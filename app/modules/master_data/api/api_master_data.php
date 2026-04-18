<?php
require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

require_once PROJECT_ROOT . '/app/shared/lib/database.php';

try {
    $db = getDatabaseConnection();
    $action = $_GET['action'] ?? '';

    if ($action === 'list_all') {
        $classes = getClassesWithCounts($db);
        $subjects = getSubjects($db);
        echo json_encode(['success' => true, 'classes' => $classes, 'subjects' => $subjects]);
        exit;
    }

    if ($action === 'list_classes') {
        echo json_encode(['success' => true, 'data' => getClassesWithCounts($db)]);
        exit;
    }

    if ($action === 'list_students_by_class') {
        $classId = intval($_GET['class_id'] ?? 0);
        $result = loadStudentsByClassId($db, $classId);
        echo json_encode([
            'success' => true,
            'class' => $result['class'],
            'data' => $result['students'],
        ]);
        exit;
    }

    if ($action === 'list_subjects') {
        echo json_encode(['success' => true, 'data' => getSubjects($db)]);
        exit;
    }

    if ($action === 'create_subject') {
        ensurePostRequest();
        $input = readJsonInput();
        $subject = getOrCreateSubject($db, $input['name'] ?? '');

        echo json_encode([
            'success' => true,
            'message' => 'Mata kuliah berhasil disimpan',
            'data' => $subject,
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

        echo json_encode([
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

        $insertStmt = $db->prepare('INSERT INTO master_students (nim, name, class_id, is_active) VALUES (:nim, :name, :class_id, 1)');
        $insertStmt->execute([
            ':nim' => $nim,
            ':name' => $name,
            ':class_id' => $class['id'],
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Mahasiswa berhasil ditambahkan',
            'data' => [
                'id' => (int) $db->lastInsertId(),
                'nim' => $nim,
                'nama' => $name,
                'class_id' => $class['id'],
                'class_name' => $class['name'],
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

            $updateStmt = $db->prepare('UPDATE master_students SET class_id = :target_class_id, updated_at = CURRENT_TIMESTAMP WHERE class_id = :source_class_id AND is_active = 1');
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

        echo json_encode([
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

        echo json_encode([
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

        echo json_encode([
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

        fgetcsv($handle, 10000, ',');
        $created = 0;
        while (($row = fgetcsv($handle, 10000, ',')) !== false) {
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

        echo json_encode([
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
                    $updateStmt = $db->prepare('UPDATE master_students SET name = :name, class_id = :class_id, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                    $updateStmt->execute([
                        ':name' => $student['nama'],
                        ':class_id' => $class['id'],
                        ':id' => $existingStudent['id'],
                    ]);
                    $updatedStudents++;
                } else {
                    $insertStmt = $db->prepare('INSERT INTO master_students (nim, name, class_id, is_active) VALUES (:nim, :name, :class_id, 1)');
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

        echo json_encode([
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

            fgetcsv($handle, 10000, ',');
            $fileCreated = 0;
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
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

        echo json_encode([
            'success' => true,
            'message' => 'Import mata kuliah selesai',
            'stats' => ['processed_files' => $processedFiles, 'created_subjects' => $created, 'skipped_duplicates' => $skippedDuplicates],
        ]);
        exit;
    }

    throw new RuntimeException('Invalid action');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
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

function getSubjects(PDO $db)
{
    $rows = $db->query('SELECT id, name FROM master_subjects ORDER BY name ASC')->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
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
