<?php
/**
 * API Handler untuk PDF Generation dengan validation error
 * File ini akan menangani form submission dan memberikan response JSON
 */

// Prevent any output before sending headers
ob_start();

// Prevent any PHP warning/notice from being output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header FIRST before anything else
header('Content-Type: application/json; charset=utf-8');

session_start();

ini_set('memory_limit', '512M');
set_time_limit(300);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_pdf'])) {
        throw new Exception('Invalid request method');
    }

    // Check if vendor/autoload.php exists
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception('Missing vendor/autoload.php - Jalankan "composer install" di root folder');
    }

    require_once "vendor/autoload.php";
    use Dompdf\Dompdf;
    use Dompdf\Options;

    // Check if phpqrcode library exists
    if (!file_exists('phpqrcode/qrlib.php')) {
        throw new Exception('Missing phpqrcode/qrlib.php - File library tidak ditemukan');
    }
    
    require_once "phpqrcode/qrlib.php";

    // 1. Process Logo
    $logoData = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $path = $_FILES['logo']['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    // 2. Process Header & Signer Data
    $headerLine1 = strtoupper($_POST['header_line1'] ?? 'AKADEMI KEBIDANAN WIJAYA HUSADA');
    $headerLine2 = strtoupper($_POST['header_line2'] ?? '');
    $subTitle    = $_POST['sub_title'] ?? 'JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025';
    
    $penandaTangan = [
        'nama'      => $_POST['signer_name'] ?? 'Elpinaria Girsang, S.ST., M.K.M.',
        'jabatan'   => $_POST['signer_title'] ?? 'Direktur',
        'institusi' => $_POST['signer_institution'] ?? 'Akademi Kebidanan Wijaya Husada',
        'tanggal'   => $_POST['signer_date'] ?? date('d F Y')
    ];

    // 3. Process Schedule Data
    $jadwal = [];
    $matkuls = isset($_POST['matkul']) && is_array($_POST['matkul']) ? $_POST['matkul'] : [];
    $haris = isset($_POST['hari']) && is_array($_POST['hari']) ? $_POST['hari'] : [];
    $jams = isset($_POST['jam']) && is_array($_POST['jam']) ? $_POST['jam'] : [];
    $ruangs = isset($_POST['ruang']) && is_array($_POST['ruang']) ? $_POST['ruang'] : [];
    
    $count = max(count($matkuls), count($haris), count($jams), count($ruangs));
    
    if ($count === 0) {
        throw new Exception('Minimal harus ada satu mata kuliah yang diisi!');
    }
    
    for ($i = 0; $i < $count; $i++) {
        $matkul = isset($matkuls[$i]) ? trim($matkuls[$i]) : '';
        $hari = isset($haris[$i]) ? trim($haris[$i]) : '';
        $jam = isset($jams[$i]) ? trim($jams[$i]) : '';
        $ruang = isset($ruangs[$i]) ? trim($ruangs[$i]) : '';
        
        if (!empty($matkul) || !empty($hari)) {
            $jadwal[] = [
                'hari'   => $hari,
                'matkul' => $matkul,
                'jam'    => $jam,
                'ruang'  => $ruang
            ];
        }
    }
    
    if (empty($jadwal)) {
        throw new Exception('Tidak ada mata kuliah yang valid! Pastikan minimal satu baris terisi.');
    }

    // 4. Process CSV Data (Bulk Students)
    $students = [];
    
    if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== 0) {
        throw new Exception('File CSV tidak diunggah! Pastikan Anda sudah memilih file CSV dengan data mahasiswa.');
    }
    
    if (($handle = fopen($_FILES['student_csv']['tmp_name'], "r")) === FALSE) {
        throw new Exception('Gagal membuka file CSV!');
    }
    
    // Skip header row
    $header = fgetcsv($handle, 10000, ",");
    if (!$header) {
        fclose($handle);
        throw new Exception('File CSV kosong atau format tidak valid!');
    }
    
    $lineNumber = 2; // Track line number for error reporting
    
    while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $lineNumber++;
        
        // Skip completely empty rows
        if (count($row) < 4 || empty(implode('', $row))) {
            continue;
        }
        
        // Trim whitespace
        $row = array_map('trim', $row);
        
        // Standard format: No (0), Nama (1), NIM (2), Kelas (3)
        $nama = $row[1] ?? '';
        $nim = $row[2] ?? '';
        $kelas = $row[3] ?? '-';
        
        if (empty($nama) || empty($nim)) {
            continue;
        }
        
        // Validate
        $cleanNim = preg_replace('/[^0-9]/', '', $nim);
        if (!empty($nama) && !empty($cleanNim)) {
            $students[] = (object)[
                'nama' => $nama,
                'nim' => $nim,
                'tingkat' => $kelas
            ];
        }
    }
    fclose($handle);
    
    if (empty($students)) {
        throw new Exception('File CSV tidak memiliki data mahasiswa yang valid! Baris harus memiliki format: No, NIM, Nama, Kelas');
    }

    require_once "phpqrcode/qrlib.php";

    // Helper function to render a single card
    function renderCard($student, $logoData, $headerLine1, $headerLine2, $subTitle, $jadwal, $penandaTangan) {
        $imgHtml = '';
        if ($logoData) {
            $imgHtml = '<img src="' . $logoData . '" style="width: auto; max-width: 70px; max-height: 70px; position: absolute; left: 30px; top: 10px;">';
        }

        // Generate QR Code
        $qrContent = $student->nama . '_' . $student->tingkat . '_' . $student->nim;
        $qrData = ''; // Default: no QR
        
        // Use output buffering for QR generation to prevent unwanted output
        ob_start();
        try {
            $qrTempFile = tempnam(sys_get_temp_dir(), 'qr') . '.png';
            if ($qrTempFile === false) {
                throw new Exception('Failed to create temp file for QR code');
            }
            
            @QRcode::png($qrContent, $qrTempFile, QR_ECLEVEL_L, 3, 2);
            
            if (file_exists($qrTempFile)) {
                $qrData = 'data:image/png;base64,' . base64_encode(file_get_contents($qrTempFile));
                @unlink($qrTempFile);
            }
        } catch (Exception $e) {
            // Silently fail - QR is optional
        }
        ob_end_clean(); // Clean any output from QR generation

        $html = '
        <div class="card">
            ' . $imgHtml . '
            <div class="header" style="text-align: center; margin-bottom: 15px; padding: 0 40px 0 110px;">
                <h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine1) . '</h3>
                ' . ($headerLine2 ? '<h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine2) . '</h3>' : '') . '
                <h4 style="margin: 5px 0 0 0; font-weight: normal; font-size: 10pt;">' . htmlspecialchars($subTitle) . '</h4>
            </div>

            <div class="student-info" style="margin-bottom: 5px; font-weight: bold; font-size: 9pt;">
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
                            ' . ($qrData ? '<img src="' . $qrData . '" style="width: 70px; height: 70px; border: 1px solid #eee;">' : '') . '
                        </td>
                    </tr>
                </table>
            </div>

            <table class="schedule-table" style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
                <thead>
                    <tr>
                        <th style="width: 5%; border: 1px solid black; padding: 3px 4px; font-size: 9pt; background-color: #eee; text-align: center; font-weight: bold;">No</th>
                        <th style="width: 35%; border: 1px solid black; padding: 3px 4px; font-size: 9pt; background-color: #eee; text-align: center; font-weight: bold;">Mata Kuliah</th>
                        <th style="width: 25%; border: 1px solid black; padding: 3px 4px; font-size: 9pt; background-color: #eee; text-align: center; font-weight: bold;">Hari/Tanggal</th>
                        <th style="width: 15%; border: 1px solid black; padding: 3px 4px; font-size: 9pt; background-color: #eee; text-align: center; font-weight: bold;">Jam</th>
                        <th style="width: 10%; border: 1px solid black; padding: 3px 4px; font-size: 9pt; background-color: #eee; text-align: center; font-weight: bold;">Ruang</th>
                        <th style="width: 10%; border: 1px solid black; padding: 3px 4px; font-size: 9pt; background-color: #eee; text-align: center; font-weight: bold;">TTD</th>
                    </tr>
                </thead>
                <tbody>';
                
        $no = 1;
        foreach ($jadwal as $row) {
            $html .= '<tr>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt; text-align: center;">'.$no++.'</td>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt;">'.htmlspecialchars($row['matkul']).'</td>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt; text-align: center;">'.htmlspecialchars($row['hari']).'</td>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt; text-align: center;">'.htmlspecialchars($row['jam']).'</td>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt; text-align: center;">'.htmlspecialchars($row['ruang']).'</td>
                <td style="border: 1px solid black; padding: 3px 4px; font-size: 9pt;"></td>
            </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <div class="footer" style="margin-top: 10px; text-align: center; float: right; width: 45%;">
                <p style="margin-bottom: 0; font-size: 9pt;">Jakarta, ' . htmlspecialchars($penandaTangan['tanggal']) . '</p>
                <p style="margin-bottom: 40px; font-size: 9pt;">Mengetahui<br>' . htmlspecialchars($penandaTangan['institusi']) . '<br>' . htmlspecialchars($penandaTangan['jabatan']) . '</p>
                <p style="font-weight: bold; text-decoration: underline; font-size: 9pt;">' . htmlspecialchars($penandaTangan['nama']) . '</p>
            </div>
            <div style="clear: both;"></div>
        </div>';
        
        return $html;
    }

    // Generate full HTML
    $fullHtmlBody = '';
    $studentCount = count($students);
    
    foreach ($students as $student) {
        $fullHtmlBody .= renderCard($student, $logoData, $headerLine1, $headerLine2, $subTitle, $jadwal, $penandaTangan);
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Kartu Jadwal Ujian</title>
        <style>
            @page { margin: 1cm 1.5cm; size: A4 portrait; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; }
            
            .card {
                position: relative;
                padding-top: 10px;
                padding-bottom: 20px;
                margin-bottom: 20px;
                border-bottom: 1px dashed #999;
                page-break-inside: avoid;
                page-break-after: always;
            }
            .card:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
        </style>
    </head>
    <body>
        ' . $fullHtmlBody . '
    </body>
    </html>';

    // Generate PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    
    ob_start(); // Buffer PDF generation in case of warnings
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfOutput = $dompdf->output();
    ob_end_clean(); // Clean any warnings from dompdf
    
    // Clean ALL buffered output before sending JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'PDF berhasil dibuat dengan ' . $studentCount . ' halaman',
        'pdf_data' => base64_encode($pdfOutput),
        'filename' => 'Jadwal_Ujian_' . date('YmdHis') . '.pdf'
    ]);
    exit;
    
} catch (Exception $e) {
    http_response_code(400);
    
    // Clean ALL buffered output before sending JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => false,
        'message' => htmlspecialchars($e->getMessage()),
        'details' => htmlspecialchars($e->getTraceAsString())
    ]);
    exit;
}

// Should not reach here
exit(0);
?>
