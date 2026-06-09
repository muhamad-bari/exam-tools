<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request(true);

if (!defined('GRADE_RECAP_HELPERS_ONLY')) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/app/shared/lib/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!defined('GRADE_RECAP_HELPERS_ONLY')) {
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

    if ($method === 'GET' && $action === 'list_class_subjects') {
        header('Content-Type: application/json; charset=utf-8');
        $db = getDatabaseConnection();
        $className = sanitizeGradeCell($_GET['class_name'] ?? '');
        if ($className === '') {
            throw new RuntimeException('class_name wajib diisi');
        }

        $filters = getRecapFiltersFromArray($_GET, false);
        $rows = getClassSubjectRecapList($db, $className, $filters);

        echo json_encode([
            'success' => true,
            'message' => 'Daftar mata kuliah kelas berhasil dimuat',
            'meta' => [
                'class_name' => $className,
                'total_subjects' => count($rows),
                'active_filters' => $filters,
            ],
            'data' => $rows,
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'update_class_subject') {
        header('Content-Type: application/json; charset=utf-8');
        $db = getDatabaseConnection();
        $payload = readGradeRecapJsonPayload();
        $scope = readClassSubjectScopePayload($payload);
        $newSubjectId = intval($payload['new_subject_id'] ?? 0);
        if ($newSubjectId <= 0) {
            throw new RuntimeException('new_subject_id wajib diisi');
        }

        $subject = getSubjectById($db, $newSubjectId);
        if ($newSubjectId === $scope['subject_id']) {
            echo json_encode([
                'success' => true,
                'message' => 'Mata kuliah kelas tidak berubah',
                'updated_rows' => 0,
                'data' => [
                    'subject' => $subject,
                ],
            ]);
            exit;
        }

        $conflictStmt = $db->prepare('SELECT COUNT(*)
                                        FROM grade_recap_results
                                       WHERE class_name = :class_name
                                         AND subject_id = :new_subject_id
                                         AND COALESCE(exam_type, "UAS") = :exam_type
                                         AND COALESCE(academic_year, "") = :academic_year
                                         AND COALESCE(term, "") = :term');
        $conflictStmt->execute([
            ':class_name' => $scope['class_name'],
            ':new_subject_id' => $newSubjectId,
            ':exam_type' => $scope['exam_type'],
            ':academic_year' => $scope['academic_year'],
            ':term' => $scope['term'],
        ]);
        if ((int) $conflictStmt->fetchColumn() > 0) {
            throw new RuntimeException('Mata kuliah pengganti sudah memiliki nilai pada kelas dan periode ini');
        }

        $db->beginTransaction();
        $stmt = $db->prepare('UPDATE grade_recap_results
                                SET subject_id = :new_subject_id,
                                    updated_at = CURRENT_TIMESTAMP
                              WHERE class_name = :class_name
                                AND subject_id = :subject_id
                                AND COALESCE(exam_type, "UAS") = :exam_type
                                AND COALESCE(academic_year, "") = :academic_year
                                AND COALESCE(term, "") = :term');
        $stmt->execute([
            ':new_subject_id' => $newSubjectId,
            ':class_name' => $scope['class_name'],
            ':subject_id' => $scope['subject_id'],
            ':exam_type' => $scope['exam_type'],
            ':academic_year' => $scope['academic_year'],
            ':term' => $scope['term'],
        ]);
        $updated = $stmt->rowCount();
        if ($updated <= 0) {
            throw new RuntimeException('Data mata kuliah kelas tidak ditemukan');
        }
        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Mata kuliah kelas berhasil diperbarui',
            'updated_rows' => $updated,
            'data' => [
                'subject' => $subject,
            ],
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'delete_class_subject_values') {
        header('Content-Type: application/json; charset=utf-8');
        $db = getDatabaseConnection();
        $payload = readGradeRecapJsonPayload();
        $scope = readClassSubjectScopePayload($payload);

        $db->beginTransaction();
        $stmt = $db->prepare('DELETE FROM grade_recap_results
                              WHERE class_name = :class_name
                                AND subject_id = :subject_id
                                AND COALESCE(exam_type, "UAS") = :exam_type
                                AND COALESCE(academic_year, "") = :academic_year
                                AND COALESCE(term, "") = :term');
        $stmt->execute([
            ':class_name' => $scope['class_name'],
            ':subject_id' => $scope['subject_id'],
            ':exam_type' => $scope['exam_type'],
            ':academic_year' => $scope['academic_year'],
            ':term' => $scope['term'],
        ]);
        $deleted = $stmt->rowCount();
        if ($deleted <= 0) {
            throw new RuntimeException('Data mata kuliah kelas tidak ditemukan');
        }
        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Nilai mata kuliah kelas berhasil dihapus',
            'deleted_rows' => $deleted,
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

    $uploadedFiles = normalizeUploadedGradeFiles($_FILES['grades_file'] ?? null);
    if (!$uploadedFiles) {
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

    validateSpreadsheetRuntime();
    $studentLookup = loadStudentLookupByNim($db);
    $combinedRowsByNim = [];
    $uploadedFileMeta = [];
    $aggregateStats = [
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

    $db->beginTransaction();
    foreach ($uploadedFiles as $fileIndex => $uploadedFile) {
        try {
            $processed = processUploadedGradeRecapFile($uploadedFile, $studentLookup);
        } catch (Throwable $fileError) {
            $fileName = sanitizeGradeCell($uploadedFile['name'] ?? '') ?: ('file-' . ($fileIndex + 1));
            throw new RuntimeException('Gagal memproses file #' . ($fileIndex + 1) . ' (' . $fileName . '): ' . $fileError->getMessage(), 0, $fileError);
        }

        $fileImportId = persistProcessedGradeRecapFile($db, [
            'subject_id' => $subjectId,
            'exam_type' => $examType,
            'academic_year' => $academicYear,
            'term' => $term,
        ], $processed);

        $uploadedFileMeta[] = [
            'file_name' => $processed['file_name'],
            'sheet_name' => $processed['sheet_name'],
            'import_id' => $fileImportId,
            'summary' => [
                'total_rows' => $processed['summary']['total_rows'],
                'unique_nim_rows' => $processed['summary']['unique_nim_rows'],
            ],
        ];

        $aggregateStats['source_rows'] += (int) ($processed['summary']['source_rows'] ?? 0);
        $aggregateStats['valid_rows'] += (int) ($processed['summary']['valid_rows'] ?? 0);
        $aggregateStats['invalid_rows'] += (int) ($processed['summary']['invalid_rows'] ?? 0);
        $aggregateStats['duplicate_nim_rows'] += (int) ($processed['summary']['duplicate_nim_rows'] ?? 0);
        $aggregateStats['category_rows']['normal'] += (int) ($processed['summary']['category_rows']['normal'] ?? 0);
        $aggregateStats['category_rows']['remedial'] += (int) ($processed['summary']['category_rows']['remedial'] ?? 0);
        $aggregateStats['category_rows']['susulan'] += (int) ($processed['summary']['category_rows']['susulan'] ?? 0);

        foreach ($processed['data'] as $row) {
            $nim = (string) ($row['nim'] ?? '');
            if ($nim === '') {
                continue;
            }

            if (!isset($combinedRowsByNim[$nim])) {
                $combinedRowsByNim[$nim] = $row;
                continue;
            }

            $aggregateStats['duplicate_nim_rows']++;
            $combinedRowsByNim[$nim] = mergeBulkRecapRow($combinedRowsByNim[$nim], $row);
        }
    }

    $combinedRows = array_values($combinedRowsByNim);
    usort($combinedRows, static function ($a, $b) {
        return strcmp((string) ($a['nim'] ?? ''), (string) ($b['nim'] ?? ''));
    });

    $derivedSummary = buildRecapResponseSummary($combinedRows, $aggregateStats);
    $activeImportId = $uploadedFileMeta ? (int) ($uploadedFileMeta[count($uploadedFileMeta) - 1]['import_id'] ?? 0) : null;
    $activeFileName = $uploadedFileMeta ? (string) ($uploadedFileMeta[count($uploadedFileMeta) - 1]['file_name'] ?? 'nilai.xlsx') : 'nilai.xlsx';
    $activeSheetName = $uploadedFileMeta ? (string) ($uploadedFileMeta[count($uploadedFileMeta) - 1]['sheet_name'] ?? '-') : '-';

    if (count($uploadedFileMeta) > 1) {
        $combinedProcessed = [
            'file_name' => 'bulk-upload-' . count($uploadedFileMeta) . '-file.xlsx',
            'sheet_name' => 'Gabungan NIM',
            'summary' => $derivedSummary,
            'data' => $combinedRows,
        ];

        $activeImportId = persistProcessedGradeRecapFile($db, [
            'subject_id' => $subjectId,
            'exam_type' => $examType,
            'academic_year' => $academicYear,
            'term' => $term,
        ], $combinedProcessed);
        $activeFileName = $combinedProcessed['file_name'];
        $activeSheetName = $combinedProcessed['sheet_name'];
    }

    $db->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Rekap nilai ' . $examType . ' berhasil diproses' . (count($uploadedFileMeta) > 1 ? ' (' . count($uploadedFileMeta) . ' file)' : ''),
        'meta' => [
            'file_name' => $activeFileName,
            'sheet_name' => $activeSheetName,
            'import_id' => $activeImportId,
            'import_ids' => array_values(array_map(static function ($meta) {
                return (int) ($meta['import_id'] ?? 0);
            }, $uploadedFileMeta)),
            'uploaded_file_count' => count($uploadedFileMeta),
            'uploaded_files' => $uploadedFileMeta,
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
        'summary' => $derivedSummary,
        'data' => $combinedRows,
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
}


function readGradeRecapJsonPayload()
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Payload tidak valid');
    }

    return $payload;
}

function readClassSubjectScopePayload(array $payload)
{
    $className = sanitizeGradeCell($payload['class_name'] ?? '');
    if ($className === '') {
        throw new RuntimeException('class_name wajib diisi');
    }

    $subjectId = intval($payload['subject_id'] ?? 0);
    if ($subjectId <= 0) {
        throw new RuntimeException('subject_id wajib diisi');
    }

    $filters = getRecapFiltersFromArray($payload, true);
    if (($filters['exam_type'] ?? null) === null) {
        throw new RuntimeException('exam_type wajib diisi');
    }
    if (($filters['academic_year'] ?? null) === null) {
        throw new RuntimeException('academic_year wajib diisi');
    }
    if (($filters['term'] ?? null) === null) {
        throw new RuntimeException('term wajib diisi');
    }

    return [
        'class_name' => $className,
        'subject_id' => $subjectId,
        'exam_type' => $filters['exam_type'],
        'academic_year' => $filters['academic_year'],
        'term' => $filters['term'],
    ];
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
    $stmt = $db->query('SELECT s.id, s.nim, s.name, s.is_active, s.student_status, c.name AS class_name FROM master_students s LEFT JOIN master_classes c ON c.id = s.class_id');
    $lookup = [];

    foreach ($stmt->fetchAll() as $row) {
        $status = normalizeStudentStatus($row['student_status'] ?? 'aktif');
        $lookup[(string) $row['nim']] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'class_name' => $row['class_name'] ?? '',
            'student_status' => $status,
            'is_active' => (int) ($row['is_active'] ?? 0),
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

function normalizeUploadedGradeFiles($filesField)
{
    if (!is_array($filesField) || !array_key_exists('name', $filesField)) {
        return [];
    }

    $normalized = [];
    if (is_array($filesField['name'])) {
        $count = count($filesField['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $filesField['name'][$i] ?? '',
                'type' => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$i] ?? 0,
            ];
        }
    } else {
        $normalized[] = [
            'name' => $filesField['name'] ?? '',
            'type' => $filesField['type'] ?? '',
            'tmp_name' => $filesField['tmp_name'] ?? '',
            'error' => $filesField['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $filesField['size'] ?? 0,
        ];
    }

    $validFiles = [];
    foreach ($normalized as $file) {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $fileName = sanitizeGradeCell($file['name'] ?? '') ?: 'tanpa-nama';
            throw new RuntimeException('Upload file gagal untuk ' . $fileName . ': ' . formatUploadErrorMessage($error));
        }
        if (sanitizeGradeCell($file['tmp_name'] ?? '') === '') {
            throw new RuntimeException('File upload tidak valid');
        }
        $validFiles[] = $file;
    }

    return $validFiles;
}

function formatUploadErrorMessage($errorCode)
{
    $code = (int) $errorCode;
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'ukuran file melebihi batas server',
        UPLOAD_ERR_FORM_SIZE => 'ukuran file melebihi batas form',
        UPLOAD_ERR_PARTIAL => 'file terupload sebagian',
        UPLOAD_ERR_NO_TMP_DIR => 'folder temporary upload tidak tersedia',
        UPLOAD_ERR_CANT_WRITE => 'gagal menulis file ke disk',
        UPLOAD_ERR_EXTENSION => 'upload dihentikan oleh extension PHP',
    ];

    return $messages[$code] ?? 'terjadi error upload';
}

function processUploadedGradeRecapFile(array $uploadedFile, array $studentLookup)
{
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

    $processed = buildRecapRowsFromSheetRows($rows, $studentLookup);
    $resultRows = $processed['rows'];
    $summary = $processed['summary'];

    return [
        'file_name' => $uploadedFile['name'] ?? 'nilai.xlsx',
        'sheet_name' => $sheetName,
        'summary' => $summary,
        'data' => $resultRows,
    ];
}

function persistProcessedGradeRecapFile(PDO $db, array $context, array $processed)
{
    $summary = $processed['summary'] ?? [];
    $resultRows = $processed['data'] ?? [];
    $persistableRows = array_values(array_filter($resultRows, static function ($row) {
        return empty($row['recap_blocked']);
    }));

    $importStmt = $db->prepare('INSERT INTO grade_recap_imports (file_name, sheet_name, total_rows, valid_rows, invalid_rows, duplicate_nim_rows, matched_students, unmatched_students, inactive_students, subject_id, exam_type, academic_year, term, updated_at) VALUES (:file_name, :sheet_name, :total_rows, :valid_rows, :invalid_rows, :duplicate_nim_rows, :matched_students, :unmatched_students, :inactive_students, :subject_id, :exam_type, :academic_year, :term, CURRENT_TIMESTAMP)');
    $importStmt->execute([
        ':file_name' => (string) ($processed['file_name'] ?? 'nilai.xlsx'),
        ':sheet_name' => (string) ($processed['sheet_name'] ?? 'Worksheet'),
        ':total_rows' => (int) ($summary['total_rows'] ?? count($persistableRows)),
        ':valid_rows' => (int) ($summary['valid_rows'] ?? 0),
        ':invalid_rows' => (int) ($summary['invalid_rows'] ?? 0),
        ':duplicate_nim_rows' => (int) ($summary['duplicate_nim_rows'] ?? 0),
        ':matched_students' => (int) ($summary['matched_students'] ?? 0),
        ':unmatched_students' => (int) ($summary['unmatched_students'] ?? 0),
        ':inactive_students' => (int) ($summary['inactive_students'] ?? 0),
        ':subject_id' => (int) $context['subject_id'],
        ':exam_type' => $context['exam_type'],
        ':academic_year' => $context['academic_year'],
        ':term' => $context['term'],
    ]);
    $importId = (int) $db->lastInsertId();

    $resultStmt = $db->prepare('INSERT INTO grade_recap_results (import_id, subject_id, exam_type, academic_year, term, nim, source_name, student_id, student_name, class_name, normal_bs, normal_score, normal_letter, remedial_bs, remedial_score, remedial_letter, susulan_bs, susulan_score, susulan_letter, final_bs, final_score, final_letter, duplicate_nim_count, updated_at) VALUES (:import_id, :subject_id, :exam_type, :academic_year, :term, :nim, :source_name, :student_id, :student_name, :class_name, :normal_bs, :normal_score, :normal_letter, :remedial_bs, :remedial_score, :remedial_letter, :susulan_bs, :susulan_score, :susulan_letter, :final_bs, :final_score, :final_letter, :duplicate_nim_count, CURRENT_TIMESTAMP) ON CONFLICT(import_id, nim) DO UPDATE SET subject_id = excluded.subject_id, exam_type = excluded.exam_type, academic_year = excluded.academic_year, term = excluded.term, source_name = excluded.source_name, student_id = excluded.student_id, student_name = excluded.student_name, class_name = excluded.class_name, normal_bs = excluded.normal_bs, normal_score = excluded.normal_score, normal_letter = excluded.normal_letter, remedial_bs = excluded.remedial_bs, remedial_score = excluded.remedial_score, remedial_letter = excluded.remedial_letter, susulan_bs = excluded.susulan_bs, susulan_score = excluded.susulan_score, susulan_letter = excluded.susulan_letter, final_bs = excluded.final_bs, final_score = excluded.final_score, final_letter = excluded.final_letter, duplicate_nim_count = excluded.duplicate_nim_count, updated_at = CURRENT_TIMESTAMP');

    foreach ($persistableRows as $row) {
        $resultStmt->execute([
            ':import_id' => $importId,
            ':subject_id' => (int) $context['subject_id'],
            ':exam_type' => $context['exam_type'],
            ':academic_year' => $context['academic_year'],
            ':term' => $context['term'],
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

    return $importId;
}

function buildRecapRowsFromSheetRows(array $rows, array $studentLookup)
{
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

        $nim = sanitizeGradeCell($row[3] ?? '');
        $sourceName = sanitizeGradeCell($row[1] ?? '');
        $bsText = sanitizeBsCell($row[6] ?? '');
        $score = normalizeGradeNumber($row[9] ?? null);
        $categoryMarker = strtoupper(sanitizeGradeCell($row[10] ?? ''));

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

    unset($record);

    $rowsByNim = [];
    foreach ($aggregatedByNim as $record) {
        $nim = (string) ($record['nim'] ?? '');
        $record['final_score'] = maxScore($record['normal_score'], $record['remedial_score'], $record['susulan_score']);
        $record['final_bs'] = resolveFinalBs($record);
        $record['final_letter'] = scoreToLetter($record['final_score']);
        unset($record['_seen_rows']);

        $masterStudent = $studentLookup[$nim] ?? null;
        $masterMatchType = determineMasterMatchType($masterStudent);
        $recapBlocked = $masterMatchType === 'inactive';
        $rowsByNim[$nim] = [
            'nim' => $nim,
            'nama' => $masterStudent['name'] ?? $record['source_name'],
            'source_name' => $record['source_name'],
            'kelas' => $masterStudent['class_name'] ?? '',
            'master_class' => $masterStudent['class_name'] ?? '',
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
            'matched_master' => $masterMatchType === 'active',
            'master_match_type' => $masterMatchType,
            'student_id' => $masterStudent['id'] ?? null,
            'student_status' => $masterStudent['student_status'] ?? '',
            'recap_blocked' => $recapBlocked,
            'recap_block_reason' => $recapBlocked ? buildInactiveStudentRecapMessage($masterStudent) : '',
        ];
    }

    $resultRows = array_values($rowsByNim);
    usort($resultRows, static function ($a, $b) {
        return strcmp((string) ($a['nim'] ?? ''), (string) ($b['nim'] ?? ''));
    });

    $summary = buildRecapResponseSummary($resultRows, $stats);

    return [
        'rows' => $resultRows,
        'summary' => $summary,
    ];
}

function mergeBulkRecapRow(array $existing, array $incoming)
{
    $merged = $existing;

    foreach (['normal', 'remedial', 'susulan'] as $category) {
        $scoreKey = $category . '_score';
        $letterKey = $category . '_letter';
        $bsKey = $category . '_bs';
        $existingScore = $merged[$scoreKey] ?? null;
        $incomingScore = $incoming[$scoreKey] ?? null;

        if ($incomingScore === null) {
            continue;
        }

        if ($existingScore === null || (float) $incomingScore > (float) $existingScore) {
            $merged[$scoreKey] = $incomingScore;
            $merged[$letterKey] = $incoming[$letterKey] ?? null;
            $merged[$bsKey] = $incoming[$bsKey] ?? null;
            continue;
        }

        if ((float) $incomingScore === (float) $existingScore && sanitizeGradeCell($merged[$bsKey] ?? '') === '' && sanitizeGradeCell($incoming[$bsKey] ?? '') !== '') {
            $merged[$bsKey] = $incoming[$bsKey];
        }
    }

    if (sanitizeGradeCell($merged['source_name'] ?? '') === '' && sanitizeGradeCell($incoming['source_name'] ?? '') !== '') {
        $merged['source_name'] = $incoming['source_name'];
    }
    if (sanitizeGradeCell($merged['nama'] ?? '') === '' && sanitizeGradeCell($incoming['nama'] ?? '') !== '') {
        $merged['nama'] = $incoming['nama'];
    }
    if (sanitizeGradeCell($merged['kelas'] ?? '') === '' && sanitizeGradeCell($incoming['kelas'] ?? '') !== '') {
        $merged['kelas'] = $incoming['kelas'];
        $merged['master_class'] = $incoming['master_class'] ?? $incoming['kelas'];
    }
    if (($merged['student_id'] ?? null) === null && ($incoming['student_id'] ?? null) !== null) {
        $merged['student_id'] = $incoming['student_id'];
    }
    if (($merged['student_status'] ?? '') === '' && ($incoming['student_status'] ?? '') !== '') {
        $merged['student_status'] = $incoming['student_status'];
    }
    if (($merged['master_match_type'] ?? '') === '' && ($incoming['master_match_type'] ?? '') !== '') {
        $merged['master_match_type'] = $incoming['master_match_type'];
    }
    if (empty($merged['recap_block_reason']) && !empty($incoming['recap_block_reason'])) {
        $merged['recap_block_reason'] = $incoming['recap_block_reason'];
    }

    $merged['matched_master'] = !empty($merged['matched_master']) || !empty($incoming['matched_master']);
    $merged['recap_blocked'] = !empty($merged['recap_blocked']) || !empty($incoming['recap_blocked']);
    $merged['duplicate_nim_count'] = (int) ($merged['duplicate_nim_count'] ?? 0) + (int) ($incoming['duplicate_nim_count'] ?? 0) + 1;

    $merged['final_score'] = maxScore($merged['normal_score'] ?? null, $merged['remedial_score'] ?? null, $merged['susulan_score'] ?? null);
    $merged['final_bs'] = resolveFinalBs($merged);
    $merged['final_letter'] = scoreToLetter($merged['final_score']);

    return $merged;
}

function buildRecapResponseSummary(array $resultRows, array $stats)
{
    $classCounts = [];
    $normalValues = [];
    $remedialValues = [];
    $susulanValues = [];
    $finalValues = [];
    $matchedStudents = 0;
    $unmatchedStudents = 0;
    $inactiveStudents = 0;

    foreach ($resultRows as $row) {
        $matchType = (string) ($row['master_match_type'] ?? 'not_found');
        if ($matchType === 'active') {
            $matchedStudents++;
        } elseif ($matchType === 'inactive') {
            $inactiveStudents++;
        } else {
            $unmatchedStudents++;
        }

        if (!empty($row['recap_blocked'])) {
            continue;
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

        $effectiveClass = sanitizeGradeCell($row['kelas'] ?? '') !== '' ? $row['kelas'] : 'Tanpa kelas';
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

    $rowCount = count($resultRows);
    $participatingRowCount = $matchedStudents + $unmatchedStudents;

    return [
        'total_rows' => $participatingRowCount,
        'unique_nim_rows' => $rowCount,
        'source_rows' => (int) ($stats['source_rows'] ?? 0),
        'valid_rows' => (int) ($stats['valid_rows'] ?? 0),
        'invalid_rows' => (int) ($stats['invalid_rows'] ?? 0),
        'duplicate_nim_rows' => (int) ($stats['duplicate_nim_rows'] ?? 0),
        'category_rows' => [
            'normal' => (int) ($stats['category_rows']['normal'] ?? 0),
            'remedial' => (int) ($stats['category_rows']['remedial'] ?? 0),
            'susulan' => (int) ($stats['category_rows']['susulan'] ?? 0),
        ],
        'matched_students' => $matchedStudents,
        'unmatched_students' => $unmatchedStudents,
        'inactive_students' => $inactiveStudents,
        'avg_normal' => calculateAverage($normalValues),
        'avg_remedial' => calculateAverage($remedialValues),
        'avg_susulan' => calculateAverage($susulanValues),
        'avg_final' => calculateAverage($finalValues),
        'highest_final' => $finalValues ? max($finalValues) : null,
        'lowest_final' => $finalValues ? min($finalValues) : null,
        'class_distribution' => $classDistribution,
    ];
}

function determineMasterMatchType($masterStudent)
{
    if (!is_array($masterStudent)) {
        return 'not_found';
    }

    $status = normalizeStudentStatus($masterStudent['student_status'] ?? 'aktif');
    return isStudentStatusActive($status) ? 'active' : 'inactive';
}

function buildInactiveStudentRecapMessage($masterStudent)
{
    if (!is_array($masterStudent)) {
        return '';
    }

    $status = normalizeStudentStatus($masterStudent['student_status'] ?? 'aktif');
    return 'Mahasiswa berstatus ' . $status . ' di master data, jadi tidak ikut rekap sampai diaktifkan kembali.';
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


function getClassSubjectRecapList(PDO $db, $className, array $filters = [])
{
    $latestSql = getLatestRecapScopeSql();
    $params = [':class_name' => $className];
    $conditions = buildRecapFilterConditions('r', $filters, $params);
    $where = ['r.class_name = :class_name'];
    $where = array_merge($where, $conditions);

    $sql = 'SELECT r.subject_id,
                   COALESCE(ms.name, "Mata kuliah #" || r.subject_id) AS subject_name,
                   COALESCE(r.exam_type, "UAS") AS exam_type,
                   COALESCE(r.academic_year, "") AS academic_year,
                   COALESCE(r.term, "") AS term,
                   COUNT(DISTINCT r.nim) AS total_students,
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
              LEFT JOIN master_subjects ms ON ms.id = r.subject_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY r.subject_id, COALESCE(r.exam_type, "UAS"), COALESCE(r.academic_year, ""), COALESCE(r.term, "")
             ORDER BY subject_name ASC, academic_year ASC, term ASC, exam_type ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
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

        $rows[] = [
            'subject_id' => (int) ($row['subject_id'] ?? 0),
            'subject_name' => (string) ($row['subject_name'] ?? ''),
            'exam_type' => (string) ($row['exam_type'] ?? 'UAS'),
            'academic_year' => (string) ($row['academic_year'] ?? ''),
            'term' => (string) ($row['term'] ?? ''),
            'label' => (string) ($row['subject_name'] ?? '') . ($periodBits ? ' (' . implode(' - ', $periodBits) . ')' : ''),
            'total_students' => (int) ($row['total_students'] ?? 0),
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
    $followUpOverrides = getClassRecapFollowUpOverrides($db, $className, $filters);

    $byNim = [];
    $rosterSql = 'SELECT s.nim,
                         s.name AS student_name
                    FROM master_students s
                    INNER JOIN master_classes c ON c.id = s.class_id
                   WHERE c.name = :class_name
                     AND s.is_active = 1
                     AND LOWER(REPLACE(TRIM(COALESCE(s.student_status, "aktif")), " ", "_")) NOT IN ("cuti", "keluar", "mengundurkan_diri")
                   ORDER BY s.name ASC, s.nim ASC';
    $rosterStmt = $db->prepare($rosterSql);
    $rosterStmt->execute([':class_name' => $className]);

    foreach ($rosterStmt->fetchAll() as $row) {
        $nim = (string) ($row['nim'] ?? '');
        if ($nim === '') {
            continue;
        }

        $byNim[$nim] = [
            'nim' => $nim,
            'name' => (string) ($row['student_name'] ?? ''),
            'subjects' => [],
        ];
    }

    foreach ($scoreRows as $row) {
        $nim = (string) ($row['nim'] ?? '');
        if ($nim === '' || !isset($byNim[$nim])) {
            continue;
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

    foreach ($followUpOverrides as $nim => $subjectOverrides) {
        if (!isset($byNim[$nim])) {
            continue;
        }

        foreach ($subjectOverrides as $subjectKey => $override) {
            if (!isset($byNim[$nim]['subjects'][$subjectKey])) {
                $byNim[$nim]['subjects'][$subjectKey] = [
                    'bs' => null,
                    'score' => null,
                    'letter' => null,
                    'sp_score' => null,
                    'susulan_score' => null,
                ];
            }

            if (array_key_exists('sp_score', $override) && $override['sp_score'] !== null) {
                $byNim[$nim]['subjects'][$subjectKey]['sp_score'] = $override['sp_score'];
            }
            if (array_key_exists('susulan_score', $override) && $override['susulan_score'] !== null) {
                $byNim[$nim]['subjects'][$subjectKey]['susulan_score'] = $override['susulan_score'];
            }
        }
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

function getClassRecapFollowUpOverrides(PDO $db, $className, array $filters = [])
{
    $params = [':class_name' => $className];
    $conditions = [
        'c.name = :class_name',
        's.is_active = 1',
        'f.follow_up_score IS NOT NULL',
    ];
    $conditions = array_merge($conditions, buildRecapFilterConditions('f', $filters, $params));

    $sql = 'SELECT s.nim,
                   f.subject_id,
                   COALESCE(f.exam_type, "UAS") AS exam_type,
                   COALESCE(f.academic_year, "") AS academic_year,
                   COALESCE(f.term, "") AS term,
                   f.follow_up_type,
                   f.follow_up_score
              FROM follow_up_statuses f
              INNER JOIN master_students s ON s.id = f.student_id
              INNER JOIN master_classes c ON c.id = s.class_id
             WHERE ' . implode(' AND ', $conditions);

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $overrides = [];
    foreach ($stmt->fetchAll() as $row) {
        $nim = (string) ($row['nim'] ?? '');
        $subjectId = (int) ($row['subject_id'] ?? 0);
        if ($nim === '' || $subjectId <= 0 || $row['follow_up_score'] === null) {
            continue;
        }

        $subjectKey = $subjectId . ':' . (string) ($row['exam_type'] ?? 'UAS') . ':' . (string) ($row['academic_year'] ?? '') . ':' . (string) ($row['term'] ?? '');
        if (!isset($overrides[$nim][$subjectKey])) {
            $overrides[$nim][$subjectKey] = [
                'sp_score' => null,
                'susulan_score' => null,
            ];
        }

        $score = (float) $row['follow_up_score'];
        if (($row['follow_up_type'] ?? '') === 'remedial') {
            $overrides[$nim][$subjectKey]['sp_score'] = $score;
            continue;
        }

        if (($row['follow_up_type'] ?? '') === 'susulan') {
            $overrides[$nim][$subjectKey]['susulan_score'] = $score;
        }
    }

    return $overrides;
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
