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
        student_status TEXT NOT NULL DEFAULT 'aktif',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES master_classes(id)
    )");

    ensureColumnExists($db, 'master_students', 'student_status', "TEXT NOT NULL DEFAULT 'aktif'");

    $db->exec("CREATE TABLE IF NOT EXISTS master_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS master_academic_periods (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        academic_year TEXT NOT NULL,
        term TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (academic_year, term)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS grade_recap_imports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_name TEXT NOT NULL,
        sheet_name TEXT NOT NULL,
        total_rows INTEGER NOT NULL DEFAULT 0,
        valid_rows INTEGER NOT NULL DEFAULT 0,
        invalid_rows INTEGER NOT NULL DEFAULT 0,
        duplicate_nim_rows INTEGER NOT NULL DEFAULT 0,
        matched_students INTEGER NOT NULL DEFAULT 0,
        unmatched_students INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    ensureColumnExists($db, 'grade_recap_imports', 'file_name', 'TEXT NOT NULL DEFAULT "nilai.xlsx"');
    ensureColumnExists($db, 'grade_recap_imports', 'sheet_name', 'TEXT NOT NULL DEFAULT "Worksheet"');
    ensureColumnExists($db, 'grade_recap_imports', 'total_rows', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'valid_rows', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'invalid_rows', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'duplicate_nim_rows', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'matched_students', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'unmatched_students', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'inactive_students', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_imports', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
    ensureColumnExists($db, 'grade_recap_imports', 'subject_id', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_imports', 'exam_type', 'TEXT NOT NULL DEFAULT "UAS"');
    ensureColumnExists($db, 'grade_recap_imports', 'academic_year', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_imports', 'term', 'TEXT DEFAULT NULL');

    repairMasterSubjectEncoding($db);

    $db->exec("CREATE TABLE IF NOT EXISTS grade_recap_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        import_id INTEGER NOT NULL,
        nim TEXT NOT NULL,
        source_name TEXT DEFAULT NULL,
        student_id INTEGER DEFAULT NULL,
        student_name TEXT DEFAULT NULL,
        class_name TEXT DEFAULT NULL,
        normal_bs TEXT DEFAULT NULL,
        normal_score REAL DEFAULT NULL,
        normal_letter TEXT DEFAULT NULL,
        remedial_bs TEXT DEFAULT NULL,
        remedial_score REAL DEFAULT NULL,
        remedial_letter TEXT DEFAULT NULL,
        susulan_bs TEXT DEFAULT NULL,
        susulan_score REAL DEFAULT NULL,
        susulan_letter TEXT DEFAULT NULL,
        final_score REAL DEFAULT NULL,
        final_letter TEXT DEFAULT NULL,
        duplicate_nim_count INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (import_id) REFERENCES grade_recap_imports(id),
        FOREIGN KEY (student_id) REFERENCES master_students(id),
        UNIQUE (import_id, nim)
    )");

    ensureColumnExists($db, 'grade_recap_results', 'source_name', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'student_id', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'student_name', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'class_name', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'normal_bs', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'normal_score', 'REAL DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'normal_letter', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'remedial_bs', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'remedial_score', 'REAL DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'remedial_letter', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'susulan_bs', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'susulan_score', 'REAL DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'susulan_letter', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'final_score', 'REAL DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'final_letter', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'final_bs', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'duplicate_nim_count', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'grade_recap_results', 'subject_id', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'exam_type', 'TEXT NOT NULL DEFAULT "UAS"');
    ensureColumnExists($db, 'grade_recap_results', 'academic_year', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'term', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'grade_recap_results', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

    $db->exec("CREATE TABLE IF NOT EXISTS follow_up_statuses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        subject_id INTEGER NOT NULL,
        exam_type TEXT NOT NULL DEFAULT 'UAS',
        academic_year TEXT NOT NULL DEFAULT '',
        term TEXT NOT NULL DEFAULT '',
        follow_up_type TEXT NOT NULL,
        class_id INTEGER DEFAULT NULL,
        class_name_snapshot TEXT DEFAULT NULL,
        source_import_id INTEGER DEFAULT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        follow_up_date TEXT DEFAULT NULL,
        follow_up_score REAL DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES master_students(id),
        FOREIGN KEY (subject_id) REFERENCES master_subjects(id),
        FOREIGN KEY (class_id) REFERENCES master_classes(id),
        FOREIGN KEY (source_import_id) REFERENCES grade_recap_imports(id)
    )");

    ensureColumnExists($db, 'follow_up_statuses', 'student_id', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'follow_up_statuses', 'subject_id', 'INTEGER NOT NULL DEFAULT 0');
    ensureColumnExists($db, 'follow_up_statuses', 'exam_type', 'TEXT NOT NULL DEFAULT "UAS"');
    ensureColumnExists($db, 'follow_up_statuses', 'academic_year', 'TEXT NOT NULL DEFAULT ""');
    ensureColumnExists($db, 'follow_up_statuses', 'term', 'TEXT NOT NULL DEFAULT ""');
    ensureColumnExists($db, 'follow_up_statuses', 'follow_up_type', 'TEXT NOT NULL DEFAULT "remedial"');
    ensureColumnExists($db, 'follow_up_statuses', 'class_id', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'follow_up_statuses', 'class_name_snapshot', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'follow_up_statuses', 'source_import_id', 'INTEGER DEFAULT NULL');
    ensureColumnExists($db, 'follow_up_statuses', 'status', 'TEXT NOT NULL DEFAULT "pending"');
    ensureColumnExists($db, 'follow_up_statuses', 'follow_up_date', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'follow_up_statuses', 'follow_up_score', 'REAL DEFAULT NULL');
    ensureColumnExists($db, 'follow_up_statuses', 'notes', 'TEXT DEFAULT NULL');
    ensureColumnExists($db, 'follow_up_statuses', 'created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
    ensureColumnExists($db, 'follow_up_statuses', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

    $db->exec('CREATE INDEX IF NOT EXISTS idx_sessions_folder_id ON sessions(folder_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_folders_parent_id ON folders(parent_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_master_students_class_id ON master_students(class_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_master_students_active_class ON master_students(is_active, class_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_master_students_status_class ON master_students(student_status, class_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_import_id ON grade_recap_results(import_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_nim ON grade_recap_results(nim)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_final_score ON grade_recap_results(final_score)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_imports_subject_id ON grade_recap_imports(subject_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_imports_subject_exam_type ON grade_recap_imports(subject_id, exam_type)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_imports_period_scope ON grade_recap_imports(subject_id, exam_type, academic_year, term)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_subject_id ON grade_recap_results(subject_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_subject_class_nim ON grade_recap_results(subject_id, class_name, nim)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_subject_exam_class ON grade_recap_results(subject_id, exam_type, class_name)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_grade_recap_results_period_scope ON grade_recap_results(subject_id, exam_type, academic_year, term, class_name)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_follow_up_statuses_unique_scope ON follow_up_statuses(student_id, subject_id, exam_type, academic_year, term, follow_up_type)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_follow_up_statuses_class_scope ON follow_up_statuses(class_id, follow_up_type, status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_follow_up_statuses_subject_scope ON follow_up_statuses(subject_id, exam_type, academic_year, term)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_master_academic_periods_year_term ON master_academic_periods(academic_year, term)');

    $db->exec("UPDATE grade_recap_imports SET exam_type = 'UAS' WHERE exam_type IS NULL OR TRIM(exam_type) = ''");
    $db->exec("UPDATE master_students SET student_status = 'aktif' WHERE student_status IS NULL OR TRIM(student_status) = ''");
    $db->exec("UPDATE master_students SET is_active = CASE WHEN student_status IN ('cuti', 'keluar') THEN 0 ELSE 1 END");
    $db->exec('UPDATE grade_recap_results SET subject_id = (SELECT subject_id FROM grade_recap_imports i WHERE i.id = grade_recap_results.import_id) WHERE subject_id IS NULL');
    $db->exec("UPDATE grade_recap_results SET exam_type = COALESCE((SELECT i.exam_type FROM grade_recap_imports i WHERE i.id = grade_recap_results.import_id), 'UAS') WHERE exam_type IS NULL OR TRIM(exam_type) = ''");
    $db->exec('UPDATE grade_recap_results SET academic_year = (SELECT i.academic_year FROM grade_recap_imports i WHERE i.id = grade_recap_results.import_id) WHERE academic_year IS NULL OR TRIM(academic_year) = ""');
    $db->exec('UPDATE grade_recap_results SET term = (SELECT i.term FROM grade_recap_imports i WHERE i.id = grade_recap_results.import_id) WHERE term IS NULL OR TRIM(term) = ""');
    $db->exec("UPDATE follow_up_statuses SET exam_type = 'UAS' WHERE exam_type IS NULL OR TRIM(exam_type) = ''");
    $db->exec('UPDATE follow_up_statuses SET academic_year = "" WHERE academic_year IS NULL');
    $db->exec('UPDATE follow_up_statuses SET term = "" WHERE term IS NULL');

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
    $value = normalizeUtf8Text((string) $value);
    return preg_replace('/\s+/', ' ', trim($value));
}

function normalizeUtf8Text(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    $value = str_replace("\u{2026}", '...', $value);

    return preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
}

function repairMasterSubjectEncoding(PDO $db): void
{
    $rows = $db->query('SELECT id, name FROM master_subjects ORDER BY id ASC')->fetchAll();
    if (!$rows) {
        return;
    }

    $updateStmt = $db->prepare('UPDATE master_subjects SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');

    foreach ($rows as $row) {
        $currentName = (string) ($row['name'] ?? '');
        $normalizedName = sanitizeMasterName($currentName);
        if ($normalizedName === '' || $normalizedName === $currentName) {
            continue;
        }

        $existingSubject = findSubjectByName($db, $normalizedName);
        if ($existingSubject && (int) $existingSubject['id'] !== (int) $row['id']) {
            continue;
        }

        $updateStmt->execute([
            ':id' => (int) $row['id'],
            ':name' => $normalizedName,
        ]);
    }
}

function normalizeClassCode($value)
{
    $value = preg_replace('/\s+/', ' ', sanitizeMasterName($value));
    return strtoupper($value);
}

function normalizeStudentStatus($value)
{
    $value = strtolower(sanitizeMasterName($value));
    $allowedStatuses = ['aktif', 'cuti', 'tidak_naik', 'keluar'];

    if (!in_array($value, $allowedStatuses, true)) {
        return 'aktif';
    }

    return $value;
}

function isStudentStatusActive($status)
{
    $status = normalizeStudentStatus($status);
    return $status === 'aktif' || $status === 'tidak_naik';
}

function normalizeStudentRow($row)
{
    $status = normalizeStudentStatus($row['student_status'] ?? 'aktif');

    return [
        'id' => isset($row['id']) ? (int) $row['id'] : 0,
        'nim' => (string) ($row['nim'] ?? ''),
        'nama' => (string) ($row['name'] ?? $row['nama'] ?? ''),
        'class_id' => isset($row['class_id']) ? (int) $row['class_id'] : 0,
        'student_status' => $status,
        'is_active' => isStudentStatusActive($status) ? 1 : 0,
    ];
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
        'name' => sanitizeMasterName($subject['name'] ?? ''),
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

function normalizeAcademicPeriodAcademicYear($value)
{
    $value = sanitizeMasterName($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^(\d{4})\s*[\/\-]\s*(\d{4})$/', $value, $matches) !== 1) {
        return null;
    }

    $startYear = (int) $matches[1];
    $endYear = (int) $matches[2];
    if ($endYear !== $startYear + 1) {
        return null;
    }

    return $startYear . '/' . $endYear;
}

function normalizeAcademicPeriodTerm($value)
{
    $value = strtoupper(sanitizeMasterName($value));
    if ($value === 'GANJIL' || $value === 'GENAP') {
        return $value;
    }

    return null;
}

function getAcademicPeriods(PDO $db)
{
    $rows = $db->query('SELECT id, academic_year, term FROM master_academic_periods ORDER BY academic_year DESC, CASE term WHEN "GANJIL" THEN 1 WHEN "GENAP" THEN 2 ELSE 3 END ASC, id DESC')->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['academic_year'] = sanitizeMasterName($row['academic_year'] ?? '');
        $row['term'] = sanitizeMasterName($row['term'] ?? '');
    }
    unset($row);

    return $rows;
}

function academicPeriodExists(PDO $db, $academicYear, $term)
{
    $academicYear = normalizeAcademicPeriodAcademicYear($academicYear);
    $term = normalizeAcademicPeriodTerm($term);
    if ($academicYear === null || $term === null) {
        return false;
    }

    $stmt = $db->prepare('SELECT id FROM master_academic_periods WHERE academic_year = :academic_year AND term = :term LIMIT 1');
    $stmt->execute([
        ':academic_year' => $academicYear,
        ':term' => $term,
    ]);

    return (bool) $stmt->fetch();
}

function getOrCreateAcademicPeriod(PDO $db, $academicYear, $term)
{
    $academicYear = normalizeAcademicPeriodAcademicYear($academicYear);
    $term = normalizeAcademicPeriodTerm($term);

    if ($academicYear === null) {
        throw new InvalidArgumentException('Tahun ajaran wajib diisi dengan format seperti 2025/2026');
    }

    if ($term === null) {
        throw new InvalidArgumentException('Periode semester wajib dipilih (Ganjil/Genap)');
    }

    $findStmt = $db->prepare('SELECT id, academic_year, term FROM master_academic_periods WHERE academic_year = :academic_year AND term = :term LIMIT 1');
    $findStmt->execute([
        ':academic_year' => $academicYear,
        ':term' => $term,
    ]);
    $existing = $findStmt->fetch();

    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'academic_year' => (string) $existing['academic_year'],
            'term' => (string) $existing['term'],
        ];
    }

    $insertStmt = $db->prepare('INSERT INTO master_academic_periods (academic_year, term) VALUES (:academic_year, :term)');
    $insertStmt->execute([
        ':academic_year' => $academicYear,
        ':term' => $term,
    ]);

    return [
        'id' => (int) $db->lastInsertId(),
        'academic_year' => $academicYear,
        'term' => $term,
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
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        return $students;
    }

    $delimiter = detectCsvDelimiter($firstLine);

    while (($row = fgetcsv($handle, 10000, $delimiter, '"', '\\')) !== false) {
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

function detectCsvDelimiter(string $headerLine): string
{
    return substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
}

function loadStudentsByClassId(PDO $db, $classId)
{
    $class = getClassById($db, $classId);

    $studentsStmt = $db->prepare("SELECT id, nim, name, class_id, student_status, is_active FROM master_students WHERE class_id = :class_id ORDER BY CASE student_status WHEN 'aktif' THEN 1 WHEN 'tidak_naik' THEN 2 WHEN 'cuti' THEN 3 WHEN 'keluar' THEN 4 ELSE 5 END, name ASC");
    $studentsStmt->execute([':class_id' => $class['id']]);

    $students = [];
    foreach ($studentsStmt->fetchAll() as $row) {
        $student = normalizeStudentRow($row);
        $student['tingkat'] = $class['name'];
        $students[] = $student;
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
