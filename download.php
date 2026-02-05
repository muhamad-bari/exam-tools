<?php
require_once "lib/pdf_helper.php";

$filenameRequest = isset($_GET['file']) ? $_GET['file'] : 'data.csv';

// Sanitize filename strictly (basename only)
$filenameRequest = basename($filenameRequest);
$csvPath = 'uploads/' . $filenameRequest;

// Construct the expected PDF path
$fileBaseName = pathinfo($filenameRequest, PATHINFO_FILENAME);
$pdfPath = 'results/QR_' . $fileBaseName . '.pdf';

// 1. Check if PDF already exists
if (file_exists($pdfPath)) {
    // Serve the existing PDF
    $timestamp = date('dmY_Hi');
    servePdf($pdfPath, "QR_" . $fileBaseName . "_" . $timestamp . ".pdf");
    exit;
}

// 2. If not, try to generate it now
if (file_exists($csvPath)) {
    if (generatePDF($csvPath, $pdfPath)) {
        $timestamp = date('dmY_Hi');
        servePdf($pdfPath, "QR_" . $fileBaseName . "_" . $timestamp . ".pdf");
        exit;
    } else {
        die("Failed to generate PDF from CSV.");
    }
} else {
    die("File not found: " . htmlspecialchars($filenameRequest));
}

function servePdf($filepath, $downloadName) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
}
