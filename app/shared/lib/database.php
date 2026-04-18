<?php

require_once __DIR__ . '/../../bootstrap.php';

function getDatabaseConnection()
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    $db = new PDO('sqlite:' . PROJECT_ROOT . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initializeDatabaseSchema($db);

    return $db;
}

function initializeDatabaseSchema(PDO $db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        data TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS folders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumnExists($db, 'sessions', 'folder_id', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'folders', 'parent_id', 'INTEGER DEFAULT NULL');

    $db->exec("CREATE TABLE IF NOT EXISTS master_classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        program_name TEXT DEFAULT NULL,
        tingkat_number INTEGER DEFAULT NULL,
        semester_number INTEGER DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumnExists($db, 'master_classes', 'program_name', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'master_classes', 'tingkat_number', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'master_classes', 'semester_number', 'INTEGER DEFAULT NULL');

    $db->exec("CREATE TABLE IF NOT EXISTS master_students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nim TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        class_id INTEGER NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES master_classes(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS master_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec('CREATE INDEX IF NOT EXISTS idx_sessions_folder_id ON sessions(folder_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_folders_parent_id ON folders(parent_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_master_students_class_id ON master_students(class_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_master_students_active_class ON master_students(is_active, class_id)');

    syncStructuredClassMetadata($db);
}

function ensureColumnExists(PDO $db, $tableName, $columnName, $definition)
{
    $columns = $db->query("PRAGMA table_info($tableName)")->fetchAll();

    foreach ($columns as $column) {
        if (($column['name'] ?? '') === $columnName) {
            return;
        }
    }

    $db->exec("ALTER TABLE $tableName ADD COLUMN $columnName $definition");
}

function normalizeRootId($value)
{
    if ($value === null || $value === '' || $value === 0 || $value === '0' || $value === 'root') {
        return null;
    }

    return intval($value);
}

function sanitizeMasterName($value)
{
    return preg_replace('/\s+/', ' ', trim((string) $value));
}

function normalizeClassCode($value)
{
    $value = preg_replace('/\s+/', ' ', sanitizeMasterName($value));
    return strtoupper($value);
}

function convertRomanToInt($value)
{
    $value = strtoupper(sanitizeMasterName($value));
    if ($value === '') {
        return null;
    }

    $romanMap = [
        'M' => 1000,
        'D' => 500,
        'C' => 100,
        'L' => 50,
        'X' => 10,
        'V' => 5,
        'I' => 1,
    ];

    $total = 0;
    $previous = 0;
    $length = strlen($value);

    for ($index = $length - 1; $index >= 0; $index--) {
        $char = $value[$index];
        if (!isset($romanMap[$char])) {
            return null;
        }

        $current = $romanMap[$char];
        if ($current < $previous) {
            $total -= $current;
        } else {
            $total += $current;
            $previous = $current;
        }
    }

    return $total > 0 ? $total : null;
}

function convertIntToRoman($value)
{
    $value = intval($value);
    if ($value <= 0) {
        throw new InvalidArgumentException('Nilai romawi tidak valid');
    }

    $romanMap = [
        1000 => 'M',
        900 => 'CM',
        500 => 'D',
        400 => 'CD',
        100 => 'C',
        90 => 'XC',
        50 => 'L',
        40 => 'XL',
        10 => 'X',
        9 => 'IX',
        5 => 'V',
        4 => 'IV',
        1 => 'I',
    ];

    $result = '';
    foreach ($romanMap as $number => $roman) {
        while ($value >= $number) {
            $result .= $roman;
            $value -= $number;
        }
    }

    return $result;
}

function buildStructuredClassName($programName, $tingkatNumber, $semesterNumber)
{
    $programName = sanitizeMasterName($programName);
    $tingkatNumber = intval($tingkatNumber);
    $semesterNumber = intval($semesterNumber);

    if ($programName === '' || $tingkatNumber <= 0 || $semesterNumber <= 0) {
        throw new InvalidArgumentException('Format kelas terstruktur tidak valid');
    }

    return $programName . ' TK ' . convertIntToRoman($tingkatNumber) . '/' . convertIntToRoman($semesterNumber);
}

function parseStructuredClassName($className)
{
    $className = sanitizeMasterName($className);
    if ($className === '') {
        return null;
    }

    if (!preg_match('/^(.*?)\s+TK\s+([IVXLCDM]+)\s*\/\s*([IVXLCDM]+)$/iu', $className, $matches)) {
        return null;
    }

    $programName = sanitizeMasterName($matches[1]);
    $tingkatNumber = convertRomanToInt($matches[2]);
    $semesterNumber = convertRomanToInt($matches[3]);

    if ($programName === '' || $tingkatNumber === null || $semesterNumber === null) {
        return null;
    }

    return [
        'program_name' => $programName,
        'tingkat_number' => $tingkatNumber,
        'semester_number' => $semesterNumber,
        'name' => buildStructuredClassName($programName, $tingkatNumber, $semesterNumber),
    ];
}

function calculateNextStructuredClass($class)
{
    $programName = sanitizeMasterName($class['program_name'] ?? '');
    $tingkatNumber = intval($class['tingkat_number'] ?? 0);
    $semesterNumber = intval($class['semester_number'] ?? 0);

    if ($programName === '' || $tingkatNumber <= 0 || $semesterNumber <= 0) {
        throw new RuntimeException('Kelas belum memiliki metadata tingkat dan semester yang valid');
    }

    $nextSemester = $semesterNumber + 1;
    $nextTingkat = $semesterNumber % 2 === 0 ? $tingkatNumber + 1 : $tingkatNumber;

    return [
        'program_name' => $programName,
        'tingkat_number' => $nextTingkat,
        'semester_number' => $nextSemester,
        'name' => buildStructuredClassName($programName, $nextTingkat, $nextSemester),
    ];
}

function normalizeClassRow($row)
{
    $parsed = parseStructuredClassName($row['name'] ?? '');
    $programName = sanitizeMasterName($row['program_name'] ?? '');
    $tingkatNumber = intval($row['tingkat_number'] ?? 0);
    $semesterNumber = intval($row['semester_number'] ?? 0);

    if (($programName === '' || $tingkatNumber <= 0 || $semesterNumber <= 0) && $parsed !== null) {
        $programName = $parsed['program_name'];
        $tingkatNumber = $parsed['tingkat_number'];
        $semesterNumber = $parsed['semester_number'];
    }

    $normalized = [
        'id' => isset($row['id']) ? (int) $row['id'] : 0,
        'code' => $row['code'] ?? '',
        'name' => $row['name'] ?? '',
        'program_name' => $programName !== '' ? $programName : null,
        'tingkat_number' => $tingkatNumber > 0 ? $tingkatNumber : null,
        'semester_number' => $semesterNumber > 0 ? $semesterNumber : null,
    ];

    if ($normalized['program_name'] !== null && $normalized['tingkat_number'] !== null && $normalized['semester_number'] !== null) {
        $next = calculateNextStructuredClass($normalized);
        $normalized['next_class_name'] = $next['name'];
    } else {
        $normalized['next_class_name'] = null;
    }

    return $normalized;
}

function syncStructuredClassMetadata(PDO $db)
{
    $rows = $db->query('SELECT id, name, program_name, tingkat_number, semester_number FROM master_classes')->fetchAll();
    if (!$rows) {
        return;
    }

    $updateStmt = $db->prepare('UPDATE master_classes SET program_name = :program_name, tingkat_number = :tingkat_number, semester_number = :semester_number, updated_at = CURRENT_TIMESTAMP WHERE id = :id');

    foreach ($rows as $row) {
        $parsed = parseStructuredClassName($row['name'] ?? '');
        if ($parsed === null) {
            continue;
        }

        $currentProgram = sanitizeMasterName($row['program_name'] ?? '');
        $currentTingkat = intval($row['tingkat_number'] ?? 0);
        $currentSemester = intval($row['semester_number'] ?? 0);

        if ($currentProgram === $parsed['program_name']
            && $currentTingkat === $parsed['tingkat_number']
            && $currentSemester === $parsed['semester_number']) {
            continue;
        }

        $updateStmt->execute([
            ':program_name' => $parsed['program_name'],
            ':tingkat_number' => $parsed['tingkat_number'],
            ':semester_number' => $parsed['semester_number'],
            ':id' => $row['id'],
        ]);
    }
}

function getClassById(PDO $db, $classId)
{
    $classId = intval($classId);
    if ($classId <= 0) {
        throw new RuntimeException('Class ID tidak valid');
    }

    $stmt = $db->prepare('SELECT id, code, name, program_name, tingkat_number, semester_number FROM master_classes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $classId]);
    $class = $stmt->fetch();

    if (!$class) {
        throw new RuntimeException('Kelas tidak ditemukan');
    }

    return normalizeClassRow($class);
}

function getOrCreateClass(PDO $db, $className, $classCode = null)
{
    $className = sanitizeMasterName($className);
    $structuredClass = parseStructuredClassName($className);
    $classCode = normalizeClassCode($classCode === null ? $className : $classCode);

    if ($className === '') {
        throw new InvalidArgumentException('Nama kelas wajib diisi');
    }

    if ($classCode === '') {
        $classCode = normalizeClassCode($className);
    }

    $programName = $structuredClass['program_name'] ?? null;
    $tingkatNumber = $structuredClass['tingkat_number'] ?? null;
    $semesterNumber = $structuredClass['semester_number'] ?? null;

    $findStmt = $db->prepare('SELECT id, code, name, program_name, tingkat_number, semester_number FROM master_classes WHERE code = :code OR LOWER(name) = LOWER(:name) LIMIT 1');
    $findStmt->execute([
        ':code' => $classCode,
        ':name' => $className,
    ]);
    $existingClass = $findStmt->fetch();

    if ($existingClass) {
        $updateStmt = $db->prepare('UPDATE master_classes SET code = :code, name = :name, program_name = :program_name, tingkat_number = :tingkat_number, semester_number = :semester_number, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->execute([
            ':code' => $classCode,
            ':name' => $className,
            ':program_name' => $programName,
            ':tingkat_number' => $tingkatNumber,
            ':semester_number' => $semesterNumber,
            ':id' => $existingClass['id'],
        ]);

        return normalizeClassRow([
            'id' => (int) $existingClass['id'],
            'code' => $classCode,
            'name' => $className,
            'program_name' => $programName,
            'tingkat_number' => $tingkatNumber,
            'semester_number' => $semesterNumber,
        ]);
    }

    $insertStmt = $db->prepare('INSERT INTO master_classes (code, name, program_name, tingkat_number, semester_number) VALUES (:code, :name, :program_name, :tingkat_number, :semester_number)');
    $insertStmt->execute([
        ':code' => $classCode,
        ':name' => $className,
        ':program_name' => $programName,
        ':tingkat_number' => $tingkatNumber,
        ':semester_number' => $semesterNumber,
    ]);

    return normalizeClassRow([
        'id' => (int) $db->lastInsertId(),
        'code' => $classCode,
        'name' => $className,
        'program_name' => $programName,
        'tingkat_number' => $tingkatNumber,
        'semester_number' => $semesterNumber,
    ]);
}

function findSubjectByName(PDO $db, $subjectName)
{
    $subjectName = sanitizeMasterName($subjectName);

    if ($subjectName === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT id, name FROM master_subjects WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $stmt->execute([':name' => $subjectName]);
    $subject = $stmt->fetch();

    if (!$subject) {
        return null;
    }

    return [
        'id' => (int) $subject['id'],
        'name' => $subject['name'],
    ];
}

function getOrCreateSubject(PDO $db, $subjectName)
{
    $subjectName = sanitizeMasterName($subjectName);

    if ($subjectName === '') {
        throw new InvalidArgumentException('Nama mata kuliah wajib diisi');
    }

    $existingSubject = findSubjectByName($db, $subjectName);
    if ($existingSubject) {
        return $existingSubject;
    }

    $stmt = $db->prepare('INSERT INTO master_subjects (name) VALUES (:name)');
    $stmt->execute([':name' => $subjectName]);

    return [
        'id' => (int) $db->lastInsertId(),
        'name' => $subjectName,
    ];
}

function parseStudentCsvFile($filePath)
{
    if (!file_exists($filePath)) {
        throw new RuntimeException('File CSV tidak ditemukan');
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Gagal membuka file CSV');
    }

    $students = [];
    fgetcsv($handle, 10000, ',');

    while (($row = fgetcsv($handle, 10000, ',')) !== false) {
        if (!is_array($row) || count($row) < 3) {
            continue;
        }

        $nama = sanitizeMasterName($row[1] ?? '');
        $nim = sanitizeMasterName($row[2] ?? '');

        if ($nama === '' || $nim === '') {
            continue;
        }

        $students[] = [
            'nama' => $nama,
            'nim' => $nim,
        ];
    }

    fclose($handle);
    return $students;
}

function loadStudentsByClassId(PDO $db, $classId)
{
    $class = getClassById($db, $classId);

    $studentsStmt = $db->prepare('SELECT id, nim, name, class_id FROM master_students WHERE class_id = :class_id AND is_active = 1 ORDER BY name ASC');
    $studentsStmt->execute([':class_id' => $class['id']]);

    $students = [];
    foreach ($studentsStmt->fetchAll() as $row) {
        $students[] = [
            'id' => (int) $row['id'],
            'nim' => $row['nim'],
            'nama' => $row['name'],
            'tingkat' => $class['name'],
            'class_id' => (int) $row['class_id'],
        ];
    }

    return [
        'class' => [
            'id' => $class['id'],
            'code' => $class['code'],
            'name' => $class['name'],
            'program_name' => $class['program_name'],
            'tingkat_number' => $class['tingkat_number'],
            'semester_number' => $class['semester_number'],
            'next_class_name' => $class['next_class_name'],
        ],
        'students' => $students,
    ];
}
