<?php
/**
 * Debug Script untuk test API response
 * Buka di browser: http://localhost/exam-tools/test_api.php
 */

echo "<h1>Testing API Generate Jadwal</h1>";

// Check perlukan files
$files_to_check = [
    "vendor/autoload.php" => "Composer Autoload",
    "phpqrcode/qrlib.php" => "QR Code Library",
    "format_mahasiswa.csv" => "Sample CSV Format",
    "generate_pdf_api.php" => "PDF API Handler",
    "api_generate_jadwal.php" => "Old API Handler (deprecated)",
];

echo "<h2>File Dependencies:</h2>";
echo "<ul>";
foreach ($files_to_check as $file => $desc) {
    $exists = file_exists($file) ? "✓ EXISTS" : "✗ MISSING";
    $status = file_exists($file)
        ? '<span style="color: green;">'
        : '<span style="color: red;">';
    echo "<li>{$status}{$exists}</span> - {$desc}: {$file}</li>";
}
echo "</ul>";

// Check PHP version
echo "<h2>Sistem Info:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Memory Limit: " . ini_get("memory_limit") . "</li>";
echo "<li>Max Upload Size: " . ini_get("upload_max_filesize") . "</li>";
echo "<li>Max POST Size: " . ini_get("post_max_size") . "</li>";
echo "</ul>";

// Check writable directories
echo "<h2>Writable Directories:</h2>";
echo "<ul>";
$dirs = ["uploads/", "qr/", sys_get_temp_dir()];
foreach ($dirs as $dir) {
    $writable = is_writable($dir) ? "✓ WRITABLE" : "✗ NOT WRITABLE";
    $status = is_writable($dir)
        ? '<span style="color: green;">'
        : '<span style="color: red;">';
    echo "<li>{$status}{$writable}</span> - {$dir}</li>";
}
echo "</ul>";

// Test minimal request
echo "<h2>API Test:</h2>";
echo "<p>Buka <strong>jadwal.php</strong> dan isi form dengan data, lalu cek response di browser console (F12).</p>";
echo "<p>Jika masih error, buka tab <strong>Network</strong> di DevTools dan lihat response raw dari api_generate_jadwal.php</p>";
?>
