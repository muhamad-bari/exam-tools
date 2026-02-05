<?php
require "vendor/autoload.php";
use Dompdf\Dompdf;
include "phpqrcode/qrlib.php";

$filenameRequest = isset($_GET['file']) ? $_GET['file'] : 'data.csv';

// Sanitize filename strictly (basename only)
$filenameRequest = basename($filenameRequest);
$csvPath = 'uploads/' . $filenameRequest;

if (!file_exists($csvPath)) {
    die("File not found: " . htmlspecialchars($filenameRequest));
}

$data = [];

if (($csv = fopen($csvPath, 'r')) !== FALSE) {
    // Skip Header
    $header = fgetcsv($csv, 10000, ",");
    
    while (($row = fgetcsv($csv, 10000, ",")) !== FALSE) {
        
        $nim = "";
        $nama = "";
        $kelas = "-";

        // Detect format based on column count and content
        // Format Baru: [0]=>No, [1]=>NIM, [2]=>Nama
        // Format Lama: [0]=>No, [1]=>Nama, [2]=>NIM, [3]=>Kelas
        
        if (count($row) >= 3) {
             // Cek apakah kolom ke-1 terlihat seperti NIM (angka)
             if (is_numeric(str_replace([' ', '-'], '', $row[1]))) {
                $nim = $row[1];
                $nama = $row[2];
                if (isset($row[3])) {
                    $kelas = $row[3];
                }
             } else {
                // Fallback ke format lama: No, Nama, NIM, Kelas
                $nama = $row[1];
                $nim = $row[2];
                if (isset($row[3])) $kelas = $row[3];
             }
        } else {
            continue;
        }

        if (empty($nama) || empty($nim)) {
            continue;
        }

        $namep = str_replace(" ", "_", $nama);
        $kelasp = str_replace(" ", "_", $kelas);
        
        $data[] = [
            "nama"  => $nama,
            "nim"   => $nim,
            "kelas" => $kelas,
            "isiqr" => $nim . " - " . $namep . " - " . $kelasp
        ];
    }
    fclose($csv);
}

if (empty($data)) {
    die("No valid data found to generate PDF.");
}

// Generate HTML for PDF
ob_start();
include 'dompdf_dom.php';
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true); // Enable for images if needed
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Filename Format: QR_{namafilecsv}_{waktutanggaltahun}.pdf
// Extract filename without extension
$fileBaseName = pathinfo($filenameRequest, PATHINFO_FILENAME);
// Timestamp: i(Min) H(Hour) d(Day) m(Month) Y(Year)
$timestamp = date('iHdmY');

$filename = "QR_" . $fileBaseName . "_" . $timestamp . ".pdf";

$dompdf->stream($filename, ['Attachment' => 1]);
?>
