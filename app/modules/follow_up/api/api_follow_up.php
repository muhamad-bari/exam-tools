<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request(true);

header('Content-Type: application/json; charset=utf-8');

require_once PROJECT_ROOT . '/app/shared/lib/database.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!defined('GRADE_RECAP_HELPERS_ONLY')) {
    define('GRADE_RECAP_HELPERS_ONLY', true);
}
require_once PROJECT_ROOT . '/app/modules/grade_recap/api/api_rekap_nilai.php';

try {
    $db = getDatabaseConnection();
    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'export_remedial_class_xlsx') {
        $filters = getFollowUpFiltersFromArray($_GET, false);
        if (($filters['class_id'] ?? null) === null) {
            throw new RuntimeException('class_id wajib diisi');
        }

        $class = getClassById($db, $filters['class_id']);

        $exportFilters = $filters;
        unset($exportFilters['student_id'], $exportFilters['subject_id']);

        $items = getRemedialFollowUpItems($db, $exportFilters);
        streamFollowUpRemedialClassXlsx($class['name'], $items);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'export_xlsx_susulan') {
        $filters = getFollowUpFiltersFromArray($_GET, false);
        if (($filters['class_id'] ?? null) === null) {
            throw new RuntimeException('class_id wajib diisi');
        }

        $class = getClassById($db, $filters['class_id']);

        $exportFilters = $filters;
        unset($exportFilters['student_id'], $exportFilters['subject_id']);

        $items = getSusulanFollowUpItems($db, $exportFilters);
        streamFollowUpSusulanClassXlsx($class['name'], $items);
        exit;
    }

    if ($action === 'list_overview') {
        $filters = getFollowUpFiltersFromArray($_GET, false);
        $overview = buildFollowUpOverview($db, $filters);

        echo json_encode([
            'success' => true,
            'message' => 'Daftar tindak lanjut berhasil dimuat',
            'data' => $overview['data'],
            'meta' => $overview['meta'],
        ]);
        exit;
    }

    if ($action === 'list_student_items') {
        $filters = getFollowUpFiltersFromArray($_GET, true);
        if (($filters['student_id'] ?? null) === null) {
            throw new RuntimeException('student_id wajib diisi');
        }

        $overview = buildFollowUpOverview($db, $filters);
        $student = findStudentSummaryFromOverview($overview['data']['by_class'], (int) $filters['student_id']);

        echo json_encode([
            'success' => true,
            'message' => 'Daftar tindak lanjut mahasiswa berhasil dimuat',
            'data' => [
                'student' => $student,
                'items' => collectStudentItemsFromOverview($overview['data']['by_class'], (int) $filters['student_id']),
            ],
            'meta' => $overview['meta'],
        ]);
        exit;
    }

    if ($action === 'save_status') {
        ensureFollowUpPostRequest();
        $input = readFollowUpJsonInput();
        $saved = saveFollowUpStatus($db, $input);

        echo json_encode([
            'success' => true,
            'message' => 'Status tindak lanjut berhasil disimpan',
            'data' => $saved,
            'meta' => [
                'filters' => [
                    'student_id' => $saved['student_id'],
                    'subject_id' => $saved['subject_id'],
                    'exam_type' => $saved['exam_type'],
                    'academic_year' => $saved['academic_year'],
                    'term' => $saved['term'],
                    'follow_up_type' => $saved['follow_up_type'],
                ],
            ],
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

function buildFollowUpOverview(PDO $db, array $filters)
{
    $remedialItems = getRemedialFollowUpItems($db, $filters);
    $susulanItems = getSusulanFollowUpItems($db, $filters);
    $items = array_merge($remedialItems, $susulanItems);

    usort($items, static function ($left, $right) {
        $classCompare = strcmp(mb_strtolower((string) ($left['class_name'] ?? '')), mb_strtolower((string) ($right['class_name'] ?? '')));
        if ($classCompare !== 0) {
            return $classCompare;
        }

        $studentCompare = strcmp(mb_strtolower((string) ($left['student_name'] ?? '')), mb_strtolower((string) ($right['student_name'] ?? '')));
        if ($studentCompare !== 0) {
            return $studentCompare;
        }

        $nimCompare = strcmp((string) ($left['nim'] ?? ''), (string) ($right['nim'] ?? ''));
        if ($nimCompare !== 0) {
            return $nimCompare;
        }

        $subjectCompare = strcmp(mb_strtolower((string) ($left['subject_name'] ?? '')), mb_strtolower((string) ($right['subject_name'] ?? '')));
        if ($subjectCompare !== 0) {
            return $subjectCompare;
        }

        return strcmp((string) ($left['follow_up_type'] ?? ''), (string) ($right['follow_up_type'] ?? ''));
    });

    $scopes = [];
    $byClass = [];
    $totalStudents = [];

    foreach ($items as $item) {
        $scopeKey = buildFollowUpScopeKey($item);
        if (!isset($scopes[$scopeKey])) {
            $scopes[$scopeKey] = [
                'scope_key' => $scopeKey,
                'class_id' => $item['class_id'],
                'class_name' => $item['class_name'],
                'subject_id' => $item['subject_id'],
                'subject_name' => $item['subject_name'],
                'exam_type' => $item['exam_type'],
                'academic_year' => $item['academic_year'],
                'term' => $item['term'],
                'source_import_id' => $item['source_import_id'],
                'item_count' => 0,
            ];
        }
        $scopes[$scopeKey]['item_count']++;

        $classKey = (string) ($item['class_id'] ?? 0) . '::' . (string) ($item['class_name'] ?? '');
        if (!isset($byClass[$classKey])) {
            $byClass[$classKey] = [
                'class' => [
                    'id' => $item['class_id'],
                    'name' => $item['class_name'],
                ],
                'students' => [],
                'totals' => [
                    'students' => 0,
                    'items' => 0,
                    'remedial' => 0,
                    'susulan' => 0,
                ],
            ];
        }

        $studentKey = (string) $item['student_id'];
        if (!isset($byClass[$classKey]['students'][$studentKey])) {
            $byClass[$classKey]['students'][$studentKey] = [
                'student' => [
                    'id' => $item['student_id'],
                    'nim' => $item['nim'],
                    'name' => $item['student_name'],
                ],
                'items' => [],
                'totals' => [
                    'items' => 0,
                    'remedial' => 0,
                    'susulan' => 0,
                ],
            ];
        }

        $byClass[$classKey]['students'][$studentKey]['items'][] = $item;
        $byClass[$classKey]['students'][$studentKey]['totals']['items']++;
        $byClass[$classKey]['totals']['items']++;

        if ($item['follow_up_type'] === 'remedial') {
            $byClass[$classKey]['students'][$studentKey]['totals']['remedial']++;
            $byClass[$classKey]['totals']['remedial']++;
        }
        if ($item['follow_up_type'] === 'susulan') {
            $byClass[$classKey]['students'][$studentKey]['totals']['susulan']++;
            $byClass[$classKey]['totals']['susulan']++;
        }

        $totalStudents[$studentKey] = true;
    }

    $classRows = array_values(array_map(static function ($classRow) {
        $students = array_values($classRow['students']);
        usort($students, static function ($left, $right) {
            $nameCompare = strcmp(mb_strtolower((string) ($left['student']['name'] ?? '')), mb_strtolower((string) ($right['student']['name'] ?? '')));
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return strcmp((string) ($left['student']['nim'] ?? ''), (string) ($right['student']['nim'] ?? ''));
        });

        foreach ($students as &$studentRow) {
            usort($studentRow['items'], static function ($left, $right) {
                $subjectCompare = strcmp(mb_strtolower((string) ($left['subject_name'] ?? '')), mb_strtolower((string) ($right['subject_name'] ?? '')));
                if ($subjectCompare !== 0) {
                    return $subjectCompare;
                }

                $typeCompare = strcmp((string) ($left['follow_up_type'] ?? ''), (string) ($right['follow_up_type'] ?? ''));
                if ($typeCompare !== 0) {
                    return $typeCompare;
                }

                return strcmp((string) ($left['exam_type'] ?? ''), (string) ($right['exam_type'] ?? ''));
            });
        }
        unset($studentRow);

        $classRow['students'] = $students;
        $classRow['totals']['students'] = count($students);
        return $classRow;
    }, $byClass));

    usort($classRows, static function ($left, $right) {
        return strcmp(mb_strtolower((string) ($left['class']['name'] ?? '')), mb_strtolower((string) ($right['class']['name'] ?? '')));
    });

    $scopeRows = array_values($scopes);
    usort($scopeRows, static function ($left, $right) {
        $classCompare = strcmp(mb_strtolower((string) ($left['class_name'] ?? '')), mb_strtolower((string) ($right['class_name'] ?? '')));
        if ($classCompare !== 0) {
            return $classCompare;
        }

        $subjectCompare = strcmp(mb_strtolower((string) ($left['subject_name'] ?? '')), mb_strtolower((string) ($right['subject_name'] ?? '')));
        if ($subjectCompare !== 0) {
            return $subjectCompare;
        }

        if (($left['academic_year'] ?? '') === ($right['academic_year'] ?? '')) {
            if (($left['term'] ?? '') === ($right['term'] ?? '')) {
                return strcmp((string) ($left['exam_type'] ?? ''), (string) ($right['exam_type'] ?? ''));
            }

            return strcmp((string) ($left['term'] ?? ''), (string) ($right['term'] ?? ''));
        }

        return strcmp((string) ($right['academic_year'] ?? ''), (string) ($left['academic_year'] ?? ''));
    });

    return [
        'data' => [
            'scopes' => $scopeRows,
            'by_class' => $classRows,
        ],
        'meta' => [
            'filters' => $filters,
            'total_items' => count($items),
            'total_students' => count($totalStudents),
            'total_classes' => count($classRows),
            'total_scopes' => count($scopeRows),
            'item_counts' => [
                'remedial' => count($remedialItems),
                'susulan' => count($susulanItems),
            ],
            'filter_options' => getRecapFilterOptionLists($db),
        ],
    ];
}

function getFollowUpFiltersFromArray(array $source, $strict)
{
    $filters = getRecapFiltersFromArray($source, $strict);
    $filters['class_id'] = normalizePositiveIntFilter($source['class_id'] ?? null, 'class_id', $strict);
    $filters['subject_id'] = normalizePositiveIntFilter($source['subject_id'] ?? null, 'subject_id', $strict);
    $filters['student_id'] = normalizePositiveIntFilter($source['student_id'] ?? null, 'student_id', $strict);

    return $filters;
}

function normalizePositiveIntFilter($value, $fieldName, $strict)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value) || (int) $value <= 0) {
        if ($strict) {
            throw new RuntimeException($fieldName . ' tidak valid');
        }
        return null;
    }

    return (int) $value;
}

function getRemedialFollowUpItems(PDO $db, array $filters)
{
    $latestSql = getLatestRecapScopeSql();
    $params = [];
    $conditions = buildRecapFilterConditions('r', $filters, $params);
    $conditions[] = 'r.student_id IS NOT NULL';
    $conditions[] = 'UPPER(COALESCE(r.normal_letter, "")) IN ("D", "E")';

    if (($filters['subject_id'] ?? null) !== null) {
        $conditions[] = 'r.subject_id = :subject_id';
        $params[':subject_id'] = $filters['subject_id'];
    }

    if (($filters['student_id'] ?? null) !== null) {
        $conditions[] = 'r.student_id = :student_id';
        $params[':student_id'] = $filters['student_id'];
    }

    if (($filters['class_id'] ?? null) !== null) {
        $conditions[] = 'CAST(COALESCE(recap_class.id, ms.class_id) AS INTEGER) = CAST(:class_id AS INTEGER)';
        $params[':class_id'] = $filters['class_id'];
    }

    $sql = 'SELECT r.student_id,
                   COALESCE(NULLIF(ms.nim, ""), r.nim) AS nim,
                   COALESCE(NULLIF(ms.name, ""), NULLIF(r.student_name, ""), NULLIF(r.source_name, "")) AS student_name,
                    CAST(COALESCE(recap_class.id, ms.class_id) AS INTEGER) AS class_id,
                    COALESCE(NULLIF(recap_class.name, ""), NULLIF(r.class_name, ""), NULLIF(current_class.name, "")) AS class_name,
                   r.subject_id,
                   subject.name AS subject_name,
                   COALESCE(r.exam_type, "UAS") AS exam_type,
                   COALESCE(r.academic_year, "") AS academic_year,
                   COALESCE(r.term, "") AS term,
                   r.import_id AS source_import_id,
                    r.normal_score,
                    r.normal_letter,
                    r.remedial_score AS uploaded_follow_up_score,
                    r.remedial_letter AS uploaded_follow_up_letter,
                    r.final_score,
                    r.final_letter,
                    import_row.created_at AS import_created_at,
                    r.updated_at AS recap_updated_at,
                    status_row.id AS status_id,
                   status_row.status,
                   status_row.follow_up_date,
                    status_row.follow_up_score,
                   status_row.notes,
                   status_row.updated_at AS status_updated_at,
                   status_row.class_name_snapshot,
                   status_row.class_id AS saved_class_id
              FROM grade_recap_results r
              INNER JOIN (' . $latestSql . ') latest ON latest.latest_import_id = r.import_id
                 AND latest.subject_id = r.subject_id
                 AND latest.exam_type = COALESCE(r.exam_type, "UAS")
                 AND latest.academic_year_key = COALESCE(r.academic_year, "")
                 AND latest.term_key = COALESCE(r.term, "")
                 AND latest.class_name_key = COALESCE(r.class_name, "")
              INNER JOIN master_subjects subject ON subject.id = r.subject_id
              INNER JOIN grade_recap_imports import_row ON import_row.id = r.import_id
              LEFT JOIN master_students ms ON ms.id = r.student_id
              LEFT JOIN master_classes current_class ON current_class.id = ms.class_id
              LEFT JOIN master_classes recap_class ON recap_class.name = r.class_name
              LEFT JOIN follow_up_statuses status_row ON status_row.student_id = r.student_id
                 AND status_row.subject_id = r.subject_id
                 AND status_row.exam_type = COALESCE(r.exam_type, "UAS")
                 AND status_row.academic_year = COALESCE(r.academic_year, "")
                 AND status_row.term = COALESCE(r.term, "")
                 AND status_row.follow_up_type = "remedial"
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY COALESCE(NULLIF(current_class.name, ""), NULLIF(r.class_name, "")) ASC,
                      COALESCE(NULLIF(ms.name, ""), NULLIF(r.student_name, ""), NULLIF(r.source_name, "")) ASC,
                      COALESCE(NULLIF(ms.nim, ""), r.nim) ASC,
                      subject.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = mapFollowUpItemRow($row, 'remedial', [
            'reason_code' => 'normal_letter_low',
            'reason_label' => 'Nilai huruf normal D/E',
        ]);
    }

    return $items;
}

function getSusulanFollowUpItems(PDO $db, array $filters)
{
    $latestSql = getLatestRecapScopeSql();
    $params = [];
    $scopeConditions = [
        'latest.class_name_key <> ""',
        'scope_class.id IS NOT NULL',
    ];

    if (($filters['exam_type'] ?? null) !== null) {
        $scopeConditions[] = 'latest.exam_type = :filter_exam_type';
        $params[':filter_exam_type'] = $filters['exam_type'];
    }
    if (($filters['academic_year'] ?? null) !== null) {
        $scopeConditions[] = 'latest.academic_year_key = :filter_academic_year';
        $params[':filter_academic_year'] = $filters['academic_year'];
    }
    if (($filters['term'] ?? null) !== null) {
        $scopeConditions[] = 'latest.term_key = :filter_term';
        $params[':filter_term'] = $filters['term'];
    }
    if (($filters['subject_id'] ?? null) !== null) {
        $scopeConditions[] = 'latest.subject_id = :subject_id';
        $params[':subject_id'] = $filters['subject_id'];
    }
    if (($filters['class_id'] ?? null) !== null) {
        $scopeConditions[] = 'scope_class.id = :class_id';
        $params[':class_id'] = $filters['class_id'];
    }
    if (($filters['student_id'] ?? null) !== null) {
        $params[':student_id'] = $filters['student_id'];
    }

    $sql = 'SELECT student.id AS student_id,
                   student.nim,
                   student.name AS student_name,
                   class.id AS class_id,
                   class.name AS class_name,
                   scoped.subject_id,
                   subject.name AS subject_name,
                   scoped.exam_type,
                   scoped.academic_year,
                   scoped.term,
                    scoped.latest_import_id AS source_import_id,
                    r.susulan_score AS uploaded_follow_up_score,
                    r.susulan_letter AS uploaded_follow_up_letter,
                     r.final_score,
                     r.final_letter,
                     import_row.created_at AS import_created_at,
                     r.updated_at AS recap_updated_at,
                    status_row.id AS status_id,
                   status_row.status,
                   status_row.follow_up_date,
                   status_row.follow_up_score,
                   status_row.notes,
                   status_row.updated_at AS status_updated_at,
                   status_row.class_name_snapshot,
                   status_row.class_id AS saved_class_id
              FROM (
                    SELECT latest.latest_import_id,
                           latest.subject_id,
                           latest.exam_type,
                           latest.academic_year_key AS academic_year,
                           latest.term_key AS term,
                           latest.class_name_key AS class_name,
                           scope_class.id AS class_id
                      FROM (' . $latestSql . ') latest
                      LEFT JOIN master_classes scope_class ON scope_class.name = latest.class_name_key
                     WHERE ' . implode(' AND ', $scopeConditions) . '
                   ) scoped
              INNER JOIN master_classes class ON class.id = scoped.class_id
              INNER JOIN master_students student ON student.class_id = class.id AND student.is_active = 1
               INNER JOIN master_subjects subject ON subject.id = scoped.subject_id
               INNER JOIN grade_recap_imports import_row ON import_row.id = scoped.latest_import_id
               LEFT JOIN grade_recap_results r ON r.import_id = scoped.latest_import_id
                 AND r.subject_id = scoped.subject_id
                 AND COALESCE(r.exam_type, "UAS") = scoped.exam_type
                 AND COALESCE(r.academic_year, "") = scoped.academic_year
                 AND COALESCE(r.term, "") = scoped.term
                 AND COALESCE(r.class_name, "") = class.name
                 AND (r.student_id = student.id OR (r.student_id IS NULL AND r.nim = student.nim))
              LEFT JOIN follow_up_statuses status_row ON status_row.student_id = student.id
                 AND status_row.subject_id = scoped.subject_id
                 AND status_row.exam_type = scoped.exam_type
                 AND status_row.academic_year = scoped.academic_year
                 AND status_row.term = scoped.term
                 AND status_row.follow_up_type = "susulan"
             WHERE (r.id IS NULL OR r.susulan_score IS NOT NULL)';

    if (($filters['student_id'] ?? null) !== null) {
        $sql .= ' AND student.id = :student_id';
    }

    $sql .= ' ORDER BY class.name ASC, student.name ASC, student.nim ASC, subject.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $reason = [
            'reason_code' => 'missing_latest_recap',
            'reason_label' => 'Belum memiliki baris pada rekap terbaru',
        ];
        if (array_key_exists('uploaded_follow_up_score', $row) && $row['uploaded_follow_up_score'] !== null) {
            $reason = [
                'reason_code' => 'uploaded_susulan_score',
                'reason_label' => 'Nilai susulan sudah diunggah',
            ];
        }

        $items[] = mapFollowUpItemRow($row, 'susulan', [
            'reason_code' => $reason['reason_code'],
            'reason_label' => $reason['reason_label'],
        ]);
    }

    return $items;
}

function mapFollowUpItemRow(array $row, $followUpType, array $reason)
{
    $uploadedFollowUpScore = null;
    if (array_key_exists('uploaded_follow_up_score', $row) && $row['uploaded_follow_up_score'] !== null) {
        $uploadedFollowUpScore = (float) $row['uploaded_follow_up_score'];
    }

    $hasSavedStatus = isset($row['status_id']) && $row['status_id'] !== null;
    $savedFollowUpScore = array_key_exists('follow_up_score', $row) && $row['follow_up_score'] !== null
        ? (float) $row['follow_up_score']
        : null;
    $importFollowUpDate = extractDateFromTimestamp($row['import_created_at'] ?? null);
    $effectiveStatus = $hasSavedStatus ? (string) ($row['status'] ?? 'pending') : 'pending';
    if ($uploadedFollowUpScore !== null && $savedFollowUpScore === null) {
        $effectiveStatus = 'sudah mengikuti';
    }

    $status = [
        'id' => $hasSavedStatus ? (int) $row['status_id'] : null,
        'status' => $effectiveStatus,
        'follow_up_date' => ($row['follow_up_date'] ?? null) ?: ($uploadedFollowUpScore !== null ? $importFollowUpDate : null),
        'follow_up_score' => $savedFollowUpScore !== null ? $savedFollowUpScore : $uploadedFollowUpScore,
        'notes' => $row['notes'] ?? null,
        'updated_at' => ($row['status_updated_at'] ?? null) ?: ($uploadedFollowUpScore !== null ? (($row['import_created_at'] ?? null) ?: ($row['recap_updated_at'] ?? null)) : null),
        'class_id' => isset($row['saved_class_id']) && $row['saved_class_id'] !== null ? (int) $row['saved_class_id'] : null,
        'class_name_snapshot' => $row['class_name_snapshot'] ?? null,
    ];

    return [
        'student_id' => (int) $row['student_id'],
        'nim' => (string) ($row['nim'] ?? ''),
        'student_name' => (string) ($row['student_name'] ?? ''),
        'class_id' => isset($row['class_id']) && $row['class_id'] !== null ? (int) $row['class_id'] : null,
        'class_name' => (string) ($row['class_name'] ?? ''),
        'subject_id' => (int) $row['subject_id'],
        'subject_name' => (string) ($row['subject_name'] ?? ''),
        'exam_type' => (string) ($row['exam_type'] ?? 'UAS'),
        'academic_year' => (string) ($row['academic_year'] ?? ''),
        'term' => (string) ($row['term'] ?? ''),
        'follow_up_type' => $followUpType,
        'source_import_id' => isset($row['source_import_id']) && $row['source_import_id'] !== null ? (int) $row['source_import_id'] : null,
        'reason_code' => $reason['reason_code'],
        'reason_label' => $reason['reason_label'],
        'normal_score' => array_key_exists('normal_score', $row) && $row['normal_score'] !== null ? (float) $row['normal_score'] : null,
        'normal_letter' => $row['normal_letter'] ?? null,
        'final_score' => array_key_exists('final_score', $row) && $row['final_score'] !== null ? (float) $row['final_score'] : null,
        'final_letter' => $row['final_letter'] ?? null,
        'status' => $status,
        'scope_key' => buildFollowUpScopeKey([
            'class_id' => isset($row['class_id']) && $row['class_id'] !== null ? (int) $row['class_id'] : null,
            'subject_id' => (int) $row['subject_id'],
            'exam_type' => (string) ($row['exam_type'] ?? 'UAS'),
            'academic_year' => (string) ($row['academic_year'] ?? ''),
            'term' => (string) ($row['term'] ?? ''),
            'follow_up_type' => $followUpType,
        ]),
    ];
}

function extractDateFromTimestamp($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
        return substr($value, 0, 10);
    }

    return null;
}

function buildFollowUpScopeKey(array $item)
{
    return implode('::', [
        (string) ($item['class_id'] ?? ''),
        (string) ($item['subject_id'] ?? ''),
        (string) ($item['exam_type'] ?? 'UAS'),
        (string) ($item['academic_year'] ?? ''),
        (string) ($item['term'] ?? ''),
        (string) ($item['follow_up_type'] ?? ''),
    ]);
}

function findStudentSummaryFromOverview(array $classes, $studentId)
{
    foreach ($classes as $classRow) {
        foreach (($classRow['students'] ?? []) as $studentRow) {
            if ((int) (($studentRow['student']['id'] ?? 0)) === $studentId) {
                return [
                    'id' => (int) $studentRow['student']['id'],
                    'nim' => (string) ($studentRow['student']['nim'] ?? ''),
                    'name' => (string) ($studentRow['student']['name'] ?? ''),
                    'class' => $classRow['class'] ?? null,
                ];
            }
        }
    }

    return null;
}

function collectStudentItemsFromOverview(array $classes, $studentId)
{
    foreach ($classes as $classRow) {
        foreach (($classRow['students'] ?? []) as $studentRow) {
            if ((int) (($studentRow['student']['id'] ?? 0)) === $studentId) {
                return $studentRow['items'] ?? [];
            }
        }
    }

    return [];
}

function saveFollowUpStatus(PDO $db, array $input)
{
    $studentId = intval($input['student_id'] ?? 0);
    if ($studentId <= 0) {
        throw new RuntimeException('student_id wajib diisi');
    }

    $subjectId = intval($input['subject_id'] ?? 0);
    if ($subjectId <= 0) {
        throw new RuntimeException('subject_id wajib diisi');
    }

    $examType = normalizeExamType($input['exam_type'] ?? '');
    if ($examType === null) {
        throw new RuntimeException('exam_type tidak valid');
    }

    $academicYear = normalizeAcademicYear($input['academic_year'] ?? '');
    if ($academicYear === null) {
        throw new RuntimeException('academic_year tidak valid');
    }

    $term = normalizeTerm($input['term'] ?? '');
    if ($term === null) {
        throw new RuntimeException('term tidak valid');
    }

    if (!academicPeriodExists($db, $academicYear, $term)) {
        throw new RuntimeException('Periode akademik tidak ditemukan di master');
    }

    $followUpType = normalizeFollowUpType($input['follow_up_type'] ?? '');
    $status = normalizeFollowUpStatus($input['status'] ?? 'pending');
    $followUpDate = normalizeFollowUpDate($input['follow_up_date'] ?? null);
    $followUpScore = normalizeFollowUpScore($input['follow_up_score'] ?? null);
    $notes = normalizeNullableText($input['notes'] ?? null);
    $classId = normalizeNullablePositiveInt($input['class_id'] ?? null);
    $classNameSnapshot = normalizeNullableText($input['class_name_snapshot'] ?? null);
    $sourceImportId = normalizeNullablePositiveInt($input['source_import_id'] ?? null);

    $student = getMasterStudentById($db, $studentId);
    getSubjectById($db, $subjectId);
    if ($classId !== null) {
        $class = getClassById($db, $classId);
        if ($classNameSnapshot === null) {
            $classNameSnapshot = $class['name'];
        }
    }
    if ($classNameSnapshot === null && !empty($student['class_name'])) {
        $classNameSnapshot = $student['class_name'];
    }

    $stmt = $db->prepare('INSERT INTO follow_up_statuses (
            student_id,
            subject_id,
            exam_type,
            academic_year,
            term,
            follow_up_type,
            class_id,
            class_name_snapshot,
            source_import_id,
            status,
            follow_up_date,
            follow_up_score,
            notes,
            created_at,
            updated_at
        ) VALUES (
            :student_id,
            :subject_id,
            :exam_type,
            :academic_year,
            :term,
            :follow_up_type,
            :class_id,
            :class_name_snapshot,
            :source_import_id,
            :status,
            :follow_up_date,
            :follow_up_score,
            :notes,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        ON CONFLICT(student_id, subject_id, exam_type, academic_year, term, follow_up_type)
        DO UPDATE SET
            class_id = excluded.class_id,
            class_name_snapshot = excluded.class_name_snapshot,
            source_import_id = excluded.source_import_id,
            status = excluded.status,
            follow_up_date = excluded.follow_up_date,
            follow_up_score = excluded.follow_up_score,
            notes = excluded.notes,
            updated_at = CURRENT_TIMESTAMP');

    $stmt->execute([
        ':student_id' => $studentId,
        ':subject_id' => $subjectId,
        ':exam_type' => $examType,
        ':academic_year' => $academicYear,
        ':term' => $term,
        ':follow_up_type' => $followUpType,
        ':class_id' => $classId,
        ':class_name_snapshot' => $classNameSnapshot,
        ':source_import_id' => $sourceImportId,
        ':status' => $status,
        ':follow_up_date' => $followUpDate,
        ':follow_up_score' => $followUpScore,
        ':notes' => $notes,
    ]);

    $savedStmt = $db->prepare('SELECT id,
                                      student_id,
                                      subject_id,
                                      exam_type,
                                      academic_year,
                                      term,
                                      follow_up_type,
                                      class_id,
                                      class_name_snapshot,
                                      source_import_id,
                                      status,
                                      follow_up_date,
                                      follow_up_score,
                                      notes,
                                      created_at,
                                      updated_at
                                 FROM follow_up_statuses
                                WHERE student_id = :student_id
                                  AND subject_id = :subject_id
                                  AND exam_type = :exam_type
                                  AND academic_year = :academic_year
                                  AND term = :term
                                  AND follow_up_type = :follow_up_type
                                LIMIT 1');
    $savedStmt->execute([
        ':student_id' => $studentId,
        ':subject_id' => $subjectId,
        ':exam_type' => $examType,
        ':academic_year' => $academicYear,
        ':term' => $term,
        ':follow_up_type' => $followUpType,
    ]);
    $saved = $savedStmt->fetch();

    if (!$saved) {
        throw new RuntimeException('Status tindak lanjut gagal dibaca ulang');
    }

    return [
        'id' => (int) $saved['id'],
        'student_id' => (int) $saved['student_id'],
        'subject_id' => (int) $saved['subject_id'],
        'exam_type' => (string) $saved['exam_type'],
        'academic_year' => (string) $saved['academic_year'],
        'term' => (string) $saved['term'],
        'follow_up_type' => (string) $saved['follow_up_type'],
        'class_id' => $saved['class_id'] !== null ? (int) $saved['class_id'] : null,
        'class_name_snapshot' => $saved['class_name_snapshot'] ?? null,
        'source_import_id' => $saved['source_import_id'] !== null ? (int) $saved['source_import_id'] : null,
        'status' => (string) $saved['status'],
        'follow_up_date' => $saved['follow_up_date'] ?? null,
        'follow_up_score' => $saved['follow_up_score'] !== null ? (float) $saved['follow_up_score'] : null,
        'notes' => $saved['notes'] ?? null,
        'created_at' => $saved['created_at'] ?? null,
        'updated_at' => $saved['updated_at'] ?? null,
    ];
}

function normalizeFollowUpType($value)
{
    $value = strtolower(trim((string) $value));
    if ($value === 'sp') {
        $value = 'remedial';
    }

    if ($value !== 'remedial' && $value !== 'susulan') {
        throw new RuntimeException('follow_up_type tidak valid');
    }

    return $value;
}

function normalizeFollowUpStatus($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        throw new RuntimeException('status wajib diisi');
    }

    return preg_replace('/\s+/', ' ', $value);
}

function normalizeFollowUpDate($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new RuntimeException('follow_up_date harus berformat YYYY-MM-DD');
    }

    return $value;
}

function normalizeFollowUpScore($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return round((float) $value, 2);
    }

    $value = str_replace(',', '.', trim((string) $value));
    if ($value === '' || !is_numeric($value)) {
        throw new RuntimeException('follow_up_score tidak valid');
    }

    return round((float) $value, 2);
}

function normalizeNullableText($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim(preg_replace('/\s+/', ' ', (string) $value));
    return $value === '' ? null : $value;
}

function normalizeNullablePositiveInt($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value) || (int) $value <= 0) {
        throw new RuntimeException('Nilai ID tidak valid');
    }

    return (int) $value;
}

function getMasterStudentById(PDO $db, $studentId)
{
    $stmt = $db->prepare('SELECT s.id, s.nim, s.name, s.class_id, c.name AS class_name
                            FROM master_students s
                            LEFT JOIN master_classes c ON c.id = s.class_id
                           WHERE s.id = :id
                           LIMIT 1');
    $stmt->execute([':id' => $studentId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('Mahasiswa tidak ditemukan di master');
    }

    return [
        'id' => (int) $row['id'],
        'nim' => (string) ($row['nim'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'class_id' => $row['class_id'] !== null ? (int) $row['class_id'] : null,
        'class_name' => (string) ($row['class_name'] ?? ''),
    ];
}

function ensureFollowUpPostRequest()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method');
    }
}

function readFollowUpJsonInput()
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function streamFollowUpRemedialClassXlsx($className, array $items)
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Remedial');

    $sheet->setCellValue('A1', 'No');
    $sheet->setCellValue('B1', 'Nama');
    $sheet->setCellValue('C1', 'NIM');
    $sheet->setCellValue('D1', 'Matakuliah');
    $sheet->setCellValue('E1', 'Nilai');
    $sheet->setCellValue('F1', 'Nilai Huruf');

    $students = [];
    foreach ($items as $item) {
        $studentId = (int) ($item['student_id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }

        if (!isset($students[$studentId])) {
            $students[$studentId] = [
                'student_id' => $studentId,
                'nim' => (string) ($item['nim'] ?? ''),
                'name' => (string) ($item['student_name'] ?? ''),
                'items' => [],
            ];
        }

        $students[$studentId]['items'][] = $item;
    }

    $students = array_values($students);

    $rowNumber = 2;
    $sequenceNumber = 1;

    foreach ($students as $student) {
        $subjectCount = count($student['items']);
        foreach ($student['items'] as $index => $item) {
            if ($index === 0) {
                $sheet->setCellValue('A' . $rowNumber, $sequenceNumber);
            }

            $sheet->setCellValue('B' . $rowNumber, $student['name']);
            $sheet->setCellValueExplicit('C' . $rowNumber, $student['nim'], DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $rowNumber, $item['subject_name'] ?? '');
            $sheet->setCellValue('E' . $rowNumber, $item['normal_score'] ?? null);
            $sheet->setCellValue('F' . $rowNumber, $item['normal_letter'] ?? '');
            $rowNumber++;
        }

        $sheet->mergeCells('A' . $rowNumber . ':E' . $rowNumber);
        $sheet->setCellValue('A' . $rowNumber, 'total matakuliah');
        $sheet->setCellValue('F' . $rowNumber, $subjectCount);

        $rowNumber++;
        $sequenceNumber++;
    }

    $safeClass = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $className);
    $timestamp = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Ymd_His');
    $fileName = 'follow_up_remedial_' . ($safeClass !== '' ? $safeClass : 'kelas') . '_' . $timestamp . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

function streamFollowUpSusulanClassXlsx($className, array $items)
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Susulan');

    $sheet->setCellValue('A1', 'No');
    $sheet->setCellValue('B1', 'Nama');
    $sheet->setCellValue('C1', 'NIM');
    $sheet->setCellValue('D1', 'Matakuliah');
    $sheet->setCellValue('E1', 'Total Matakuliah');

    $students = [];
    foreach ($items as $item) {
        $studentId = (int) ($item['student_id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }

        if (!isset($students[$studentId])) {
            $students[$studentId] = [
                'student_id' => $studentId,
                'nim' => (string) ($item['nim'] ?? ''),
                'name' => (string) ($item['student_name'] ?? ''),
                'items' => [],
            ];
        }

        $students[$studentId]['items'][] = $item;
    }

    $students = array_values($students);

    $rowNumber = 2;
    $sequenceNumber = 1;

    foreach ($students as $student) {
        $subjectCount = count($student['items']);
        foreach ($student['items'] as $index => $item) {
            if ($index === 0) {
                $sheet->setCellValue('A' . $rowNumber, $sequenceNumber);
            }

            $sheet->setCellValue('B' . $rowNumber, $student['name']);
            $sheet->setCellValueExplicit('C' . $rowNumber, $student['nim'], DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $rowNumber, $item['subject_name'] ?? '');
            $rowNumber++;
        }

        $sheet->mergeCells('A' . $rowNumber . ':D' . $rowNumber);
        $sheet->setCellValue('A' . $rowNumber, 'total matakuliah');
        $sheet->setCellValue('E' . $rowNumber, $subjectCount);

        $rowNumber++;
        $sequenceNumber++;
    }

    $safeClass = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $className);
    $timestamp = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Ymd_His');
    $fileName = 'follow_up_susulan_' . ($safeClass !== '' ? $safeClass : 'kelas') . '_' . $timestamp . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}
