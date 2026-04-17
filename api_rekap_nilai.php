<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method');
    }

    if (!isset($_FILES['grades_file']) || ($_FILES['grades_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File Excel nilai wajib diunggah');
    }

    $uploadedFile = $_FILES['grades_file'];
    $extension = strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION));
    if ($extension !== 'xlsx') {
        throw new RuntimeException('Format file harus .xlsx');
    }

    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($uploadedFile['tmp_name']);
    $sheet = $spreadsheet->getSheet(0);
    $sheetName = $sheet->getTitle();
    $rows = $sheet->toArray(null, true, true, false);

    [$headerIndex, $headerMap, $detectedHeaders] = detectGradeHeader($rows);

    if (!isset($headerMap['nim']) || !isset($headerMap['nama']) || (!isset($headerMap['uts']) && !isset($headerMap['uas']))) {
        throw new RuntimeException('Header Excel tidak dikenali. Gunakan minimal kolom NIM, Nama, dan salah satu atau kedua kolom UTS/UAS. Header terdeteksi: ' . implode(', ', $detectedHeaders));
    }

    $db = getDatabaseConnection();
    $studentLookup = loadStudentLookupByNim($db);
    $parsedRows = [];
    $utsValues = [];
    $uasValues = [];
    $finalValues = [];
    $matchedStudents = 0;
    $classCounts = [];

    for ($index = $headerIndex + 1; $index < count($rows); $index++) {
        $row = $rows[$index] ?? [];
        $nim = sanitizeGradeCell($row[$headerMap['nim']] ?? '');
        $nama = sanitizeGradeCell($row[$headerMap['nama']] ?? '');
        $className = isset($headerMap['kelas']) ? sanitizeGradeCell($row[$headerMap['kelas']] ?? '') : '';
        $uts = isset($headerMap['uts']) ? normalizeGradeNumber($row[$headerMap['uts']] ?? null) : null;
        $uas = isset($headerMap['uas']) ? normalizeGradeNumber($row[$headerMap['uas']] ?? null) : null;

        if ($nim === '' && $nama === '' && $className === '' && $uts === null && $uas === null) {
            continue;
        }

        if ($nim === '' || $nama === '') {
            continue;
        }

        $masterStudent = $studentLookup[$nim] ?? null;
        if ($masterStudent) {
            $matchedStudents++;
        }

        if ($uts !== null) {
            $utsValues[] = $uts;
        }

        if ($uas !== null) {
            $uasValues[] = $uas;
        }

        $finalScore = null;
        if ($uts !== null && $uas !== null) {
            $finalScore = round(($uts + $uas) / 2, 2);
            $finalValues[] = $finalScore;
        }

        $effectiveClass = $className !== '' ? $className : ($masterStudent['class_name'] ?? 'Tanpa kelas');
        if (!isset($classCounts[$effectiveClass])) {
            $classCounts[$effectiveClass] = 0;
        }
        $classCounts[$effectiveClass]++;

        $parsedRows[] = [
            'row_number' => $index + 1,
            'nim' => $nim,
            'nama' => $nama,
            'kelas' => $className,
            'master_class' => $masterStudent['class_name'] ?? '',
            'uts' => $uts,
            'uas' => $uas,
            'final_score' => $finalScore,
            'matched_master' => $masterStudent !== null,
        ];
    }

    arsort($classCounts);
    $classDistribution = [];
    foreach ($classCounts as $className => $count) {
        $classDistribution[] = ['name' => $className, 'count' => $count];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Rekap nilai berhasil dibaca',
        'meta' => [
            'file_name' => $uploadedFile['name'] ?? 'nilai.xlsx',
            'sheet_name' => $sheetName,
            'detected_headers' => $detectedHeaders,
        ],
        'summary' => [
            'total_rows' => count($parsedRows),
            'matched_students' => $matchedStudents,
            'unmatched_students' => count($parsedRows) - $matchedStudents,
            'avg_uts' => calculateAverage($utsValues),
            'avg_uas' => calculateAverage($uasValues),
            'avg_final' => calculateAverage($finalValues),
            'highest_final' => $finalValues ? max($finalValues) : null,
            'lowest_final' => $finalValues ? min($finalValues) : null,
            'class_distribution' => $classDistribution,
        ],
        'data' => $parsedRows,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

function detectGradeHeader(array $rows)
{
    foreach ($rows as $rowIndex => $row) {
        $normalizedHeaders = [];
        $detectedHeaders = [];

        foreach ((array) $row as $columnIndex => $value) {
            $label = sanitizeGradeCell($value);
            $detectedHeaders[] = $label;
            $normalized = normalizeHeaderLabel($label);
            if ($normalized === '') {
                continue;
            }

            $headerKey = resolveGradeHeaderKey($normalized);
            if ($headerKey !== null && !isset($normalizedHeaders[$headerKey])) {
                $normalizedHeaders[$headerKey] = $columnIndex;
            }
        }

        if (isset($normalizedHeaders['nim']) && isset($normalizedHeaders['nama']) && (isset($normalizedHeaders['uts']) || isset($normalizedHeaders['uas']))) {
            return [$rowIndex, $normalizedHeaders, array_values(array_filter($detectedHeaders, static function ($item) {
                return $item !== '';
            }))];
        }
    }

    return [0, [], []];
}

function resolveGradeHeaderKey($normalizedLabel)
{
    $aliases = [
        'nim' => ['nim', 'nomorindukmahasiswa', 'nomormahasiswa', 'nimmhs'],
        'nama' => ['nama', 'namamahasiswa', 'mahasiswa', 'namamhs'],
        'kelas' => ['kelas', 'class', 'tingkat', 'kelasmahasiswa'],
        'uts' => ['uts', 'nilaiuts', 'scoreuts'],
        'uas' => ['uas', 'nilaiuas', 'scoreuas'],
    ];

    foreach ($aliases as $key => $values) {
        if (in_array($normalizedLabel, $values, true)) {
            return $key;
        }
    }

    return null;
}

function normalizeHeaderLabel($value)
{
    $value = strtolower(sanitizeGradeCell($value));
    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function sanitizeGradeCell($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function normalizeGradeNumber($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return round((float) $value, 2);
    }

    $value = str_replace(',', '.', sanitizeGradeCell($value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    return round((float) $value, 2);
}

function calculateAverage(array $values)
{
    if (!$values) {
        return null;
    }

    return round(array_sum($values) / count($values), 2);
}

function loadStudentLookupByNim(PDO $db)
{
    $stmt = $db->query('SELECT s.nim, s.name, c.name AS class_name FROM master_students s LEFT JOIN master_classes c ON c.id = s.class_id WHERE s.is_active = 1');
    $lookup = [];

    foreach ($stmt->fetchAll() as $row) {
        $lookup[(string) $row['nim']] = [
            'name' => $row['name'],
            'class_name' => $row['class_name'] ?? '',
        ];
    }

    return $lookup;
}
