<?php
require "vendor/autoload.php";
use Dompdf\Dompdf;
include "phpqrcode/qrlib.php";


$csv = fopen($_FILES["filecsv"]["tmp_name"],'r');
$header = fgetcsv($csv,"10000",",");
$data = [];
while($row = fgetcsv($csv, 10000,",")) {
    $namep = str_replace(" ","_", $row[1]);
    $kelasp = str_replace(" ","_", $row[3]);
    $data[] = [
        "nama" => $row[1],
        "nim" => $row[2],
        "kelas" => $row[3],
        "isiqr" => $namep." - ".$kelasp
    ];
}
$qrDir = __DIR__ . "/qr/"; 
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}
ob_start();
include 'dompdf_dom.php';
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("QR Code.pdf", ['Attachment' => 0]);