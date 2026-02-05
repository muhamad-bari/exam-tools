<?php
session_start();
require "vendor/autoload.php";
include "phpqrcode/qrlib.php";

$data = [];
$showSidebar = false;
$toastMessage = null;
$toastType = null;

// Handle Clear Action
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    if (file_exists('uploads/data.csv')) {
        unlink('uploads/data.csv');
    }
    header("Location: index.php");
    exit;
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['filecsv'])) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    // Normalize files array structure
    $files = $_FILES['filecsv'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    $processedFiles = 0;
    
    // Clear old files if new upload (optional, but keeps folder clean)
    // array_map('unlink', glob("$uploadDir*.csv")); 

    for ($i = 0; $i < $fileCount; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

        if ($error === 0) {
            // Save individual file
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $destPath = $uploadDir . $safeName;
            
            if (move_uploaded_file($tmpName, $destPath)) {
                
                // Read this specific file
                if (($handle = fopen($destPath, 'r')) !== FALSE) {
                    // Skip Header
                    fgetcsv($handle, 10000, ",");
                    
                    $fileHasData = false;
                    while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                        $nim = ""; $nama = ""; $kelas = "-";

                        // Smart detection logic
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
                        $isiqr = $nim . " - " . $namep . " - " . $kelasp;

                        // Add to display data with Source Info
                        $data[] = [
                            "nama"  => $nama,
                            "nim"   => $nim,
                            "kelas" => $kelas,
                            "isiqr" => $isiqr,
                            "source"=> $name,      // Display name
                            "file"  => $safeName   // Saved filename for download link
                        ];
                        
                        $fileHasData = true;
                    }
                    fclose($handle);
                    if ($fileHasData) $processedFiles++;
                }
            }
        }
    }
    
    if ($processedFiles > 0) {
        $showSidebar = true;
        $totalRecords = count($data);
        $toastMessage = "Successfully processed $processedFiles files ($totalRecords records).";
        $toastType = "success";
    } else {
        $toastMessage = "No valid data found in uploaded files.";
        $toastType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="toast-container"></div>

    <div class="container">
        <!-- Left Panel: Upload Form -->
        <div class="main-content">
            <div class="upload-card">
                <h1><i class="fa-solid fa-qrcode"></i> QR Generator</h1>
                <p style="margin-bottom: 20px; color: #636e72;">Drag & drop multiple CSV files here to merge and generate.</p>
                
                <form action="index.php" method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" id="dropZone">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Drag & Drop CSV files here or <span>Browse</span></p>
                        <!-- Added multiple attribute -->
                        <input type="file" name="filecsv[]" id="filecsv" accept=".csv" multiple required>
                    </div>
                    <div id="fileInfo" class="file-info"></div>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 1rem;">
                        <button type="submit" class="btn-submit" style="flex: 2;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Bulk QR
                        </button>
                        <a href="format_QR.csv" class="btn-submit" style="flex: 1; background-color: #95a5a6; text-decoration: none; display: flex; align-items: center; justify-content: center;" download>
                            <i class="fa-solid fa-file-csv" style="margin-right: 5px;"></i> Template
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Panel: Sidebar Results -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div>
                    <h2>Results Preview</h2>
                    <?php if($showSidebar && !empty($data)): ?>
                        <span style="font-size: 0.85rem; color: #7f8c8d; font-weight: 500;">
                            <i class="fa-solid fa-database" style="color: #3498db; margin-right: 4px;"></i> 
                            <?= count($data) ?> Records found
                        </span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 10px;">
                    <?php if($showSidebar && !empty($data)): ?>
                        <a href="index.php?action=clear" class="btn-download" style="background-color: #e74c3c;" title="Clear All">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar-content">
                <?php if ($showSidebar && !empty($data)): ?>
                    <?php 
                    // Group data by file source
                    $groupedData = [];
                    foreach($data as $row) {
                        $source = $row['source'];
                        if (!isset($groupedData[$source])) {
                            $groupedData[$source] = [
                                'file' => $row['file'], // Safe filename for download
                                'items' => []
                            ];
                        }
                        $groupedData[$source]['items'][] = $row;
                    }
                    
                    $sectionId = 0;
                    foreach($groupedData as $sourceName => $group): 
                        $sectionId++;
                    ?>
                        <div class="file-section">
                            <!-- Header with Toggle -->
                            <div class="file-header" onclick="toggleSection('section-<?= $sectionId ?>')">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-chevron-down toggle-icon" id="icon-section-<?= $sectionId ?>"></i>
                                    <span style="font-size: 0.85rem; font-weight: bold; color: #2c3e50;">
                                        <i class="fa-regular fa-file-lines" style="margin-right: 5px;"></i> 
                                        <?= htmlspecialchars($sourceName) ?>
                                        <span style="font-size: 0.7rem; color: #7f8c8d; font-weight: normal; margin-left: 5px;">
                                            (<?= count($group['items']) ?>)
                                        </span>
                                    </span>
                                </div>
                                
                                <!-- Stop propagation to prevent collapse when clicking download -->
                                <a href="download.php?file=<?= urlencode($group['file']) ?>" target="_blank" onclick="event.stopPropagation();" style="font-size: 0.75rem; text-decoration: none; color: #fff; background-color: #27ae60; padding: 4px 8px; border-radius: 4px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-download"></i> PDF
                                </a>
                            </div>

                            <!-- Collapsible Content -->
                            <div id="section-<?= $sectionId ?>" class="file-items">
                                <?php foreach($group['items'] as $row): ?>
                                    <?php 
                                    ob_start();
                                    QRcode::png($row["isiqr"], null, QR_ECLEVEL_L, 3, 2);
                                    $imageString = ob_get_clean();
                                    $base64 = base64_encode($imageString);
                                    ?>
                                    <div class="preview-item">
                                        <img src="data:image/png;base64,<?= $base64 ?>" alt="QR Code" class="qr">
                                        <div class="preview-details">
                                            <table>
                                                <tr><td><strong>NIM</strong></td><td>: <?= htmlspecialchars($row["nim"]) ?></td></tr>
                                                <tr><td><strong>Nama</strong></td><td>: <?= htmlspecialchars($row["nama"]) ?></td></tr>
                                                <tr><td><strong>Kelas</strong></td><td>: <?= htmlspecialchars($row["kelas"]) ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-file-image" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>No data generated yet.</p>
                        <p style="font-size: 0.8rem;">Drag & Drop multiple CSV files to merge them.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .file-section {
            margin-bottom: 15px;
        }
        .file-header {
            background: #eef2f3; 
            padding: 10px 12px; 
            border-radius: 6px; 
            margin-bottom: 5px;
            border-left: 4px solid #3498db; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        .file-header:hover {
            background: #e1e8ea;
        }
        .toggle-icon {
            color: #3498db;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }
        .file-items {
            display: block;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .collapsed .file-items {
            display: none;
        }
    </style>

    <script>
        function toggleSection(id) {
            const content = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            
            if (content.style.display === "none") {
                content.style.display = "block";
                icon.style.transform = "rotate(0deg)";
            } else {
                content.style.display = "none";
                icon.style.transform = "rotate(-90deg)";
            }
        }

        // Console Logging Helper
        function log(message, data = null) {
            const timestamp = new Date().toLocaleTimeString();
            if (data) {
                console.log(`[${timestamp}] ${message}`, data);
            } else {
                console.log(`[${timestamp}] ${message}`);
            }
        }

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('filecsv');
        const fileInfo = document.getElementById('fileInfo');
        const uploadForm = document.getElementById('uploadForm');

        // Toast Notification Logic
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let iconClass = 'fa-circle-info';
            if (type === 'success') iconClass = 'fa-circle-check';
            if (type === 'error') iconClass = 'fa-triangle-exclamation';

            toast.innerHTML = `
                <i class="fa-solid ${iconClass} toast-icon"></i>
                <div class="toast-content">
                    <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;

            container.appendChild(toast);
            log(`Toast displayed: ${type} - ${message}`);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.transition = 'all 0.5s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-150%)'; // Move up
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        // Check for server-side messages
        <?php if ($toastMessage): ?>
            showToast("<?= addslashes($toastMessage) ?>", "<?= $toastType ?>");
        <?php endif; ?>

        // Trigger file input when clicking the drop zone
        dropZone.addEventListener('click', () => fileInput.click());

        // Handle drag events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('dragover');
        }

        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }

        // Handle file drop
        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            log('Files dropped', files);
            handleFiles(files);
        }

        // Handle standard input change
        fileInput.addEventListener('change', function() {
            log('Files selected via dialog', this.files);
            handleFiles(this.files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                // Assign to input if dropped
                if (fileInput.files !== files) {
                    fileInput.files = files;
                }

                let validCount = 0;
                let fileNames = [];
                
                for(let i=0; i<files.length; i++) {
                    if (files[i].type === "text/csv" || files[i].name.endsWith('.csv')) {
                        validCount++;
                        fileNames.push(files[i].name);
                    }
                }

                if (validCount === 0) {
                    const msg = "Please upload valid CSV files.";
                    showToast(msg, 'error');
                    fileInput.value = ""; // Clear input
                    fileInfo.style.display = 'none';
                    return;
                }

                fileInfo.style.display = 'block';
                if (validCount === 1) {
                    fileInfo.innerHTML = `<i class="fa-solid fa-file-csv"></i> <strong>Selected:</strong> ${fileNames[0]}`;
                } else {
                    fileInfo.innerHTML = `<i class="fa-solid fa-layer-group"></i> <strong>${validCount} Files Selected:</strong><br><small>${fileNames.join(', ')}</small>`;
                }
                
                log('Files ready for upload', fileNames);
            }
        }

        uploadForm.addEventListener('submit', function(e) {
            if (fileInput.files.length === 0) {
                e.preventDefault();
                showToast('Please select at least one file.', 'error');
            }
        });
    </script>
</body>
</html>