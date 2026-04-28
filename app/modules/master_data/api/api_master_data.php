<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request(true);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once PROJECT_ROOT . '/app/shared/lib/database.php';
    $db = getDatabaseConnection();
    $action = $_GET['action'] ?? '';

    if ($action === 'list_all') {
        $classes = getClassesWithCounts($db);
        $subjects = getSubjects($db);
        $academicPeriods = getAcademicPeriods($db);
        sendJson(['success' => true, 'classes' => $classes, 'subjects' => $subjects, 'academic_periods' => $academicPeriods]);
        exit;
    }

    if ($action === 'list_classes') {
        sendJson(['success' => true, 'data' => getClassesWithCounts($db)]);
        exit;
    }

    if ($action === 'list_students_by_class') {
        $classId = intval($_GET['class_id'] ?? 0);
        $result = loadStudentsByClassId($db, $classId);
        sendJson([
            'success' => true,
            'class' => $result['class'],
            'data' => $result['students'],
        ]);
        exit;
    }

    if ($action === 'list_subjects') {
        sendJson(['success' => true, 'data' => getSubjects($db)]);
        exit;
    }

    if ($action === 'list_academic_periods') {
        sendJson(['success' => true, 'data' => getAcademicPeriods($db)]);
        exit;
    }

    if ($action === 'create_subject') {
        ensurePostRequest();
        $input = readJsonInput();
        $subject = getOrCreateSubject($db, $input['name'] ?? '');

        sendJson([
            'success' => true,
            'message' => 'Mata kuliah berhasil disimpan',
            'data' => $subject,
        ]);
        exit;
    }

    if ($action === 'create_academic_period') {
        ensurePostRequest();
        $input = readJsonInput();
        $academicPeriod = getOrCreateAcademicPeriod($db, $input['academic_year'] ?? '', $input['term'] ?? '');

        sendJson([
            'success' => true,
            'message' => 'Periode akademik berhasil disimpan',
            'data' => $academicPeriod,
        ]);
        exit;
    }

    if ($action === 'create_class') {
        ensurePostRequest();
        $input = readJsonInput();
        $name = sanitizeMasterName($input['name'] ?? '');
        $code = sanitizeMasterName($input['code'] ?? '');

        if ($name === '') {
            throw new RuntimeException('Nama kelas wajib diisi');
        }

        $class = getOrCreateClass($db, $name, $code === '' ? null : $code);

        sendJson([
            'success' => true,
            'message' => 'Kelas berhasil ditambahkan',
            'data' => $class,
        ]);
        exit;
    }

    if ($action === 'create_student') {
        ensurePostRequest();
        $input = readJsonInput();
        $classId = intval($input['class_id'] ?? 0);
        $name = sanitizeMasterName($input['name'] ?? '');
        $nim = sanitizeMasterName($input['nim'] ?? '');
        $studentStatus = normalizeStudentStatus($input['student_status'] ?? 'aktif');

        if ($classId <= 0) {
            throw new RuntimeException('Class ID tidak valid');
        }

        if ($name === '' || $nim === '') {
            throw new RuntimeException('Nama dan NIM mahasiswa wajib diisi');
        }

        $class = getClassById($db, $classId);
        $findStmt = $db->prepare('SELECT id FROM master_students WHERE nim = :nim LIMIT 1');
        $findStmt->execute([':nim' => $nim]);
        if ($findStmt->fetch()) {
            throw new RuntimeException('NIM sudah terdaftar di master mahasiswa');
        }

        $insertStmt = $db->prepare('INSERT INTO master_students (nim, name, class_id, is_active, student_status) VALUES (:nim, :name, :class_id, :is_active, :student_status)');
        $insertStmt->execute([
            ':nim' => $nim,
            ':name' => $name,
            ':class_id' => $class['id'],
            ':is_active' => isStudentStatusActive($studentStatus) ? 1 : 0,
            ':student_status' => $studentStatus,
        ]);

        sendJson([
            'success' => true,
            'message' => 'Mahasiswa berhasil ditambahkan',
            'data' => [
                'id' => (int) $db->lastInsertId(),
                'nim' => $nim,
                'nama' => $name,
                'class_id' => $class['id'],
                'class_name' => $class['name'],
                'student_status' => $studentStatus,
            ],
        ]);
        exit;
    }

    if ($action === 'promote_class') {
        ensurePostRequest();
        $input = readJsonInput();
        $classId = intval($input['class_id'] ?? 0);
        $sourceClass = getClassById($db, $classId);
        $targetClassData = calculateNextStructuredClass($sourceClass);

        $db->beginTransaction();
        try {
            $targetClass = getOrCreateClass($db, $targetClassData['name']);

            $updateStmt = $db->prepare("UPDATE master_students SET class_id = :target_class_id, updated_at = CURRENT_TIMESTAMP WHERE class_id = :source_class_id AND student_status = 'aktif'");
            $updateStmt->execute([
                ':target_class_id' => $targetClass['id'],
                ':source_class_id' => $sourceClass['id'],
            ]);

            $movedStudents = $updateStmt->rowCount();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        sendJson([
            'success' => true,
            'message' => 'Naik kelas selesai',
            'data' => [
                'source_class' => $sourceClass,
                'target_class' => $targetClass,
                'moved_students' => $movedStudents,
            ],
        ]);
        exit;
    }

    if ($action === 'promote_all_classes') {
        ensurePostRequest();
        $classes = getClassesWithCounts($db);
        $eligibleClasses = [];
        $studentIdsBySourceClass = [];

        foreach ($classes as $class) {
            if (!empty($class['next_class_name'])) {
                $eligibleClasses[] = $class;
                $studentIdsBySourceClass[(int) $class['id']] = getPromotableStudentIdsByClass($db, $class['id']);
            }
        }

        if (!$eligibleClasses) {
            throw new RuntimeException('Tidak ada kelas dengan format terstruktur yang bisa dipromosikan');
        }

        $db->beginTransaction();
        try {
            $movedStudents = 0;
            $processedClasses = 0;
            $createdTargetClasses = 0;

            foreach ($eligibleClasses as $sourceClass) {
                $targetClassData = calculateNextStructuredClass($sourceClass);
                $existingTargetClass = findClassByCodeOrName($db, $targetClassData['name'], $targetClassData['name']);
                $targetClass = getOrCreateClass($db, $targetClassData['name']);
                if ($existingTargetClass === null) {
                    $createdTargetClasses++;
                }

                $sourceStudentIds = $studentIdsBySourceClass[(int) $sourceClass['id']] ?? [];
                $movedStudents += moveStudentsToClass($db, $sourceStudentIds, $targetClass['id']);
                $processedClasses++;
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        sendJson([
            'success' => true,
            'message' => 'Naik kelas semua selesai',
            'data' => [
                'processed_classes' => $processedClasses,
                'moved_students' => $movedStudents,
                'created_target_classes' => $createdTargetClasses,
            ],
        ]);
        exit;
    }

    if ($action === 'update_student') {
        ensurePostRequest();
        $input = readJsonInput();
        $studentId = intval($input['student_id'] ?? 0);
        $classId = intval($input['class_id'] ?? 0);
        $name = sanitizeMasterName($input['name'] ?? '');
        $nim = sanitizeMasterName($input['nim'] ?? '');
        $studentStatus = normalizeStudentStatus($input['student_status'] ?? 'aktif');

        if ($studentId <= 0) {
            throw new RuntimeException('ID mahasiswa tidak valid');
        }

        if ($classId <= 0) {
            throw new RuntimeException('Class ID tidak valid');
        }

        if ($name === '' || $nim === '') {
            throw new RuntimeException('Nama dan NIM mahasiswa wajib diisi');
        }

        $student = getStudentById($db, $studentId);
        $class = getClassById($db, $classId);

        $duplicateStmt = $db->prepare('SELECT id FROM master_students WHERE nim = :nim AND id != :id LIMIT 1');
        $duplicateStmt->execute([
            ':nim' => $nim,
            ':id' => $studentId,
        ]);
        if ($duplicateStmt->fetch()) {
            throw new RuntimeException('NIM sudah terdaftar di master mahasiswa');
        }

        $updateStmt = $db->prepare('UPDATE master_students SET nim = :nim, name = :name, class_id = :class_id, student_status = :student_status, is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->execute([
            ':nim' => $nim,
            ':name' => $name,
            ':class_id' => $class['id'],
            ':student_status' => $studentStatus,
            ':is_active' => isStudentStatusActive($studentStatus) ? 1 : 0,
            ':id' => $student['id'],
        ]);

        sendJson([
            'success' => true,
            'message' => 'Mahasiswa berhasil diperbarui',
            'data' => [
                'id' => $student['id'],
                'nim' => $nim,
                'nama' => $name,
                'class_id' => $class['id'],
                'class_name' => $class['name'],
                'student_status' => $studentStatus,
            ],
        ]);
        exit;
    }

    if ($action === 'delete_student') {
        ensurePostRequest();
        $input = readJsonInput();
        $studentId = intval($input['student_id'] ?? 0);

        if ($studentId <= 0) {
            throw new RuntimeException('ID mahasiswa tidak valid');
        }

        $student = getStudentById($db, $studentId);
        $deleteStmt = $db->prepare('DELETE FROM master_students WHERE id = :id');
        $deleteStmt->execute([':id' => $student['id']]);

        sendJson([
            'success' => true,
            'message' => 'Mahasiswa berhasil dihapus',
            'data' => $student,
        ]);
        exit;
    }

    if ($action === 'update_class') {
        ensurePostRequest();
        $input = readJsonInput();
        $classId = intval($input['class_id'] ?? 0);
        $name = sanitizeMasterName($input['name'] ?? '');
        $code = sanitizeMasterName($input['code'] ?? '');

        if ($classId <= 0) {
            throw new RuntimeException('Class ID tidak valid');
        }

        if ($name === '') {
            throw new RuntimeException('Nama kelas wajib diisi');
        }

        $class = updateClass($db, $classId, $name, $code);

        sendJson([
            'success' => true,
            'message' => 'Kelas berhasil diperbarui',
            'data' => $class,
        ]);
        exit;
    }

    if ($action === 'delete_class') {
        ensurePostRequest();
        $input = readJsonInput();
        $classId = intval($input['class_id'] ?? 0);

        if ($classId <= 0) {
            throw new RuntimeException('Class ID tidak valid');
        }

        $class = getClassById($db, $classId);
        $usageStmt = $db->prepare('SELECT COUNT(*) FROM master_students WHERE class_id = :class_id');
        $usageStmt->execute([':class_id' => $classId]);
        $studentCount = (int) $usageStmt->fetchColumn();

        $db->beginTransaction();
        try {
            $deleteStudentsStmt = $db->prepare('DELETE FROM master_students WHERE class_id = :class_id');
            $deleteStudentsStmt->execute([':class_id' => $classId]);

            $deleteStmt = $db->prepare('DELETE FROM master_classes WHERE id = :id');
            $deleteStmt->execute([':id' => $classId]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        sendJson([
            'success' => true,
            'message' => 'Kelas berhasil dihapus',
            'data' => $class,
            'deleted_students' => $studentCount,
        ]);
        exit;
    }

    if ($action === 'import_classes') {
        ensurePostRequest();
        if (!isset($_FILES['classes_csv']) || $_FILES['classes_csv']['error'] !== 0) {
            throw new RuntimeException('File CSV kelas wajib diunggah');
        }

        $handle = fopen($_FILES['classes_csv']['tmp_name'], 'r');
        if ($handle === false) {
            throw new RuntimeException('Gagal membaca file CSV kelas');
        }

        fgetcsv($handle, 10000, ',', '"', '\\');
        $created = 0;
        while (($row = fgetcsv($handle, 10000, ',', '"', '\\')) !== false) {
            $classCode = sanitizeMasterName($row[0] ?? '');
            $className = sanitizeMasterName($row[1] ?? $row[0] ?? '');
            if ($className === '') {
                continue;
            }
            $before = findClassByCodeOrName($db, $classCode, $className);
            getOrCreateClass($db, $className, $classCode);
            if ($before === null) {
                $created++;
            }
        }
        fclose($handle);

        sendJson([
            'success' => true,
            'message' => 'Import kelas selesai',
            'stats' => ['created_classes' => $created],
        ]);
        exit;
    }

    if ($action === 'import_students') {
        ensurePostRequest();
        $uploadedFiles = normalizeUploadedFiles($_FILES['students_csv'] ?? null);
        if (!$uploadedFiles) {
            throw new RuntimeException('File CSV mahasiswa wajib diunggah');
        }

        $classId = intval($_POST['class_id'] ?? 0);
        if ($classId <= 0) {
            throw new RuntimeException('Pilih kelas tujuan import mahasiswa terlebih dahulu');
        }

        $class = getClassById($db, $classId);

        $createdStudents = 0;
        $updatedStudents = 0;
        $processedFiles = 0;
        $overwrittenRows = 0;
        $studentsByNim = [];

        foreach ($uploadedFiles as $uploadedFile) {
            $students = parseStudentCsvFile($uploadedFile['tmp_name']);
            if (!$students) {
                continue;
            }

            $processedFiles++;
            foreach ($students as $student) {
                if (isset($studentsByNim[$student['nim']])) {
                    $overwrittenRows++;
                }
                $studentsByNim[$student['nim']] = $student;
            }
        }

        if ($processedFiles === 0 || !$studentsByNim) {
            throw new RuntimeException('Tidak ada file CSV mahasiswa yang valid');
        }

        $db->beginTransaction();
        try {
            foreach ($studentsByNim as $student) {
                $findStmt = $db->prepare('SELECT id FROM master_students WHERE nim = :nim LIMIT 1');
                $findStmt->execute([':nim' => $student['nim']]);
                $existingStudent = $findStmt->fetch();

                if ($existingStudent) {
                    $updateStmt = $db->prepare("UPDATE master_students SET name = :name, class_id = :class_id, student_status = 'aktif', is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $updateStmt->execute([
                        ':name' => $student['nama'],
                        ':class_id' => $class['id'],
                        ':id' => $existingStudent['id'],
                    ]);
                    $updatedStudents++;
                } else {
                    $insertStmt = $db->prepare("INSERT INTO master_students (nim, name, class_id, is_active, student_status) VALUES (:nim, :name, :class_id, 1, 'aktif')");
                    $insertStmt->execute([
                        ':nim' => $student['nim'],
                        ':name' => $student['nama'],
                        ':class_id' => $class['id'],
                    ]);
                    $createdStudents++;
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        sendJson([
            'success' => true,
            'message' => 'Import mahasiswa selesai',
            'stats' => [
                'class' => $class,
                'processed_files' => $processedFiles,
                'created_students' => $createdStudents,
                'updated_students' => $updatedStudents,
                'overwritten_rows' => $overwrittenRows,
            ],
        ]);
        exit;
    }

    if ($action === 'import_subjects') {
        ensurePostRequest();
        $created = 0;
        $processedFiles = 0;
        $skippedDuplicates = 0;
        $uploadedFiles = normalizeUploadedFiles($_FILES['subjects_csv'] ?? null);
        if (!$uploadedFiles) {
            throw new RuntimeException('File CSV mata kuliah wajib diunggah');
        }

        $seenSubjects = [];

        foreach ($uploadedFiles as $uploadedFile) {
            $handle = fopen($uploadedFile['tmp_name'], 'r');
            if ($handle === false) {
                continue;
            }

            fgetcsv($handle, 10000, ',', '"', '\\');
            $fileCreated = 0;
            while (($row = fgetcsv($handle, 10000, ',', '"', '\\')) !== false) {
                $name = sanitizeMasterName($row[0] ?? $row[1] ?? '');
                if ($name === '') {
                    continue;
                }

                $normalizedName = mb_strtolower($name, 'UTF-8');
                if (isset($seenSubjects[$normalizedName]) || findSubjectByName($db, $name)) {
                    $skippedDuplicates++;
                    continue;
                }

                getOrCreateSubject($db, $name);
                $seenSubjects[$normalizedName] = true;
                $created++;
                $fileCreated++;
            }
            fclose($handle);
            if ($fileCreated > 0 || is_uploaded_file($uploadedFile['tmp_name']) || file_exists($uploadedFile['tmp_name'])) {
                $processedFiles++;
            }
        }

        if ($processedFiles === 0) {
            throw new RuntimeException('Tidak ada file CSV mata kuliah yang valid');
        }

        sendJson([
            'success' => true,
            'message' => 'Import mata kuliah selesai',
            'stats' => ['processed_files' => $processedFiles, 'created_subjects' => $created, 'skipped_duplicates' => $skippedDuplicates],
        ]);
        exit;
    }

    throw new RuntimeException('Invalid action');
} catch (Throwable $e) {
    http_response_code(400);
    sendJson([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} finally {
    restore_error_handler();
}

function getClassesWithCounts(PDO $db)
{
    $stmt = $db->query('SELECT c.id, c.code, c.name, c.program_name, c.tingkat_number, c.semester_number, COUNT(s.id) AS student_count FROM master_classes c LEFT JOIN master_students s ON s.class_id = c.id AND s.is_active = 1 GROUP BY c.id, c.code, c.name, c.program_name, c.tingkat_number, c.semester_number ORDER BY c.name ASC');
    $rows = [];

    foreach ($stmt->fetchAll() as $row) {
        $studentCount = (int) ($row['student_count'] ?? 0);
        $row = normalizeClassRow($row);
        $row['student_count'] = $studentCount;
        $rows[] = $row;
    }

    return $rows;
}

function sendJson(array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new RuntimeException('Gagal membuat response JSON: ' . json_last_error_msg());
    }

    echo $json;
}

function getSubjects(PDO $db)
{
    $rows = $db->query('SELECT id, name FROM master_subjects ORDER BY name ASC')->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['name'] = sanitizeMasterName($row['name'] ?? '');
    }
    unset($row);
    return $rows;
}

function findClassByCodeOrName(PDO $db, $code, $name)
{
    $code = normalizeClassCode($code);
    $name = sanitizeMasterName($name);

    if ($code === '' && $name === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT id, code, name, program_name, tingkat_number, semester_number FROM master_classes WHERE code = :code OR LOWER(name) = LOWER(:name) LIMIT 1');
    $stmt->execute([
        ':code' => $code,
        ':name' => $name,
    ]);

    $row = $stmt->fetch();
    return $row ? normalizeClassRow($row) : null;
}

function normalizeUploadedFiles($fileField)
{
    if (!$fileField || !isset($fileField['name'])) {
        return [];
    }

    if (!is_array($fileField['name'])) {
        if (($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [];
        }

        return [[
            'name' => $fileField['name'],
            'tmp_name' => $fileField['tmp_name'],
            'error' => $fileField['error'],
            'size' => $fileField['size'] ?? 0,
        ]];
    }

    $files = [];
    foreach ($fileField['name'] as $index => $name) {
        if (($fileField['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $files[] = [
            'name' => $name,
            'tmp_name' => $fileField['tmp_name'][$index] ?? '',
            'error' => $fileField['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileField['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function ensurePostRequest()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method');
    }
}

function readJsonInput()
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function updateClass(PDO $db, $classId, $name, $code = '')
{
    $currentClass = getClassById($db, $classId);
    $name = sanitizeMasterName($name);
    $code = normalizeClassCode($code === '' ? $name : $code);

    $duplicateStmt = $db->prepare('SELECT id FROM master_classes WHERE id != :id AND (code = :code OR LOWER(name) = LOWER(:name)) LIMIT 1');
    $duplicateStmt->execute([
        ':id' => $classId,
        ':code' => $code,
        ':name' => $name,
    ]);

    if ($duplicateStmt->fetch()) {
        throw new RuntimeException('Nama atau kode kelas sudah digunakan kelas lain');
    }

    $parsed = parseStructuredClassName($name);
    $programName = $parsed['program_name'] ?? null;
    $tingkatNumber = $parsed['tingkat_number'] ?? null;
    $semesterNumber = $parsed['semester_number'] ?? null;

    $updateStmt = $db->prepare('UPDATE master_classes SET code = :code, name = :name, program_name = :program_name, tingkat_number = :tingkat_number, semester_number = :semester_number, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $updateStmt->execute([
        ':code' => $code,
        ':name' => $name,
        ':program_name' => $programName,
        ':tingkat_number' => $tingkatNumber,
        ':semester_number' => $semesterNumber,
        ':id' => $classId,
    ]);

    $updatedClass = getClassById($db, $classId);
    $updatedClass['student_count'] = $currentClass['student_count'] ?? 0;

    return $updatedClass;
}

function getStudentById(PDO $db, $studentId)
{
    $studentId = intval($studentId);
    if ($studentId <= 0) {
        throw new RuntimeException('ID mahasiswa tidak valid');
    }

    $stmt = $db->prepare('SELECT id, nim, name, class_id, student_status, is_active FROM master_students WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new RuntimeException('Mahasiswa tidak ditemukan');
    }

    $normalizedStudent = normalizeStudentRow($student);
    $class = getClassById($db, $normalizedStudent['class_id']);
    $normalizedStudent['class_name'] = $class['name'];

    return $normalizedStudent;
}

function getPromotableStudentIdsByClass(PDO $db, $classId)
{
    $stmt = $db->prepare("SELECT id FROM master_students WHERE class_id = :class_id AND student_status = 'aktif' ORDER BY id ASC");
    $stmt->execute([':class_id' => intval($classId)]);

    return array_map(static function ($row) {
        return (int) $row['id'];
    }, $stmt->fetchAll());
}

function moveStudentsToClass(PDO $db, array $studentIds, $targetClassId)
{
    if (!$studentIds) {
        return 0;
    }

    $placeholders = [];
    $params = [':target_class_id' => intval($targetClassId)];

    foreach (array_values($studentIds) as $index => $studentId) {
        $placeholder = ':student_id_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = (int) $studentId;
    }

    $updateStmt = $db->prepare('UPDATE master_students SET class_id = :target_class_id, updated_at = CURRENT_TIMESTAMP WHERE id IN (' . implode(', ', $placeholders) . ')');
    $updateStmt->execute($params);

    return $updateStmt->rowCount();
}
