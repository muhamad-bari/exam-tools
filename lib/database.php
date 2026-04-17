<?php

function getDatabaseConnection()
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    $db = new PDO('sqlite:' . __DIR__ . '/../database.sqlite');
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

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

function getOrCreateClass(PDO $db, $className, $classCode = null)
{
    $className = sanitizeMasterName($className);
    $classCode = normalizeClassCode($classCode === null ? $className : $classCode);

    if ($className === '') {
        throw new InvalidArgumentException('Nama kelas wajib diisi');
    }

    if ($classCode === '') {
        $classCode = normalizeClassCode($className);
    }

    $findStmt = $db->prepare('SELECT id, code, name FROM master_classes WHERE code = :code OR LOWER(name) = LOWER(:name) LIMIT 1');
    $findStmt->execute([
        ':code' => $classCode,
        ':name' => $className,
    ]);
    $existingClass = $findStmt->fetch();

    if ($existingClass) {
        $updateStmt = $db->prepare('UPDATE master_classes SET code = :code, name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->execute([
            ':code' => $classCode,
            ':name' => $className,
            ':id' => $existingClass['id'],
        ]);

        return [
            'id' => (int) $existingClass['id'],
            'code' => $classCode,
            'name' => $className,
        ];
    }

    $insertStmt = $db->prepare('INSERT INTO master_classes (code, name) VALUES (:code, :name)');
    $insertStmt->execute([
        ':code' => $classCode,
        ':name' => $className,
    ]);

    return [
        'id' => (int) $db->lastInsertId(),
        'code' => $classCode,
        'name' => $className,
    ];
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
        if (!is_array($row) || count($row) < 4) {
            continue;
        }

        $nama = sanitizeMasterName($row[1] ?? '');
        $nim = sanitizeMasterName($row[2] ?? '');
        $kelas = sanitizeMasterName($row[3] ?? '');

        if ($nama === '' || $nim === '' || $kelas === '') {
            continue;
        }

        $students[] = [
            'nama' => $nama,
            'nim' => $nim,
            'tingkat' => $kelas,
        ];
    }

    fclose($handle);
    return $students;
}

function loadStudentsByClassId(PDO $db, $classId)
{
    $classId = intval($classId);
    if ($classId <= 0) {
        throw new RuntimeException('Class ID tidak valid');
    }

    $classStmt = $db->prepare('SELECT id, code, name FROM master_classes WHERE id = :id LIMIT 1');
    $classStmt->execute([':id' => $classId]);
    $class = $classStmt->fetch();

    if (!$class) {
        throw new RuntimeException('Kelas tidak ditemukan');
    }

    $studentsStmt = $db->prepare('SELECT id, nim, name, class_id FROM master_students WHERE class_id = :class_id AND is_active = 1 ORDER BY name ASC');
    $studentsStmt->execute([':class_id' => $classId]);

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
            'id' => (int) $class['id'],
            'code' => $class['code'],
            'name' => $class['name'],
        ],
        'students' => $students,
    ];
}
