<?php
// CRITICAL: NO WHITESPACE BEFORE OR AFTER PHP TAGS!
// Set headers immediately
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

// Buffering for safety
ob_start();

// Configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('memory_limit', '512M');
set_time_limit(300);

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    // Session
    session_start();
    
    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    }
    
    if (!isset($_POST['generate_pdf'])) {
        throw new Exception('Missing generate_pdf parameter');
    }
    
    // Check dependencies
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception('Vendor autoload not found');
    }
    
    if (!file_exists(__DIR__ . '/phpqrcode/qrlib.php')) {
        throw new Exception('PHPQRCode library not found');
    }
    
// Load libraries
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/phpqrcode/qrlib.php';
    
    // ====== 1. PROCESS FORM DATA ======
    
    // Header & Title
    $headerLine1 = !empty($_POST['header_line1']) ? strtoupper(trim($_POST['header_line1'])) : 'AKADEMI KEBIDANAN WIJAYA HUSADA';
    $headerLine2 = !empty($_POST['header_line2']) ? strtoupper(trim($_POST['header_line2'])) : '';
    $subTitle = !empty($_POST['sub_title']) ? trim($_POST['sub_title']) : 'JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025';
    
    // Signer data
    $signer = [
        'nama' => !empty($_POST['signer_name']) ? trim($_POST['signer_name']) : 'Elpinaria Girsang, S.ST., M.K.M.',
        'jabatan' => !empty($_POST['signer_title']) ? trim($_POST['signer_title']) : 'Direktur',
        'institusi' => !empty($_POST['signer_institution']) ? trim($_POST['signer_institution']) : 'Akademi Kebidanan Wijaya Husada',
        'tanggal' => !empty($_POST['signer_date']) ? trim($_POST['signer_date']) : date('d F Y')
    ];
    
    // Process Logo
    $logoData = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $logoPath = $_FILES['logo']['tmp_name'];
        if (file_exists($logoPath)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($logoPath);
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        }
    }
    
    // ====== 2. PROCESS SCHEDULE DATA ======
    
    $schedule = [];
    
    $matkuls = isset($_POST['matkul']) && is_array($_POST['matkul']) ? $_POST['matkul'] : [];
    $haris = isset($_POST['hari']) && is_array($_POST['hari']) ? $_POST['hari'] : [];
    $jams = isset($_POST['jam']) && is_array($_POST['jam']) ? $_POST['jam'] : [];
    $ruangs = isset($_POST['ruang']) && is_array($_POST['ruang']) ? $_POST['ruang'] : [];
    
    $maxCount = max(count($matkuls), count($haris), count($jams), count($ruangs));
    
    if ($maxCount === 0) {
        throw new Exception('Minimal harus ada satu mata kuliah yang diisi');
    }
    
    for ($i = 0; $i < $maxCount; $i++) {
        $matkul = isset($matkuls[$i]) ? trim($matkuls[$i]) : '';
        $hari = isset($haris[$i]) ? trim($haris[$i]) : '';
        $jam = isset($jams[$i]) ? trim($jams[$i]) : '';
        $ruang = isset($ruangs[$i]) ? trim($ruangs[$i]) : '';
        
        if (!empty($matkul) || !empty($hari)) {
            $schedule[] = [
                'matkul' => $matkul,
                'hari' => $hari,
                'jam' => $jam,
                'ruang' => $ruang
            ];
        }
    }
    
    if (empty($schedule)) {
        throw new Exception('Tidak ada mata kuliah yang valid');
    }
    
    // ====== 3. PROCESS CSV DATA ======
    
    if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== 0) {
        throw new Exception('File CSV tidak diunggah atau ada error');
    }
    
    $csvPath = $_FILES['student_csv']['tmp_name'];
    if (!file_exists($csvPath)) {
        throw new Exception('File CSV tidak ditemukan');
    }
    
    $students = [];
    $handle = fopen($csvPath, 'r');
    
    if ($handle === false) {
        throw new Exception('Gagal membuka file CSV');
    }
    
    // Skip header
    $header = fgetcsv($handle, 10000, ',');
    if (!is_array($header) || count($header) < 2) {
        fclose($handle);
        throw new Exception('Format CSV tidak valid');
    }
    
    // Parse data
    while (($row = fgetcsv($handle, 10000, ',')) !== false) {
        if (count($row) < 2) {
            continue;
        }
        
        // Trim all values
        $row = array_map(function($val) { return trim($val); }, $row);
        
        // Get columns
        $col0 = isset($row[0]) ? $row[0] : '';
        $col1 = isset($row[1]) ? $row[1] : '';
        $col2 = isset($row[2]) ? $row[2] : '';
        $col3 = isset($row[3]) ? $row[3] : '-';
        
        // Skip empty rows
        if (empty($col1) && empty($col2)) {
            continue;
        }
        
        // Smart detect: NIM is numeric >= 6 digits
        $cleanNum = preg_replace('/[^0-9]/', '', $col1);
        
        if (strlen($cleanNum) >= 6 && is_numeric($cleanNum)) {
            // col1 = NIM, col2 = Name
            $data = [
                'nim' => $col1,
                'nama' => $col2,
                'tingkat' => $col3
            ];
        } else {
            // col1 = Name, col2 = NIM
            $data = [
                'nim' => $col2,
                'nama' => $col1,
                'tingkat' => $col3
            ];
        }
        
        // Validate
        if (!empty($data['nama']) && !empty(preg_replace('/[^0-9]/', '', $data['nim']))) {
            $students[] = (object)$data;
        }
    }
    fclose($handle);
    
    if (empty($students)) {
        throw new Exception('CSV tidak memiliki data mahasiswa yang valid');
    }
    
    // ====== 4. GENERATE PDF ======
    
    $htmlBody = '';
    $studentCount = count($students);
    
    // Helper: render single student card
    foreach ($students as $student) {
        // Generate QR code
        $qrContent = $student->nama . '_' . $student->tingkat . '_' . $student->nim;
        $qrData = '';
        
        ob_start();
        try {
            $qrTempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
            @QRcode::png($qrContent, $qrTempFile, QR_ECLEVEL_L, 3, 2);
            if (file_exists($qrTempFile)) {
                $qrData = 'data:image/png;base64,' . base64_encode(file_get_contents($qrTempFile));
                @unlink($qrTempFile);
            }
        } catch (Exception $e) {
            // QR is optional, continue if failed
        }
        ob_end_clean();
        
        // Build HTML for this student
        $logoHtml = '';
        if ($logoData) {
            $logoHtml = '<img src="' . htmlspecialchars($logoData) . '" style="width: auto; max-width: 50px; max-height: 50px; position: absolute; left: 30px; top: 10px;">';
        }
        
        $scheduleHtml = '';
        foreach ($schedule as $idx => $s) {
            $scheduleHtml .= '<tr>
                <td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . ($idx + 1) . '</td>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt;">' . htmlspecialchars($s['matkul']) . '</td>
                <td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . htmlspecialchars($s['hari']) . '</td>
                <td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . htmlspecialchars($s['jam']) . '</td>
                <td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;">' . htmlspecialchars($s['ruang']) . '</td>
                <td style="border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt;"></td>
            </tr>';
        }
        
        $htmlBody .= '
            <div style="position: relative; padding-top: 10px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px dashed #999; page-break-inside: avoid;">
                ' . $logoHtml . '
                <div style="text-align: center; margin-bottom: 15px; padding: 0 40px 0 110px;">
                    <h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine1) . '</h3>
                    ' . ($headerLine2 ? '<h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine2) . '</h3>' : '') . '
                    <h4 style="margin: 5px 0 0 0; font-weight: normal; font-size: 10pt;">' . htmlspecialchars($subTitle) . '</h4>
                </div>
                
                <div style="margin-bottom: 5px; font-weight: bold; font-size: 9pt;">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="vertical-align: top;">
                                <table style="width: 100%; border: none;">
                                    <tr><td style="width: 70px;">KELAS</td><td>: ' . htmlspecialchars($student->tingkat) . '</td></tr>
                                    <tr><td>NAMA</td><td>: ' . htmlspecialchars($student->nama) . '</td></tr>
                                    <tr><td>NIM</td><td>: ' . htmlspecialchars($student->nim) . '</td></tr>
                                </table>
                            </td>
                            <td style="width: 80px; text-align: right; vertical-align: top;">
                                ' . ($qrData ? '<img src="' . htmlspecialchars($qrData) . '" style="width: 70px; height: 70px; border: 1px solid #eee;">' : '') . '
                            </td>
                        </tr>
                    </table>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
                    <thead>
                        <tr>
                            <th style="width: 5%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">No</th>
                            <th style="width: 35%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Mata Kuliah</th>
                            <th style="width: 25%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Hari/Tanggal</th>
                            <th style="width: 15%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Jam</th>
                            <th style="width: 10%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">Ruang</th>
                            <th style="width: 10%; border: 1px solid black; padding: 3px 4px; text-align: center; font-size: 9pt; background-color: #eee; font-weight: bold;">TTD</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $scheduleHtml . '
                    </tbody>
                </table>
                
                <div style="margin-top: 10px; text-align: center; width: 45%; float: right;">
                    <p style="margin-bottom: 0; font-size: 9pt;">Jakarta, ' . htmlspecialchars($signer['tanggal']) . '</p>
                    <p style="margin-bottom: 40px; font-size: 9pt;">Mengetahui<br>' . htmlspecialchars($signer['institusi']) . '<br>' . htmlspecialchars($signer['jabatan']) . '</p>
                    <p style="font-weight: bold; text-decoration: underline; font-size: 9pt;">' . htmlspecialchars($signer['nama']) . '</p>
                </div>
                <div style="clear: both;"></div>
            </div>
        ';
    }
    
    // Full HTML
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Jadwal Ujian</title>
        <style>
            @page { margin: 1cm 1.5cm; size: 215mm 330mm; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; margin: 0; padding: 0; }
        </style>
    </head>
    <body>
    ' . $htmlBody . '
    </body>
    </html>';
    
    // Generate PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    
    ob_start();
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    // 215mm x 330mm = 609.45pt x 935.43pt
    $dompdf->setPaper(array(0, 0, 609.4488, 935.433), 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();
    ob_end_clean();
    
    // Clean all buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Send JSON response
    echo json_encode([
        'success' => true,
        'message' => 'PDF berhasil dibuat dengan ' . $studentCount . ' halaman',
        'pdf_data' => base64_encode($pdfContent),
        'filename' => 'Jadwal_Ujian_' . date('YmdHis') . '.pdf'
    ]);
    
    exit(0);
    
} catch (Throwable $e) {
    // Clean all buffers before error response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    exit(1);
}
