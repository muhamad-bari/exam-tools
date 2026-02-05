<?php
session_start();
require "vendor/autoload.php";
use Dompdf\Dompdf;
use Dompdf\Options;

// Handle PDF Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf'])) {
    
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
    if (isset($_POST['matkul']) && is_array($_POST['matkul'])) {
        $count = count($_POST['matkul']);
        for ($i = 0; $i < $count; $i++) {
            $jadwal[] = [
                'hari'   => $_POST['hari'][$i] ?? '',
                'matkul' => $_POST['matkul'][$i] ?? '',
                'jam'    => $_POST['jam'][$i] ?? '',
                'ruang'  => $_POST['ruang'][$i] ?? ''
            ];
        }
    }

    // 4. Process CSV Data (Bulk Students)
    $students = [];
    if (isset($_FILES['student_csv']) && $_FILES['student_csv']['error'] === 0) {
        if (($handle = fopen($_FILES['student_csv']['tmp_name'], "r")) !== FALSE) {
            // Check for header row logic if needed, but assuming standard format or simple check
            // Row Format Assumption: No (0), NIM (1), Nama (2), Kelas (3) - based on index.php logic
            // Or Smart Detection similar to index.php
            
            // Skip first line if it looks like header
            $firstRow = fgetcsv($handle, 10000, ",");
            if ($firstRow) {
                 // Simple smart detection from index.php
                 $nimIdx = 1; $nameIdx = 2; $classIdx = 3;
                 
                 // Reuse first row if it's data
                 if (is_numeric(str_replace([' ', '-'], '', $firstRow[1]))) {
                    $students[] = (object)[
                        'nim' => $firstRow[1],
                        'nama' => $firstRow[2],
                        'tingkat' => $firstRow[3] ?? '-'
                    ];
                 }
            }

            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if (count($row) < 3) continue;
                
                // Logic from index.php
                $nim = ""; $nama = ""; $kelas = "-";
                if (is_numeric(str_replace([' ', '-'], '', $row[1]))) {
                    $nim = $row[1];
                    $nama = $row[2];
                    if (isset($row[3])) $kelas = $row[3];
                } else {
                    $nama = $row[1];
                    $nim = $row[2];
                    if (isset($row[3])) $kelas = $row[3];
                }
                
                if (empty($nama) || empty($nim)) continue;

                $students[] = (object)[
                    'nama' => $nama,
                    'nim' => $nim,
                    'tingkat' => $kelas
                ];
            }
            fclose($handle);
        }
    } else {
        // Fallback or Error if no CSV? 
        // Or maybe just generate 1 demo page if no CSV?
        // Let's add at least one dummy if empty so user sees something
        $students[] = (object)['nama' => 'CONTOH MAHASISWA', 'nim' => '12345678', 'tingkat' => 'I'];
    }

    require_once "phpqrcode/qrlib.php"; // Include library

    // ... (rest of code)
    
    // 5. Generate PDF HTML
    // Helper function to render a single card
    function renderCard($student, $logoData, $headerLine1, $headerLine2, $subTitle, $jadwal, $penandaTangan) {
        // Logo HTML
        $imgHtml = '';
        if ($logoData) {
            // Fixed position and size (Max width 70px, Max height 70px to fit corner)
            $imgHtml = '<img src="' . $logoData . '" style="width: auto; max-width: 70px; max-height: 70px; position: absolute; left: 30px; top: 10px;">';
        }

        // Generate QR Code
        $qrContent = $student->nama . '_' . $student->tingkat . '_' . $student->nim;
        $qrTempFile = tempnam(sys_get_temp_dir(), 'qr') . '.png';
        QRcode::png($qrContent, $qrTempFile, QR_ECLEVEL_L, 3, 2);
        $qrData = 'data:image/png;base64,' . base64_encode(file_get_contents($qrTempFile));
        unlink($qrTempFile); // Clean up

        $html = '
        <div class="card">
            ' . $imgHtml . '
            <!-- Header with Padding to avoid Logo Overlap -->
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
                            <img src="' . $qrData . '" style="width: 70px; height: 70px; border: 1px solid #eee;">
                        </td>
                    </tr>
                </table>
            </div>

            <table class="schedule-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 35%;">Mata Kuliah</th>
                        <th style="width: 25%;">Hari/Tanggal</th>
                        <th style="width: 15%;">Jam</th>
                        <th style="width: 10%;">Ruang</th>
                        <th style="width: 10%;">TTD</th>
                    </tr>
                </thead>
                <tbody>';
                
                $no = 1;
                foreach ($jadwal as $row) {
                    $html .= '<tr>
                        <td style="text-align: center;">'.$no++.'</td>
                        <td>'.htmlspecialchars($row['matkul']).'</td>
                        <td style="text-align: center;">'.htmlspecialchars($row['hari']).'</td>
                        <td style="text-align: center;">'.htmlspecialchars($row['jam']).'</td>
                        <td style="text-align: center;">'.htmlspecialchars($row['ruang']).'</td>
                        <td></td>
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

    $fullHtmlBody = '';
    $chunks = array_chunk($students, 2);
    $totalChunks = count($chunks);
    
    foreach ($chunks as $index => $chunk) {
        $fullHtmlBody .= '<!-- Page Start -->';
        
        // Top Card
        $fullHtmlBody .= renderCard($chunk[0], $logoData, $headerLine1, $headerLine2, $subTitle, $jadwal, $penandaTangan);
        
        // Separator
        $fullHtmlBody .= '<div class="separator"></div>';
        
        // Bottom Card (if exists)
        if (isset($chunk[1])) {
            $fullHtmlBody .= renderCard($chunk[1], $logoData, $headerLine1, $headerLine2, $subTitle, $jadwal, $penandaTangan);
        }

        // Page Break (if not last page)
        if ($index < $totalChunks - 1) {
            $fullHtmlBody .= '<div style="page-break-after: always;"></div>';
        }
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
                height: 48%; 
                position: relative;
                padding-top: 5px;
            }
            .separator {
                border-top: 1px dashed #999;
                margin: 10px 0;
                height: 0;
            }
            
            table.schedule-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 5px;
            }
            table.schedule-table th, table.schedule-table td {
                border: 1px solid black;
                padding: 3px 4px; /* Tighter padding */
                font-size: 9pt;
                vertical-align: middle;
            }
            table.schedule-table th {
                background-color: #eee;
                text-align: center;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        ' . $fullHtmlBody . '
    </body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Jadwal_Ujian_Bulk.pdf", ['Attachment' => 0]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generator Jadwal - Exam Tools</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { overflow: hidden; } /* Use internal scrolling */
        .split-container {
            display: flex;
            height: calc(100vh - 60px); /* Adjust for navbar */
            overflow: hidden;
        }
        .left-panel {
            width: 40%;
            background: #fff;
            border-right: 1px solid #dfe6e9;
            overflow-y: auto;
            padding: 20px;
        }
        .right-panel {
            width: 60%;
            background: #525659; /* PDF viewer bg color feel */
            padding: 20px;
            overflow-y: auto;
            display: flex;
            justify-content: center;
        }
        
        /* Preview Card Logic */
        .preview-paper {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            font-family: Arial, sans-serif;
            font-size: 12px;
            transform: scale(0.8); /* Scale down to fit better */
            transform-origin: top center;
        }

        .form-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin-bottom: 10px;
            font-size: 1rem;
            color: var(--primary-color);
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
        }

        /* Form Controls */
        .form-group { margin-bottom: 10px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; color: #444; }
        .form-control { 
            width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; 
        }

        /* Table Input */
        .table-input { width: 100%; border-collapse: collapse; }
        .table-input td { padding: 4px; }
        .table-input input { width: 100%; border: 1px solid #ddd; padding: 6px; border-radius: 4px; }
        
        .btn-action {
            width: 100%; padding: 6px; border: none; border-radius: 4px; color: white; cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include "navbar.php"; ?>
    <div id="toast-container"></div>

    <div class="split-container">
        <!-- LEFT PANEL: SETTINGS FORM -->
        <div class="left-panel">
            <form action="jadwal.php" method="post" enctype="multipart/form-data" target="_blank" id="scheduleForm">
                <input type="hidden" name="generate_pdf" value="true">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;"><i class="fa-solid fa-gears"></i> Konfigurasi</h2>
                </div>

                <!-- 1. Header & Logo -->
                <div class="form-section">
                    <h3>1. Header & Logo</h3>
                    <div class="form-group">
                        <label class="form-label">Institusi (Baris 1)</label>
                        <input type="text" name="header_line1" class="form-control" value="AKADEMI KEBIDANAN WIJAYA HUSADA" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Institusi (Baris 2)</label>
                        <input type="text" name="header_line2" class="form-control" placeholder="(Opsional)" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Judul / Tahun Ajaran</label>
                        <input type="text" name="sub_title" class="form-control" value="JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Upload Logo</label>
                        <div class="upload-area" id="logoDropZone" style="padding: 1.5rem; border: 2px dashed #dfe6e9; border-radius: 6px; text-align: center; cursor: pointer; transition: all 0.3s; background: #fafbfc;">
                            <i class="fa-solid fa-image" style="font-size: 2rem; color: #a4b0be; margin-bottom: 0.5rem;"></i>
                            <p style="margin: 0; font-size: 0.9rem; color: #636e72;">Drag Image here or <span style="color: #3498db; font-weight: 600;">Browse</span></p>
                            <p id="logoFileName" style="margin: 5px 0 0 0; font-size: 0.8rem; color: #27ae60; font-weight: bold; display: none;"></p>
                            <input type="file" name="logo" id="logo" accept="image/*" class="form-control" style="display: none;" onchange="handleLogoSelect(this)">
                        </div>
                    </div>
                </div>

                <!-- 2. Signer Info -->
                <div class="form-section">
                    <h3>2. Penanda Tangan</h3>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="signer_name" class="form-control" value="Elpinaria Girsang, S.ST., M.K.M." oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Institusi (Penanda Tangan)</label>
                        <input type="text" name="signer_institution" class="form-control" value="Akademi Kebidanan Wijaya Husada" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jabatan</label>
                        <input type="text" name="signer_title" class="form-control" value="Direktur" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Tanda Tangan</label>
                        <input type="text" name="signer_date" class="form-control" value="<?= date('d F Y') ?>" oninput="updatePreview()">
                    </div>
                </div>

                <!-- 3. Student Data Source -->
                <div class="form-section">
                    <h3>3. Data Mahasiswa (CSV)</h3>
                    <div class="form-group">
                        <label class="form-label">Upload File CSV</label>
                        <div class="upload-area" id="csvDropZone" style="padding: 1.5rem; border: 2px dashed #dfe6e9; border-radius: 6px; text-align: center; cursor: pointer; transition: all 0.3s; background: #fafbfc;">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: #a4b0be; margin-bottom: 0.5rem;"></i>
                            <p style="margin: 0; font-size: 0.9rem; color: #636e72;">Drag CSV here or <span style="color: #3498db; font-weight: 600;">Browse</span></p>
                            <p id="csvFileName" style="margin: 5px 0 0 0; font-size: 0.8rem; color: #27ae60; font-weight: bold; display: none;"></p>
                            <input type="file" name="student_csv" id="student_csv" accept=".csv" class="form-control" style="display: none;" required onchange="handleCsvSelect(this)">
                        </div>
                        <div style="display: flex; justify-content: flex-end; align-items: top; margin-top: 5px;">
                            <a href="format_mahasiswa.csv" download class="btn-download" style="font-size: 0.8rem; text-decoration: none; color: white !important; padding: 4px 8px; border-radius: 4px; background-color: #3498db;">
                                <i class="fa-solid fa-download"></i> Download Template
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4. Schedule Items -->
                <div class="form-section">
                    <h3>4. Mata Kuliah</h3>
                    <table class="table-input" id="scheduleTable">
                        <tbody id="scheduleBody">
                            <tr>
                                <td><input type="text" name="hari[]" placeholder="Senin, 10 Mar" required oninput="updatePreview()"></td>
                                <td><input type="text" name="matkul[]" placeholder="Mata Kuliah" required oninput="updatePreview()"></td>
                                <td><input type="text" name="jam[]" placeholder="09.00-10.00" oninput="updatePreview()"></td>
                                <td style="width: 60px;"><input type="text" name="ruang[]" placeholder="R.206" oninput="updatePreview()"></td>
                                <td style="width: 30px;"><button type="button" class="btn-action" style="background:#e74c3c" onclick="removeRow(this); updatePreview();"><i class="fa-solid fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn-action" style="background:#27ae60; margin-top: 10px;" onclick="addRow()">
                        <i class="fa-solid fa-plus"></i> Tambah Baris
                    </button>
                </div>

                <div style="margin-top: 20px; margin-bottom: 20px;">
                    <button type="submit" class="btn-submit" style="width: 100%; padding: 12px; font-size: 1.1rem; background: #e74c3c; color: white; border: none; border-radius: 6px; cursor: pointer; transition: background 0.3s;">
                        <i class="fa-solid fa-file-pdf"></i> Generate PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- RIGHT PANEL: PREVIEW -->
        <div class="right-panel">
            <div class="preview-paper">
                <!-- Preview Card Content (Simulating PDF Layout) -->
                <div id="previewCard">
                    <img id="previewLogoImg" src="" style="display:none; width: 60px; height: auto; position: absolute; margin-left: 20px;">
                    
                    <div style="text-align: center; margin-bottom: 20px; padding: 0 40px 0 110px;">
                        <h3 id="prev_h1" style="margin: 0; font-size: 14px;">AKADEMI KEBIDANAN WIJAYA HUSADA</h3>
                        <h3 id="prev_h2" style="margin: 0; font-size: 14px;"></h3>
                        <h4 id="prev_sub" style="margin: 5px 0 0 0; font-weight: normal; font-size: 12px;">JADWAL UJIAN...</h4>
                    </div>

                    <div style="margin-bottom: 10px; font-weight: bold; font-size: 11px; border: 1px dashed #ccc; padding: 5px; display: flex; justify-content: space-between;">
                        <div>
                            [Preview Data Mahasiswa dari CSV]<br>
                            KELAS: I<br>
                            NAMA: CONTOH MAHASISWA<br>
                            NIM: 12345678
                        </div>
                        <div>
                           <img id="prev_qr" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=CONTOH_I_12345678" style="width: 60px; height: 60px; border: 1px solid #ddd;">
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: #eee;">
                                <th style="border: 1px solid #000; padding: 4px; font-size: 11px;">No</th>
                                <th style="border: 1px solid #000; padding: 4px; font-size: 11px;">Mata Kuliah</th>
                                <th style="border: 1px solid #000; padding: 4px; font-size: 11px;">Hari/Tanggal</th>
                                <th style="border: 1px solid #000; padding: 4px; font-size: 11px;">Jam</th>
                                <th style="border: 1px solid #000; padding: 4px; font-size: 11px;">Ruang</th>
                                <th style="border: 1px solid #000; padding: 4px; font-size: 11px;">TTD</th>
                            </tr>
                        </thead>
                        <tbody id="previewScheduleBody">
                            <!-- JS will populate this -->
                        </tbody>
                    </table>

                    <div style="text-align: center; float: right; width: 45%;">
                        <p id="prev_date" style="margin-bottom: 0; font-size: 11px;">Jakarta, <?= date('d F Y') ?></p>
                        <p style="margin-bottom: 40px; font-size: 11px;">Mengetahui<br><span id="prev_inst_signer">Akademi Kebidanan Wijaya Husada</span><br><span id="prev_title">Direktur</span></p>
                        <p id="prev_name" style="font-weight: bold; text-decoration: underline; font-size: 11px;">Elpinaria Girsang, S.ST., M.K.M.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addRow() {
            const tbody = document.getElementById('scheduleBody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="hari[]" placeholder="Hari, dd MMM" oninput="updatePreview()"></td>
                <td><input type="text" name="matkul[]" placeholder="Mata Kuliah" oninput="updatePreview()"></td>
                <td><input type="text" name="jam[]" placeholder="00.00-00.00" oninput="updatePreview()"></td>
                <td><input type="text" name="ruang[]" placeholder="-" oninput="updatePreview()"></td>
                <td><button type="button" class="btn-action" style="background:#e74c3c" onclick="removeRow(this); updatePreview();"><i class="fa-solid fa-trash"></i></button></td>
            `;
            tbody.appendChild(row);
            updatePreview();
        }

        function removeRow(btn) {
            const tbody = document.getElementById('scheduleBody');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
            }
        }

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('previewLogoImg');
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updatePreview() {
            // Header
            document.getElementById('prev_h1').innerText = document.getElementsByName('header_line1')[0].value;
            document.getElementById('prev_h2').innerText = document.getElementsByName('header_line2')[0].value;
            document.getElementById('prev_sub').innerText = document.getElementsByName('sub_title')[0].value;
            
            // Signer
            document.getElementById('prev_name').innerText = document.getElementsByName('signer_name')[0].value;
            document.getElementById('prev_title').innerText = document.getElementsByName('signer_title')[0].value;
            document.getElementById('prev_date').innerText = 'Jakarta, ' + document.getElementsByName('signer_date')[0].value;

            // Schedule Table
            const tbody = document.getElementById('previewScheduleBody');
            tbody.innerHTML = '';
            
            const inputsHari = document.getElementsByName('hari[]');
            const inputsMatkul = document.getElementsByName('matkul[]');
            const inputsJam = document.getElementsByName('jam[]');
            const inputsRuang = document.getElementsByName('ruang[]');
            
            for (let i = 0; i < inputsMatkul.length; i++) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${i+1}</td>
                    <td style="border: 1px solid #000; padding: 4px; font-size: 11px;">${inputsMatkul[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${inputsHari[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${inputsJam[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${inputsRuang[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; font-size: 11px;"></td>
                `;
                tbody.appendChild(tr);
            }
        }

        // CSV Drag & Drop Logic
        setupDragAndDrop('csvDropZone', 'student_csv', 'csvFileName', handleCsvSelect);
        setupDragAndDrop('logoDropZone', 'logo', 'logoFileName', handleLogoSelect);

        function setupDragAndDrop(dropZoneId, inputId, fileNameId, selectHandler) {
            const dropZone = document.getElementById(dropZoneId);
            const input = document.getElementById(inputId);
            
            dropZone.addEventListener('click', () => input.click());
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.style.borderColor = '#3498db', false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.style.borderColor = '#dfe6e9', false);
            });
            
            dropZone.addEventListener('drop', (e) => handleDrop(e, input, selectHandler), false);
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function handleDrop(e, input, callback) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                input.files = files;
                callback(input);
            }
        }

        function handleCsvSelect(input) {
            if (input.files && input.files[0]) {
                const name = input.files[0].name;
                const display = document.getElementById('csvFileName');
                display.style.display = 'block';
                display.innerHTML = '<i class="fa-solid fa-file-csv"></i> ' + name;
            }
        }

        // Toast Logic
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'circle-exclamation'}"></i>
                <span>${message}</span>
            `;
            container.appendChild(toast);

            // Auto show/hide animation
            requestAnimationFrame(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            });
        }

        function handleLogoSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                
                if (!validTypes.includes(file.type)) {
                    showToast('Hanya format JPG, JPEG, dan PNG yang diperbolehkan!', 'error');
                    input.value = ""; // Clear input
                    document.getElementById('logoFileName').style.display = 'none';
                    document.getElementById('previewLogoImg').style.display = 'none';
                    return;
                }

                const name = file.name;
                const display = document.getElementById('logoFileName');
                display.style.display = 'block';
                display.innerHTML = '<i class="fa-solid fa-image"></i> ' + name;
                previewLogo(input);
            }
        }
        
        // Remove old specific logo/csv listeners since we genericized them above or replaced them
        
        // Keep previewLogo for the actual preview rendering logic
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('previewLogoImg');
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updatePreview() {
            // Header
            document.getElementById('prev_h1').innerText = document.getElementsByName('header_line1')[0].value;
            document.getElementById('prev_h2').innerText = document.getElementsByName('header_line2')[0].value;
            document.getElementById('prev_sub').innerText = document.getElementsByName('sub_title')[0].value;
            
            // Signer
            document.getElementById('prev_name').innerText = document.getElementsByName('signer_name')[0].value;
            document.getElementById('prev_inst_signer').innerText = document.getElementsByName('signer_institution')[0].value;
            document.getElementById('prev_title').innerText = document.getElementsByName('signer_title')[0].value;
            document.getElementById('prev_date').innerText = 'Jakarta, ' + document.getElementsByName('signer_date')[0].value;

            // Schedule Table
            const tbody = document.getElementById('previewScheduleBody');
            tbody.innerHTML = '';
            
            const inputsHari = document.getElementsByName('hari[]');
            const inputsMatkul = document.getElementsByName('matkul[]');
            const inputsJam = document.getElementsByName('jam[]');
            const inputsRuang = document.getElementsByName('ruang[]');
            
            for (let i = 0; i < inputsMatkul.length; i++) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${i+1}</td>
                    <td style="border: 1px solid #000; padding: 4px; font-size: 11px;">${inputsMatkul[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${inputsHari[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${inputsJam[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px;">${inputsRuang[i].value}</td>
                    <td style="border: 1px solid #000; padding: 4px; font-size: 11px;"></td>
                `;
                tbody.appendChild(tr);
            }
        }

        // Init
        updatePreview();
    </script>
</body>
</html>
