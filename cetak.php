<?php
include "phpqrcode/qrlib.php";
include "pdf/fpdf.php";

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
$pdf = new FPDF("P", "mm", "A4");
$pdf->AddPage();
$pdf->SetMargins(5,5,5);
$pdf->SetAutoPageBreak(true, 5);
$pdf->SetXY(5, 5);
$width = 40;
$height = 40;
$pdf->SetFont("times","","10");
foreach($data as $row) {
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $qr = Qrcode::png($row["isiqr"],$qrDir.$row["isiqr"].".png");
    $pdf->Rect($x, $y, $width+5, $height+30);
    $pdf->Image(__DIR__."/qr/".$row["isiqr"].".png", $x+2.5, $y+2.5, $width, $height);
    $pdf->Text($x+5, $y+$width+5, $row["nim"]);
    $pdf->Text($x+5, $y+$width+10, $row["nama"]);
    $pdf->Text($x+5, $y+$width+15, $row["kelas"]);
    $pdf->SetX($pdf->GetX() + $width+12);
    if($pdf->GetX() > $pdf->GetPageWidth()-$width) {
        $pdf->SetY($pdf->GetY()+$height+40);
    }
    unlink(__DIR__."/qr/".$row["isiqr"].".png");
}
$pdf->Output("QR.pdf", "I");
?>