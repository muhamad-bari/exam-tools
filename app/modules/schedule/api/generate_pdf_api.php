<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request(true);

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('memory_limit', '512M');
set_time_limit(300);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once PROJECT_ROOT . '/app/shared/lib/database.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
    }

    if (!isset($_POST['generate_pdf'])) {
        throw new RuntimeException('Missing generate_pdf parameter');
    }

    if (!file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
        throw new RuntimeException('Vendor autoload not found');
    }

    if (!file_exists(PROJECT_ROOT . '/phpqrcode/qrlib.php')) {
        throw new RuntimeException('PHPQRCode library not found');
    }

    require_once PROJECT_ROOT . '/vendor/autoload.php';
    require_once PROJECT_ROOT . '/phpqrcode/qrlib.php';

    $db = getDatabaseConnection();

    $headerLine1 = isset($_POST['header_line1']) ? strtoupper(trim($_POST['header_line1'])) : 'AKADEMI KEBIDANAN WIJAYA HUSADA';
    $headerLine2 = isset($_POST['header_line2']) ? strtoupper(trim($_POST['header_line2'])) : '';
    $subTitle = isset($_POST['sub_title']) ? trim($_POST['sub_title']) : 'JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025';

    $signer = [
        'nama' => isset($_POST['signer_name']) ? trim($_POST['signer_name']) : 'Elpinaria Girsang, S.ST., M.K.M.',
        'jabatan' => isset($_POST['signer_title']) ? trim($_POST['signer_title']) : 'Direktur',
        'institusi' => isset($_POST['signer_institution']) ? trim($_POST['signer_institution']) : 'Akademi Kebidanan Wijaya Husada',
        'tanggal' => isset($_POST['signer_date']) ? trim($_POST['signer_date']) : date('d F Y'),
    ];

    $logoData = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $logoPath = $_FILES['logo']['tmp_name'];
        if (file_exists($logoPath)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($logoPath);
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        }
    } elseif (!empty($_POST['existing_logo_data'])) {
        $logoData = $_POST['existing_logo_data'];
    }

    $schedule = [];
    $matkuls = isset($_POST['matkul']) && is_array($_POST['matkul']) ? $_POST['matkul'] : [];
    $subjectIds = isset($_POST['subject_id']) && is_array($_POST['subject_id']) ? $_POST['subject_id'] : [];
    $haris = isset($_POST['hari']) && is_array($_POST['hari']) ? $_POST['hari'] : [];
    $jams = isset($_POST['jam']) && is_array($_POST['jam']) ? $_POST['jam'] : [];
    $ruangs = isset($_POST['ruang']) && is_array($_POST['ruang']) ? $_POST['ruang'] : [];

    $maxCount = max(count($matkuls), count($subjectIds), count($haris), count($jams), count($ruangs));
    if ($maxCount === 0) {
        throw new RuntimeException('Minimal harus ada satu mata kuliah yang diisi');
    }

    for ($i = 0; $i < $maxCount; $i++) {
        $matkul = trim((string) ($matkuls[$i] ?? ''));
        $subjectId = intval($subjectIds[$i] ?? 0);
        $hari = trim((string) ($haris[$i] ?? ''));
        $jam = trim((string) ($jams[$i] ?? ''));
        $ruang = trim((string) ($ruangs[$i] ?? ''));

        if ($subjectId > 0 && $matkul === '') {
            $subjectStmt = $db->prepare('SELECT name FROM master_subjects WHERE id = :id LIMIT 1');
            $subjectStmt->execute([':id' => $subjectId]);
            $matkul = trim((string) $subjectStmt->fetchColumn());
        }

        if ($matkul === '' && $hari === '' && $jam === '' && $ruang === '') {
            continue;
        }

        if ($matkul === '') {
            throw new RuntimeException('Nama mata kuliah wajib diisi pada setiap baris jadwal yang digunakan');
        }

        $schedule[] = [
            'subject_id' => $subjectId > 0 ? $subjectId : null,
            'matkul' => $matkul,
            'hari' => $hari,
            'jam' => $jam,
            'ruang' => $ruang,
        ];
    }

    if (!$schedule) {
        throw new RuntimeException('Tidak ada jadwal yang valid untuk dibuatkan PDF');
    }

    $selectedStudentIdRaw = trim((string) ($_POST['selected_student_id'] ?? $_POST['selected_student'] ?? ''));
    $selectedStudentId = $selectedStudentIdRaw !== '' ? intval($selectedStudentIdRaw) : 0;

    if ($selectedStudentIdRaw !== '' && $selectedStudentId <= 0) {
        throw new RuntimeException('Mahasiswa yang dipilih tidak valid');
    }

    $classId = intval($_POST['class_id'] ?? 0);
    $students = [];
    $sourceLabel = 'mahasiswa';

    if ($classId > 0) {
        $result = loadStudentsByClassId($db, $classId);
        $students = $result['students'];
        if (!$students) {
            throw new RuntimeException('Kelas terpilih belum memiliki mahasiswa aktif');
        }

        if ($selectedStudentId > 0) {
            $students = array_values(array_filter($students, static function ($student) use ($selectedStudentId) {
                return isset($student['id']) && (int) $student['id'] === $selectedStudentId;
            }));

            if (!$students) {
                throw new RuntimeException('Mahasiswa yang dipilih tidak ditemukan pada kelas yang dipilih');
            }
        }

        $sourceLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $result['class']['name']);
    } else {
        if ($selectedStudentIdRaw !== '') {
            throw new RuntimeException('Pilihan satu mahasiswa hanya tersedia saat menggunakan kelas master');
        }

        if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== 0) {
            throw new RuntimeException('Pilih kelas master atau unggah file CSV mahasiswa');
        }

        $students = parseStudentCsvFile($_FILES['student_csv']['tmp_name']);
        if (!$students) {
            throw new RuntimeException('CSV tidak memiliki data mahasiswa yang valid');
        }

        if (!empty($_FILES['student_csv']['name'])) {
            $rawName = pathinfo($_FILES['student_csv']['name'], PATHINFO_FILENAME);
            $sourceLabel = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $rawName));
            if ($sourceLabel === '') {
                $sourceLabel = 'mahasiswa';
            }
        }
    }

    $htmlBody = '';
    foreach ($students as $student) {
        $qrContent = $student['nama'] . '_' . $student['tingkat'] . '_' . $student['nim'];
        $qrData = '';

        ob_start();
        try {
            $qrTempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
            @QRcode::png($qrContent, $qrTempFile, QR_ECLEVEL_L, 3, 2);
            if (file_exists($qrTempFile)) {
                $qrData = 'data:image/png;base64,' . base64_encode(file_get_contents($qrTempFile));
                @unlink($qrTempFile);
            }
        } catch (Throwable $e) {
        }
        ob_end_clean();

        $logoHtml = '';
        if ($logoData) {
            $logoHtml = '<img src="' . htmlspecialchars($logoData) . '" style="width: auto; max-width: 50px; max-height: 50px; position: absolute; left: 30px; top: 10px;">';
        }

        $scheduleHtml = '';
        foreach ($schedule as $idx => $row) {
            $scheduleHtml .= '<tr>'
                . '<td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . ($idx + 1) . '</td>'
                . '<td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt;">' . htmlspecialchars($row['matkul']) . '</td>'
                . '<td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . htmlspecialchars($row['hari']) . '</td>'
                . '<td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . htmlspecialchars($row['jam']) . '</td>'
                . '<td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . htmlspecialchars($row['ruang']) . '</td>'
                . '<td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;"></td>'
                . '</tr>';
        }

        $htmlBody .= '<div style="position: relative; padding-top: 10px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px dashed #999; page-break-inside: avoid;">';
        $htmlBody .= $logoHtml;
        $htmlBody .= '<div style="text-align: center; margin-bottom: 15px; padding: 0 40px 0 110px;">';
        $htmlBody .= '<h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine1) . '</h3>';
        if ($headerLine2 !== '') {
            $htmlBody .= '<h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine2) . '</h3>';
        }
        $htmlBody .= '<h4 style="margin: 5px 0 0 0; font-weight: normal; font-size: 10pt;">' . htmlspecialchars($subTitle) . '</h4>';
        $htmlBody .= '</div>';
        $htmlBody .= '<div style="margin-bottom: 5px; font-weight: bold; font-size: 9pt;">';
        $htmlBody .= '<table style="width: 100%; border: none;"><tr><td style="vertical-align: top;">';
        $htmlBody .= '<table style="width: 100%; border: none;">';
        $htmlBody .= '<tr><td style="width: 70px;">KELAS</td><td>: ' . htmlspecialchars($student['tingkat']) . '</td></tr>';
        $htmlBody .= '<tr><td>NAMA</td><td>: ' . htmlspecialchars($student['nama']) . '</td></tr>';
        $htmlBody .= '<tr><td>NIM</td><td>: ' . htmlspecialchars($student['nim']) . '</td></tr>';
        $htmlBody .= '</table></td><td style="width: 80px; text-align: right; vertical-align: top;">';
        if ($qrData) {
            $htmlBody .= '<img src="' . htmlspecialchars($qrData) . '" style="width: 70px; height: 70px; border: 1px solid #eee;">';
        }
        $htmlBody .= '</td></tr></table></div>';
        $htmlBody .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">';
        $htmlBody .= '<thead><tr>';
        $htmlBody .= '<th style="width: 5%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">No</th>';
        $htmlBody .= '<th style="width: 35%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Mata Kuliah</th>';
        $htmlBody .= '<th style="width: 25%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Hari/Tanggal</th>';
        $htmlBody .= '<th style="width: 15%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Jam</th>';
        $htmlBody .= '<th style="width: 10%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Ruang</th>';
        $htmlBody .= '<th style="width: 10%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">TTD</th>';
        $htmlBody .= '</tr></thead><tbody>' . $scheduleHtml . '</tbody></table>';
        $htmlBody .= '<div style="margin-top: 10px; text-align: center; width: 45%; float: right;">';
        $htmlBody .= '<p style="margin-bottom: 0; font-size: 9pt;">Bogor, ' . htmlspecialchars($signer['tanggal']) . '</p>';
        $htmlBody .= '<p style="margin-bottom: 40px; font-size: 9pt;">Mengetahui<br>' . htmlspecialchars($signer['institusi']) . '<br>' . htmlspecialchars($signer['jabatan']) . '</p>';
        $htmlBody .= '<p style="font-weight: bold; text-decoration: underline; font-size: 9pt;">' . htmlspecialchars($signer['nama']) . '</p>';
        $htmlBody .= '</div><div style="clear: both;"></div></div>';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Jadwal Ujian</title><style>@page { margin: 1cm 1.5cm; size: 215mm 330mm; } body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; margin: 0; padding: 0; }</style></head><body>' . $htmlBody . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    ob_start();
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper([0, 0, 609.4488, 935.433], 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();
    ob_end_clean();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode([
        'success' => true,
        'message' => 'PDF berhasil dibuat dengan ' . count($students) . ' halaman',
        'pdf_data' => base64_encode($pdfContent),
        'filename' => 'Jadwal_Ujian_' . $sourceLabel . '_' . date('YmdHis') . '.pdf',
    ]);
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'pdf_data' => null,
        'filename' => '',
    ]);
    exit;
}
