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
        'nama'    => $_POST['signer_name'] ?? 'Elpinaria Girsang, S.ST., M.K.M.',
        'jabatan' => $_POST['signer_title'] ?? 'Direktur',
        'tanggal' => $_POST['signer_date'] ?? date('d F Y')
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

    // 5. Generate PDF HTML
    // Helper function to render a single card
    function renderCard($student, $logoData, $headerLine1, $headerLine2, $subTitle, $jadwal, $penandaTangan) {
        // Logo HTML
        $imgHtml = '';
        if ($logoData) {
            $imgHtml = '<img src="' . $logoData . '" style="width: 70px; height: auto; position: absolute; left: 30px; top: 10px;">';
        }

        $html = '
        <div class="card">
            ' . $imgHtml . '
            <div class="header" style="text-align: center; margin-bottom: 15px; padding: 0 40px;">
                <h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine1) . '</h3>
                ' . ($headerLine2 ? '<h3 style="margin: 0; font-size: 11pt;">' . htmlspecialchars($headerLine2) . '</h3>' : '') . '
                <h4 style="margin: 5px 0 0 0; font-weight: normal; font-size: 10pt;">' . htmlspecialchars($subTitle) . '</h4>
            </div>

            <div class="student-info" style="margin-bottom: 5px; font-weight: bold; font-size: 9pt;">
                <table style="width: 100%; border: none;">
                    <tr><td style="width: 70px;">TINGKAT</td><td>: ' . htmlspecialchars($student->tingkat) . '</td></tr>
                    <tr><td>NAMA</td><td>: ' . htmlspecialchars($student->nama) . '</td></tr>
                    <tr><td>NIM</td><td>: ' . htmlspecialchars($student->nim) . '</td></tr>
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
                <p style="margin-bottom: 40px; font-size: 9pt;">Mengetahui<br>Akademi Kebidanan Wijaya Husada<br>' . htmlspecialchars($penandaTangan['jabatan']) . '</p>
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
                    <button type="submit" class="btn-submit" style="width: auto; padding: 8px 15px; background: #e74c3c;">
                        <i class="fa-solid fa-file-pdf"></i> Generate PDF
                    </button>
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
                        <input type="file" name="logo" class="form-control" accept="image/*" onchange="previewLogo(this)">
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
                        <input type="file" name="student_csv" class="form-control" accept=".csv" required>
                        <small style="color: #666;">Format: No, NIM, Nama, Kelas</small>
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
            </form>
        </div>

        <!-- RIGHT PANEL: PREVIEW -->
        <div class="right-panel">
            <div class="preview-paper">
                <!-- Preview Card Content (Simulating PDF Layout) -->
                <div id="previewCard">
                    <img id="previewLogoImg" src="" style="display:none; width: 60px; height: auto; position: absolute; margin-left: 20px;">
                    
                    <div style="text-align: center; margin-bottom: 20px; padding: 0 40px;">
                        <h3 id="prev_h1" style="margin: 0; font-size: 14px;">AKADEMI KEBIDANAN WIJAYA HUSADA</h3>
                        <h3 id="prev_h2" style="margin: 0; font-size: 14px;"></h3>
                        <h4 id="prev_sub" style="margin: 5px 0 0 0; font-weight: normal; font-size: 12px;">JADWAL UJIAN...</h4>
                    </div>

                    <div style="margin-bottom: 10px; font-weight: bold; font-size: 11px; border: 1px dashed #ccc; padding: 5px;">
                        [Preview Data Mahasiswa dari CSV]<br>
                        NAMA: CONTOH MAHASISWA<br>
                        NIM: 12345678<br>
                        TINGKAT: I
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
                        <p style="margin-bottom: 40px; font-size: 11px;">Mengetahui<br>Akademi Kebidanan Wijaya Husada<br><span id="prev_title">Direktur</span></p>
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

        // Init
        updatePreview();
    </script>
</body>
</html>
