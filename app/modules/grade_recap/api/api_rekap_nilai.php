<?php
require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/app/shared/lib/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && $action === 'list_class_recaps') {
        header('Content-Type: application/json; charset=utf-8');
        $db = getDatabaseConnection();
        $filters = getRecapFiltersFromArray($_GET, false);
        $rows = getStoredClassRecapList($db, $filters);
        $latestImport = getLatestRecapImport($db, $filters);

        echo json_encode([
            'success' => true,
            'message' => 'Daftar kelas rekap berhasil dimuat',
            'meta' => [
                'total_classes' => count($rows),
                'active_filters' => $filters,
                'filter_options' => getRecapFilterOptionLists($db),
                'import_id' => $latestImport['id'] ?? null,
                'file_name' => $latestImport['file_name'] ?? null,
                'sheet_name' => $latestImport['sheet_name'] ?? null,
                'exam_type' => $latestImport['exam_type'] ?? null,
                'academic_year' => $latestImport['academic_year'] ?? null,
                'term' => $latestImport['term'] ?? null,
            ],
            'data' => $rows,
        ]);
        exit;
    }

    if ($method === 'GET' && ($action === 'download_class_recap' || $action === 'export_class_recap_xlsx')) {
        $db = getDatabaseConnection();
        $className = sanitizeGradeCell($_GET['class_name'] ?? '');
        if ($className === '') {
            throw new RuntimeException('class_name wajib diisi');
        }

        $filters = getRecapFiltersFromArray($_GET, false);

        $pivot = getClassRecapPivotData($db, $className, $filters);
        if (!$pivot['subjects'] || !$pivot['rows']) {
            throw new RuntimeException('Data kelas tidak ditemukan pada rekap terakhir');
        }

        streamClassRecapPivotXlsx($className, $pivot['subjects'], $pivot['rows']);
        exit;
    }

    if ($method === 'POST' && $action === 'delete_class_recap') {
        header('Content-Type: application/json; charset=utf-8');
        $db = getDatabaseConnection();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Payload tidak valid');
        }

        $className = sanitizeGradeCell($payload['class_name'] ?? '');
        if ($className === '') {
            throw new RuntimeException('class_name wajib diisi');
        }

        $db->beginTransaction();
        $stmt = $db->prepare('DELETE FROM grade_recap_results WHERE class_name = :class_name');
        $stmt->execute([':class_name' => $className]);
        $deleted = $stmt->rowCount();
        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Nilai kelas berhasil dihapus',
            'deleted_rows' => $deleted,
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'sync_assigned_students') {
        header('Content-Type: application/json; charset=utf-8');
        $db = getDatabaseConnection();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Payload tidak valid');
        }

        $importId = intval($payload['import_id'] ?? 0);
        $rows = $payload['rows'] ?? null;
        if ($importId <= 0 || !is_array($rows) || !$rows) {
            throw new RuntimeException('import_id dan rows wajib diisi');
        }

        $db->beginTransaction();
        $updatedCount = 0;
        $stmt = $db->prepare('UPDATE grade_recap_results SET student_id = :student_id, student_name = :student_name, class_name = :class_name, updated_at = CURRENT_TIMESTAMP WHERE import_id = :import_id AND nim = :nim');

        foreach ($rows as $row) {
            $nim = sanitizeGradeCell($row['nim'] ?? '');
            $className = sanitizeGradeCell($row['class_name'] ?? '');
            $studentName = sanitizeGradeCell($row['student_name'] ?? '');
            $studentId = intval($row['student_id'] ?? 0);

            if ($nim === '' || $className === '' || $studentId <= 0 || $studentName === '') {
                continue;
            }

            $stmt->execute([
                ':student_id' => $studentId,
                ':student_name' => $studentName,
                ':class_name' => $className,
                ':import_id' => $importId,
                ':nim' => $nim,
            ]);

            if ($stmt->rowCount() > 0) {
                $updatedCount++;
            }
        }

        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Sinkronisasi hasil assign ke rekap tersimpan selesai',
            'updated_rows' => $updatedCount,
        ]);
        exit;
    }

    if ($method !== 'POST') {
        throw new RuntimeException('Invalid request method');
    }

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_FILES['grades_file']) || ($_FILES['grades_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File Excel nilai wajib diunggah');
    }

    $subjectId = intval($_POST['subject_id'] ?? 0);
    if ($subjectId <= 0) {
        throw new RuntimeException('Mata kuliah wajib dipilih sebelum upload nilai');
    }

    $examType = normalizeExamType($_POST['exam_type'] ?? '');
    if ($examType === null) {
        throw new RuntimeException('Jenis ujian wajib dipilih sebelum upload nilai');
    }

    $academicYear = normalizeAcademicYear($_POST['academic_year'] ?? '');
    if ($academicYear === null) {
        throw new RuntimeException('Tahun ajaran wajib diisi dengan format seperti 2025/2026');
    }

    $term = normalizeTerm($_POST['term'] ?? '');
    if ($term === null) {
        throw new RuntimeException('Periode semester wajib dipilih (Ganjil/Genap)');
    }

    $db = getDatabaseConnection();
    if (!academicPeriodExists($db, $academicYear, $term)) {
        throw new RuntimeException('Kombinasi tahun ajaran dan periode semester belum terdaftar di master periode akademik');
    }

    $subject = getSubjectById($db, $subjectId);

    $uploadedFile = $_FILES['grades_file'];
    $extension = strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION));
    if ($extension !== 'xlsx') {
        throw new RuntimeException('Format file harus .xlsx');
    }

    validateSpreadsheetRuntime();

    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($uploadedFile['tmp_name']);
    $sheet = $spreadsheet->getSheet(0);
    $sheetName = $sheet->getTitle();
    $rows = $sheet->toArray(null, true, true, false);

    $studentLookup = loadStudentLookupByNim($db);

    $stats = [
        'source_rows' => 0,
        'valid_rows' => 0,
        'invalid_rows' => 0,
        'duplicate_nim_rows' => 0,
        'category_rows' => [
            'normal' => 0,
            'remedial' => 0,
            'susulan' => 0,
        ],
    ];

    $aggregatedByNim = [];

    for ($index = 1; $index < count($rows); $index++) {
        $row = $rows[$index] ?? [];

        $nim = sanitizeGradeCell($row[3] ?? ''); // D
        $sourceName = sanitizeGradeCell($row[1] ?? ''); // B
        $bsText = sanitizeBsCell($row[6] ?? ''); // G
        $score = normalizeGradeNumber($row[9] ?? null); // J
        $categoryMarker = strtoupper(sanitizeGradeCell($row[10] ?? '')); // K

        if ($nim === '' && $sourceName === '' && $bsText === '' && $score === null && $categoryMarker === '') {
            continue;
        }

        $stats['source_rows']++;

        $category = resolveAssessmentCategory($categoryMarker);
        if ($nim === '' || $score === null || $category === null) {
            $stats['invalid_rows']++;
            continue;
        }

        $nimKey = 'nim:' . $nim;

        if (!isset($aggregatedByNim[$nimKey])) {
            $aggregatedByNim[$nimKey] = [
                'nim' => $nim,
                'source_name' => $sourceName,
                'normal_bs' => null,
                'normal_score' => null,
                'normal_letter' => null,
                'remedial_bs' => null,
                'remedial_score' => null,
                'remedial_letter' => null,
                'susulan_bs' => null,
                'susulan_score' => null,
                'susulan_letter' => null,
                'final_score' => null,
                'final_letter' => null,
                'duplicate_nim_count' => 0,
                '_seen_rows' => 0,
            ];
        }

        $record = &$aggregatedByNim[$nimKey];
        if ($record['_seen_rows'] > 0) {
            $record['duplicate_nim_count']++;
            $stats['duplicate_nim_rows']++;
        }
        $record['_seen_rows']++;

        $stats['valid_rows']++;
        $stats['category_rows'][$category]++;

        applyCategoryScore($record, $category, $score, $bsText);
    }

    $classCounts = [];
    $normalValues = [];
    $remedialValues = [];
    $susulanValues = [];
    $finalValues = [];
    $matchedStudents = 0;
    $resultRows = [];

    foreach ($aggregatedByNim as $record) {
        $nim = (string) ($record['nim'] ?? '');
        $record['final_score'] = maxScore($record['normal_score'], $record['remedial_score'], $record['susulan_score']);
        $record['final_bs'] = resolveFinalBs($record);
        $record['final_letter'] = scoreToLetter($record['final_score']);

        $masterStudent = $studentLookup[$nim] ?? null;
        if ($masterStudent !== null) {
            $matchedStudents++;
        }

        $name = $masterStudent['name'] ?? $record['source_name'];
        $className = $masterStudent['class_name'] ?? '';

        if ($record['normal_score'] !== null) {
            $normalValues[] = $record['normal_score'];
        }
        if ($record['remedial_score'] !== null) {
            $remedialValues[] = $record['remedial_score'];
        }
        if ($record['susulan_score'] !== null) {
            $susulanValues[] = $record['susulan_score'];
        }
        if ($record['final_score'] !== null) {
            $finalValues[] = $record['final_score'];
        }

        $effectiveClass = $className !== '' ? $className : 'Tanpa kelas';
        if (!isset($classCounts[$effectiveClass])) {
            $classCounts[$effectiveClass] = 0;
        }
        $classCounts[$effectiveClass]++;

        unset($record['_seen_rows']);

        $resultRows[] = [
            'nim' => $nim,
            'nama' => $name,
            'source_name' => $record['source_name'],
            'kelas' => $className,
            'master_class' => $className,
            'normal_bs' => $record['normal_bs'],
            'normal_score' => $record['normal_score'],
            'normal_letter' => $record['normal_letter'],
            'remedial_bs' => $record['remedial_bs'],
            'remedial_score' => $record['remedial_score'],
            'remedial_letter' => $record['remedial_letter'],
            'susulan_bs' => $record['susulan_bs'],
            'susulan_score' => $record['susulan_score'],
            'susulan_letter' => $record['susulan_letter'],
            'final_score' => $record['final_score'],
            'final_letter' => $record['final_letter'],
            'duplicate_nim_count' => $record['duplicate_nim_count'],
            'final_bs' => $record['final_bs'],
            'matched_master' => $masterStudent !== null,
            'student_id' => $masterStudent['id'] ?? null,
        ];
    }

    usort($resultRows, static function ($a, $b) {
        return strcmp($a['nim'], $b['nim']);
    });

    $rawResultCount = count($resultRows);
    if ($resultRows) {
        $uniqueRows = [];
        foreach ($resultRows as $row) {
            $uniqueRows[$row['nim']] = $row;
        }
        $resultRows = array_values($uniqueRows);
    }

    $dedupedDiff = $rawResultCount - count($resultRows);
    if ($dedupedDiff > 0) {
        $stats['duplicate_nim_rows'] += $dedupedDiff;
    }

    $classCounts = [];
    $normalValues = [];
    $remedialValues = [];
    $susulanValues = [];
    $finalValues = [];
    $matchedStudents = 0;

    foreach ($resultRows as $row) {
        if (!empty($row['matched_master'])) {
            $matchedStudents++;
        }

        if ($row['normal_score'] !== null) {
            $normalValues[] = $row['normal_score'];
        }
        if ($row['remedial_score'] !== null) {
            $remedialValues[] = $row['remedial_score'];
        }
        if ($row['susulan_score'] !== null) {
            $susulanValues[] = $row['susulan_score'];
        }
        if ($row['final_score'] !== null) {
            $finalValues[] = $row['final_score'];
        }

        $effectiveClass = $row['kelas'] !== '' ? $row['kelas'] : 'Tanpa kelas';
        if (!isset($classCounts[$effectiveClass])) {
            $classCounts[$effectiveClass] = 0;
        }
        $classCounts[$effectiveClass]++;
    }

    arsort($classCounts);
    $classDistribution = [];
    foreach ($classCounts as $className => $count) {
        $classDistribution[] = [
            'name' => $className,
            'count' => $count,
        ];
    }

    $unmatchedStudents = count($resultRows) - $matchedStudents;

    $db->beginTransaction();

    $importStmt = $db->prepare('INSERT INTO grade_recap_imports (file_name, sheet_name, total_rows, valid_rows, invalid_rows, duplicate_nim_rows, matched_students, unmatched_students, subject_id, exam_type, academic_year, term, updated_at) VALUES (:file_name, :sheet_name, :total_rows, :valid_rows, :invalid_rows, :duplicate_nim_rows, :matched_students, :unmatched_students, :subject_id, :exam_type, :academic_year, :term, CURRENT_TIMESTAMP)');
    $importStmt->execute([
        ':file_name' => $uploadedFile['name'] ?? 'nilai.xlsx',
        ':sheet_name' => $sheetName,
        ':total_rows' => count($resultRows),
        ':valid_rows' => $stats['valid_rows'],
        ':invalid_rows' => $stats['invalid_rows'],
        ':duplicate_nim_rows' => $stats['duplicate_nim_rows'],
        ':matched_students' => $matchedStudents,
        ':unmatched_students' => $unmatchedStudents,
        ':subject_id' => $subjectId,
        ':exam_type' => $examType,
        ':academic_year' => $academicYear,
        ':term' => $term,
    ]);
    $importId = (int) $db->lastInsertId();

    $resultStmt = $db->prepare('INSERT INTO grade_recap_results (import_id, subject_id, exam_type, academic_year, term, nim, source_name, student_id, student_name, class_name, normal_bs, normal_score, normal_letter, remedial_bs, remedial_score, remedial_letter, susulan_bs, susulan_score, susulan_letter, final_bs, final_score, final_letter, duplicate_nim_count, updated_at) VALUES (:import_id, :subject_id, :exam_type, :academic_year, :term, :nim, :source_name, :student_id, :student_name, :class_name, :normal_bs, :normal_score, :normal_letter, :remedial_bs, :remedial_score, :remedial_letter, :susulan_bs, :susulan_score, :susulan_letter, :final_bs, :final_score, :final_letter, :duplicate_nim_count, CURRENT_TIMESTAMP) ON CONFLICT(import_id, nim) DO UPDATE SET subject_id = excluded.subject_id, exam_type = excluded.exam_type, academic_year = excluded.academic_year, term = excluded.term, source_name = excluded.source_name, student_id = excluded.student_id, student_name = excluded.student_name, class_name = excluded.class_name, normal_bs = excluded.normal_bs, normal_score = excluded.normal_score, normal_letter = excluded.normal_letter, remedial_bs = excluded.remedial_bs, remedial_score = excluded.remedial_score, remedial_letter = excluded.remedial_letter, susulan_bs = excluded.susulan_bs, susulan_score = excluded.susulan_score, susulan_letter = excluded.susulan_letter, final_bs = excluded.final_bs, final_score = excluded.final_score, final_letter = excluded.final_letter, duplicate_nim_count = excluded.duplicate_nim_count, updated_at = CURRENT_TIMESTAMP');

    foreach ($resultRows as $row) {
        $resultStmt->execute([
            ':import_id' => $importId,
            ':subject_id' => $subjectId,
            ':exam_type' => $examType,
            ':academic_year' => $academicYear,
            ':term' => $term,
            ':nim' => $row['nim'],
            ':source_name' => $row['source_name'],
            ':student_id' => $row['student_id'],
            ':student_name' => $row['nama'],
            ':class_name' => $row['kelas'] !== '' ? $row['kelas'] : null,
            ':normal_bs' => $row['normal_bs'],
            ':normal_score' => $row['normal_score'],
            ':normal_letter' => $row['normal_letter'],
            ':remedial_bs' => $row['remedial_bs'],
            ':remedial_score' => $row['remedial_score'],
            ':remedial_letter' => $row['remedial_letter'],
            ':susulan_bs' => $row['susulan_bs'],
            ':susulan_score' => $row['susulan_score'],
            ':susulan_letter' => $row['susulan_letter'],
            ':final_bs' => $row['final_bs'],
            ':final_score' => $row['final_score'],
            ':final_letter' => $row['final_letter'],
            ':duplicate_nim_count' => $row['duplicate_nim_count'],
        ]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Rekap nilai ' . $examType . ' berhasil diproses',
        'meta' => [
            'file_name' => $uploadedFile['name'] ?? 'nilai.xlsx',
            'sheet_name' => $sheetName,
            'import_id' => $importId,
            'exam_type' => $examType,
            'academic_year' => $academicYear,
            'term' => $term,
            'subject' => [
                'id' => $subjectId,
                'name' => $subject['name'],
            ],
            'fixed_columns' => [
                'nama' => 'B',
                'nim' => 'D',
                'benar_salah' => 'G',
                'nilai' => 'J',
                'kategori' => 'K',
            ],
        ],
        'summary' => [
            'total_rows' => $stats['valid_rows'],
            'unique_nim_rows' => count($resultRows),
            'source_rows' => $stats['source_rows'],
            'valid_rows' => $stats['valid_rows'],
            'invalid_rows' => $stats['invalid_rows'],
            'duplicate_nim_rows' => $stats['duplicate_nim_rows'],
            'category_rows' => $stats['category_rows'],
            'matched_students' => $matchedStudents,
            'unmatched_students' => $unmatchedStudents,
            'avg_normal' => calculateAverage($normalValues),
            'avg_remedial' => calculateAverage($remedialValues),
            'avg_susulan' => calculateAverage($susulanValues),
            'avg_final' => calculateAverage($finalValues),
            'highest_final' => $finalValues ? max($finalValues) : null,
            'lowest_final' => $finalValues ? min($finalValues) : null,
            'class_distribution' => $classDistribution,
        ],
        'data' => $resultRows,
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

function sanitizeGradeCell($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function sanitizeBsCell($value)
{
    $value = sanitizeGradeCell($value);
    if ($value === '') {
        return '';
    }

    return preg_replace('/\s*\/\s*/', '/', $value);
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

function normalizeExamType($value)
{
    $value = strtoupper(sanitizeGradeCell($value));
    if ($value === 'UTS' || $value === 'UAS') {
        return $value;
    }

    return null;
}

function normalizeAcademicYear($value)
{
    return normalizeAcademicPeriodAcademicYear($value);
}

function normalizeTerm($value)
{
    return normalizeAcademicPeriodTerm($value);
}

function getRecapFiltersFromArray(array $source, $strict)
{
    $examType = normalizeExamType($source['exam_type'] ?? '');
    if ($strict && isset($source['exam_type']) && sanitizeGradeCell($source['exam_type']) !== '' && $examType === null) {
        throw new RuntimeException('Jenis ujian tidak valid');
    }

    $academicYear = normalizeAcademicYear($source['academic_year'] ?? '');
    if ($strict && isset($source['academic_year']) && sanitizeGradeCell($source['academic_year']) !== '' && $academicYear === null) {
        throw new RuntimeException('Tahun ajaran tidak valid');
    }

    $term = normalizeTerm($source['term'] ?? '');
    if ($strict && isset($source['term']) && sanitizeGradeCell($source['term']) !== '' && $term === null) {
        throw new RuntimeException('Periode semester tidak valid');
    }

    return [
        'exam_type' => $examType,
        'academic_year' => $academicYear,
        'term' => $term,
    ];
}

function buildRecapFilterConditions($alias, array $filters, array &$params)
{
    $conditions = [];

    if (($filters['exam_type'] ?? null) !== null) {
        $conditions[] = 'COALESCE(' . $alias . '.exam_type, "UAS") = :filter_exam_type';
        $params[':filter_exam_type'] = $filters['exam_type'];
    }

    if (($filters['academic_year'] ?? null) !== null) {
        $conditions[] = 'COALESCE(' . $alias . '.academic_year, "") = :filter_academic_year';
        $params[':filter_academic_year'] = $filters['academic_year'];
    }

    if (($filters['term'] ?? null) !== null) {
        $conditions[] = 'COALESCE(' . $alias . '.term, "") = :filter_term';
        $params[':filter_term'] = $filters['term'];
    }

    return $conditions;
}

function resolveAssessmentCategory($marker)
{
    if ($marker === 'REMEDIAL' || $marker === 'SP') {
        return 'remedial';
    }

    if ($marker === 'SUSULAN') {
        return 'susulan';
    }

    if ($marker === '' || preg_match('/^[A-E]$/', $marker) === 1) {
        return 'normal';
    }

    return null;
}

function applyCategoryScore(array &$record, $category, $score, $bsText)
{
    $scoreKey = $category . '_score';
    $bsKey = $category . '_bs';
    $letterKey = $category . '_letter';

    $existing = $record[$scoreKey];
    if ($existing === null || $score > $existing) {
        $record[$scoreKey] = $score;
        $record[$bsKey] = $bsText;
        $record[$letterKey] = scoreToLetter($score);
        return;
    }

    if ($score === $existing && $record[$bsKey] === null && $bsText !== '') {
        $record[$bsKey] = $bsText;
    }
}

function maxScore($normal, $remedial, $susulan)
{
    $values = [];
    if ($normal !== null) {
        $values[] = $normal;
    }
    if ($remedial !== null) {
        $values[] = $remedial;
    }
    if ($susulan !== null) {
        $values[] = $susulan;
    }

    if (!$values) {
        return null;
    }

    return max($values);
}

function scoreToLetter($score)
{
    if ($score === null) {
        return null;
    }

    if ($score >= 85) {
        return 'A';
    }

    if ($score >= 70) {
        return 'B';
    }

    if ($score >= 55) {
        return 'C';
    }

    if ($score >= 40) {
        return 'D';
    }

    return 'E';
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
    $stmt = $db->query('SELECT s.id, s.nim, s.name, c.name AS class_name FROM master_students s LEFT JOIN master_classes c ON c.id = s.class_id WHERE s.is_active = 1');
    $lookup = [];

    foreach ($stmt->fetchAll() as $row) {
        $lookup[(string) $row['nim']] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'class_name' => $row['class_name'] ?? '',
        ];
    }

    return $lookup;
}

function validateSpreadsheetRuntime()
{
    $missing = [];

    if (!function_exists('simplexml_load_string')) {
        $missing[] = 'simplexml';
    }
    if (!class_exists('DOMDocument')) {
        $missing[] = 'dom';
    }
    if (!class_exists('XMLReader')) {
        $missing[] = 'xmlreader';
    }
    if (!extension_loaded('zip')) {
        $missing[] = 'zip';
    }

    if (!$missing) {
        return;
    }

    $list = implode(', ', $missing);
    throw new RuntimeException(
        'Runtime PHP belum memuat extension untuk baca XLSX (' . $list . '). ' .
        'Pastikan extension aktif di SAPI web (php-fpm/Apache), bukan hanya CLI, lalu restart servicenya.'
    );
}

function getLatestRecapImport(PDO $db, array $filters = [])
{
    $params = [];
    $conditions = buildRecapFilterConditions('i', $filters, $params);
    $sql = 'SELECT i.id, i.file_name, i.sheet_name, i.created_at, i.exam_type, i.academic_year, i.term
            FROM grade_recap_imports i';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY i.id DESC LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getLatestRecapScopeSql()
{
    return 'SELECT subject_id,
                   COALESCE(exam_type, "UAS") AS exam_type,
                   COALESCE(academic_year, "") AS academic_year_key,
                   COALESCE(term, "") AS term_key,
                   COALESCE(class_name, "") AS class_name_key,
                   MAX(import_id) AS latest_import_id
            FROM grade_recap_results
            WHERE subject_id IS NOT NULL
            GROUP BY subject_id, COALESCE(exam_type, "UAS"), COALESCE(academic_year, ""), COALESCE(term, ""), COALESCE(class_name, "")';
}

function getStoredClassRecapList(PDO $db, array $filters = [])
{
    $latestSql = getLatestRecapScopeSql();
    $params = [];
    $conditions = buildRecapFilterConditions('r', $filters, $params);
    $where = ['r.class_name IS NOT NULL', 'TRIM(r.class_name) <> ""'];
    $where = array_merge($where, $conditions);
    $sql = 'SELECT r.class_name,
                   COUNT(DISTINCT r.nim) AS total_students,
                   COUNT(DISTINCT r.subject_id || "|" || COALESCE(r.exam_type, "UAS") || "|" || COALESCE(r.academic_year, "") || "|" || COALESCE(r.term, "")) AS total_subjects,
                   ROUND(AVG(r.final_score), 2) AS avg_final,
                   MAX(r.final_score) AS highest_final,
                   MIN(r.final_score) AS lowest_final,
                   MAX(i.created_at) AS last_import_at
              FROM grade_recap_results r
              INNER JOIN grade_recap_imports i ON i.id = r.import_id
              INNER JOIN (' . $latestSql . ') latest ON latest.latest_import_id = r.import_id
                 AND latest.subject_id = r.subject_id
                 AND latest.exam_type = COALESCE(r.exam_type, "UAS")
                 AND latest.academic_year_key = COALESCE(r.academic_year, "")
                 AND latest.term_key = COALESCE(r.term, "")
                 AND latest.class_name_key = COALESCE(r.class_name, "")
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY r.class_name
              ORDER BY r.class_name ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'class_name' => $row['class_name'],
            'total_students' => (int) ($row['total_students'] ?? 0),
            'total_subjects' => (int) ($row['total_subjects'] ?? 0),
            'avg_final' => $row['avg_final'] !== null ? (float) $row['avg_final'] : null,
            'highest_final' => $row['highest_final'] !== null ? (float) $row['highest_final'] : null,
            'lowest_final' => $row['lowest_final'] !== null ? (float) $row['lowest_final'] : null,
            'last_import_at' => $row['last_import_at'] ?? null,
        ];
    }

    return $rows;
}

function getRecapFilterOptionLists(PDO $db)
{
    $masterPeriods = getAcademicPeriods($db);
    $historyStmt = $db->query('SELECT DISTINCT academic_year, term FROM grade_recap_imports WHERE (academic_year IS NOT NULL AND TRIM(academic_year) <> "") OR (term IS NOT NULL AND TRIM(term) <> "")');

    $academicYears = [];
    $terms = [];
    $academicPeriods = [];
    $seenPeriods = [];

    foreach ($masterPeriods as $period) {
        $academicYear = normalizeAcademicYear($period['academic_year'] ?? '');
        $term = normalizeTerm($period['term'] ?? '');
        if ($academicYear !== null) {
            $academicYears[$academicYear] = $academicYear;
        }
        if ($term !== null) {
            $terms[$term] = $term;
        }
        if ($academicYear !== null && $term !== null) {
            $key = $academicYear . '::' . $term;
            if (!isset($seenPeriods[$key])) {
                $seenPeriods[$key] = true;
                $academicPeriods[] = [
                    'academic_year' => $academicYear,
                    'term' => $term,
                ];
            }
        }
    }

    foreach ($historyStmt->fetchAll() as $row) {
        $academicYear = normalizeAcademicYear($row['academic_year'] ?? '');
        $term = normalizeTerm($row['term'] ?? '');
        if ($academicYear !== null) {
            $academicYears[$academicYear] = $academicYear;
        }
        if ($term !== null) {
            $terms[$term] = $term;
        }
        if ($academicYear !== null && $term !== null) {
            $key = $academicYear . '::' . $term;
            if (!isset($seenPeriods[$key])) {
                $seenPeriods[$key] = true;
                $academicPeriods[] = [
                    'academic_year' => $academicYear,
                    'term' => $term,
                ];
            }
        }
    }

    $academicYears = array_values($academicYears);
    rsort($academicYears, SORT_STRING);

    $orderedTerms = [];
    foreach (['GANJIL', 'GENAP'] as $term) {
        if (isset($terms[$term])) {
            $orderedTerms[] = $term;
            unset($terms[$term]);
        }
    }
    if ($terms) {
        ksort($terms, SORT_STRING);
        $orderedTerms = array_merge($orderedTerms, array_values($terms));
    }

    usort($academicPeriods, static function ($a, $b) {
        if ($a['academic_year'] === $b['academic_year']) {
            $order = ['GANJIL' => 1, 'GENAP' => 2];
            return ($order[$a['term']] ?? 99) <=> ($order[$b['term']] ?? 99);
        }
        return strcmp($b['academic_year'], $a['academic_year']);
    });

    return [
        'exam_types' => ['UTS', 'UAS'],
        'academic_years' => $academicYears,
        'terms' => $orderedTerms,
        'academic_periods' => $academicPeriods,
    ];
}

function getStoredClassRecapRows(PDO $db, $importId, $className)
{
    $stmt = $db->prepare('SELECT nim, student_name, class_name, normal_bs, normal_score, normal_letter, remedial_bs, remedial_score, remedial_letter, susulan_bs, susulan_score, susulan_letter, final_score, final_letter, duplicate_nim_count FROM grade_recap_results WHERE import_id = :import_id AND class_name = :class_name ORDER BY nim ASC');
    $stmt->execute([
        ':import_id' => $importId,
        ':class_name' => $className,
    ]);

    return $stmt->fetchAll();
}

function getSubjectById(PDO $db, $subjectId)
{
    $stmt = $db->prepare('SELECT id, name FROM master_subjects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $subjectId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Mata kuliah tidak ditemukan di master');
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
    ];
}

function resolveFinalBs(array $row)
{
    $finalScore = $row['final_score'] ?? null;
    if ($finalScore === null) {
        return null;
    }

    $candidates = [
        ['score' => $row['normal_score'] ?? null, 'bs' => $row['normal_bs'] ?? null],
        ['score' => $row['remedial_score'] ?? null, 'bs' => $row['remedial_bs'] ?? null],
        ['score' => $row['susulan_score'] ?? null, 'bs' => $row['susulan_bs'] ?? null],
    ];

    foreach ($candidates as $candidate) {
        if ($candidate['score'] === null) {
            continue;
        }
        if ((float) $candidate['score'] === (float) $finalScore) {
            return $candidate['bs'];
        }
    }

    return null;
}

function getClassRecapPivotData(PDO $db, $className, array $filters = [])
{
    $latestSql = getLatestRecapScopeSql();
    $subjectParams = [':class_name' => $className];
    $subjectConditions = buildRecapFilterConditions('r', $filters, $subjectParams);
    $subjectWhere = ['r.class_name = :class_name'];
    $subjectWhere = array_merge($subjectWhere, $subjectConditions);

    $subjectSql = 'SELECT ms.id AS subject_id,
                          ms.name AS subject_name,
                          scoped.exam_type AS exam_type,
                          scoped.academic_year AS academic_year,
                          scoped.term AS term
                   FROM master_subjects ms
                   INNER JOIN (
                     SELECT r.subject_id,
                            COALESCE(r.exam_type, "UAS") AS exam_type,
                            COALESCE(r.academic_year, "") AS academic_year,
                            COALESCE(r.term, "") AS term
                      FROM grade_recap_results r
                      INNER JOIN (' . $latestSql . ') latest ON latest.latest_import_id = r.import_id
                         AND latest.subject_id = r.subject_id
                         AND latest.exam_type = COALESCE(r.exam_type, "UAS")
                         AND latest.academic_year_key = COALESCE(r.academic_year, "")
                         AND latest.term_key = COALESCE(r.term, "")
                         AND latest.class_name_key = COALESCE(r.class_name, "")
                      WHERE ' . implode(' AND ', $subjectWhere) . '
                      GROUP BY r.subject_id, COALESCE(r.exam_type, "UAS"), COALESCE(r.academic_year, ""), COALESCE(r.term, "")
                     ) scoped ON scoped.subject_id = ms.id
                    ORDER BY ms.name ASC, scoped.academic_year ASC, scoped.term ASC, scoped.exam_type ASC';

    $subjectStmt = $db->prepare($subjectSql);
    $subjectStmt->execute($subjectParams);
    $subjects = array_map(static function ($row) {
        $periodBits = [];
        if (($row['exam_type'] ?? '') !== '') {
            $periodBits[] = (string) $row['exam_type'];
        }
        if (($row['term'] ?? '') !== '') {
            $periodBits[] = (string) $row['term'];
        }
        if (($row['academic_year'] ?? '') !== '') {
            $periodBits[] = (string) $row['academic_year'];
        }

        return [
            'id' => (int) $row['subject_id'],
            'name' => (string) $row['subject_name'],
            'exam_type' => (string) ($row['exam_type'] ?? 'UAS'),
            'academic_year' => (string) ($row['academic_year'] ?? ''),
            'term' => (string) ($row['term'] ?? ''),
            'label' => (string) $row['subject_name'] . ' (' . implode(' - ', $periodBits) . ')',
        ];
    }, $subjectStmt->fetchAll());

    if (!$subjects) {
        return ['subjects' => [], 'rows' => []];
    }

    $scoreParams = [':class_name' => $className];
    $scoreConditions = buildRecapFilterConditions('r', $filters, $scoreParams);
    $scoreWhere = ['r.class_name = :class_name'];
    $scoreWhere = array_merge($scoreWhere, $scoreConditions);

    $scoreSql = 'SELECT r.subject_id,
                         COALESCE(r.exam_type, "UAS") AS exam_type,
                         COALESCE(r.academic_year, "") AS academic_year,
                         COALESCE(r.term, "") AS term,
                         r.nim,
                         COALESCE(NULLIF(r.student_name, ""), NULLIF(r.source_name, "")) AS student_name,
                         r.normal_bs,
                        r.normal_score,
                        r.normal_letter,
                        r.remedial_score,
                        r.susulan_score
                   FROM grade_recap_results r
                   INNER JOIN (' . $latestSql . ') latest ON latest.latest_import_id = r.import_id
                      AND latest.subject_id = r.subject_id
                      AND latest.exam_type = COALESCE(r.exam_type, "UAS")
                      AND latest.academic_year_key = COALESCE(r.academic_year, "")
                      AND latest.term_key = COALESCE(r.term, "")
                      AND latest.class_name_key = COALESCE(r.class_name, "")
                   WHERE ' . implode(' AND ', $scoreWhere);
    $scoreStmt = $db->prepare($scoreSql);
    $scoreStmt->execute($scoreParams);
    $scoreRows = $scoreStmt->fetchAll();

    $byNim = [];
    foreach ($scoreRows as $row) {
        $nim = (string) ($row['nim'] ?? '');
        if ($nim === '') {
            continue;
        }
        if (!isset($byNim[$nim])) {
            $byNim[$nim] = [
                'nim' => $nim,
                'name' => (string) ($row['student_name'] ?? ''),
                'subjects' => [],
            ];
        }
        $subjectKey = (int) ($row['subject_id'] ?? 0) . ':' . (string) ($row['exam_type'] ?? 'UAS') . ':' . (string) ($row['academic_year'] ?? '') . ':' . (string) ($row['term'] ?? '');
        $byNim[$nim]['subjects'][$subjectKey] = [
            'bs' => $row['normal_bs'],
            'score' => $row['normal_score'] !== null ? (float) $row['normal_score'] : null,
            'letter' => $row['normal_letter'],
            'sp_score' => $row['remedial_score'] !== null ? (float) $row['remedial_score'] : null,
            'susulan_score' => $row['susulan_score'] !== null ? (float) $row['susulan_score'] : null,
        ];
    }

    $rows = array_values($byNim);
    usort($rows, static function ($a, $b) {
        $nameCompare = strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
        if ($nameCompare !== 0) {
            return $nameCompare;
        }
        return strcmp($a['nim'], $b['nim']);
    });

    return ['subjects' => $subjects, 'rows' => $rows];
}

function streamClassRecapPivotXlsx($className, array $subjects, array $rows)
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rekap');

    $sheet->setCellValue('A3', 'Nomor');
    $sheet->setCellValue('B3', 'Nama');
    $sheet->setCellValue('C3', 'NIM');

    foreach ($subjects as $index => $subject) {
        $startColIndex = 4 + ($index * 5);
        $colBs = Coordinate::stringFromColumnIndex($startColIndex);
        $colN = Coordinate::stringFromColumnIndex($startColIndex + 1);
        $colH = Coordinate::stringFromColumnIndex($startColIndex + 2);
        $colSp = Coordinate::stringFromColumnIndex($startColIndex + 3);
        $colSusulan = Coordinate::stringFromColumnIndex($startColIndex + 4);

        $sheet->mergeCells($colBs . '1:' . $colSusulan . '1');
        $sheet->setCellValue($colBs . '1', $subject['label'] ?? $subject['name']);
        $sheet->setCellValue($colBs . '2', 'B/S');
        $sheet->setCellValue($colN . '2', 'N');
        $sheet->setCellValue($colH . '2', 'H');
        $sheet->setCellValue($colSp . '2', 'SP');
        $sheet->setCellValue($colSusulan . '2', 'Susulan');
    }

    $rowNumber = 4;
    foreach ($rows as $index => $row) {
        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['name'] ?? '');
        $sheet->setCellValueExplicit('C' . $rowNumber, $row['nim'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        foreach ($subjects as $subjectIndex => $subject) {
            $startColIndex = 4 + ($subjectIndex * 5);
            $colBs = Coordinate::stringFromColumnIndex($startColIndex);
            $colN = Coordinate::stringFromColumnIndex($startColIndex + 1);
            $colH = Coordinate::stringFromColumnIndex($startColIndex + 2);
            $colSp = Coordinate::stringFromColumnIndex($startColIndex + 3);
            $colSusulan = Coordinate::stringFromColumnIndex($startColIndex + 4);
            $subjectKey = (int) ($subject['id'] ?? 0) . ':' . (string) ($subject['exam_type'] ?? 'UAS') . ':' . (string) ($subject['academic_year'] ?? '') . ':' . (string) ($subject['term'] ?? '');
            $value = $row['subjects'][$subjectKey] ?? null;

            $sheet->setCellValue($colBs . $rowNumber, $value['bs'] ?? '');
            $sheet->setCellValue($colN . $rowNumber, $value['score'] ?? '');
            $sheet->setCellValue($colH . $rowNumber, $value['letter'] ?? '');
            $sheet->setCellValue($colSp . $rowNumber, $value['sp_score'] ?? '');
            $sheet->setCellValue($colSusulan . $rowNumber, $value['susulan_score'] ?? '');
        }

        $rowNumber++;
    }

    $safeClass = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $className);
    $timestamp = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Ymd_His');
    $fileName = 'rekap_' . ($safeClass !== '' ? $safeClass : 'kelas') . '_' . $timestamp . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}
