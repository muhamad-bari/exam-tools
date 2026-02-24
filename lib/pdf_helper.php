<?php
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../phpqrcode/qrlib.php";
use Dompdf\Dompdf;

function generatePDF($csvPath, $outputPdfPath) {
    if (!file_exists($csvPath)) {
        return false;
    }

    $data = [];
    if (($csv = fopen($csvPath, 'r')) !== FALSE) {
        // Skip Header
        $header = fgetcsv($csv, 10000, ",");
        
        while (($row = fgetcsv($csv, 10000, ",")) !== FALSE) {
            $nim = ""; $nama = ""; $kelas = "-";

            // Logic parsing (must match index.php/download.php logic)
            if (count($row) >= 3) {
                 if (is_numeric(str_replace([' ', '-'], '', $row[1]))) {
                    $nim = $row[1];
                    $nama = $row[2];
                    if (isset($row[3])) $kelas = $row[3];
                 } else {
                    $nama = $row[1];
                    $nim = $row[2];
                    if (isset($row[3])) $kelas = $row[3];
                 }
            } else {
                continue;
            }

            if (empty($nama) || empty($nim)) continue;

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
        return false;
    }

    // Render HTML
    // We use buffering to capture the output of dompdf_dom.php
    ob_start();
    include __DIR__ . "/../dompdf_dom.php";
    $html = ob_get_clean();

    // Generate PDF
    $dompdf = new Dompdf();
    $dompdf->set_option('isRemoteEnabled', true);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save to file
    file_put_contents($outputPdfPath, $dompdf->output());
    
    return true;
}
