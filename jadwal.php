<?php
session_start();
// Increase limits for bulk generation
ini_set('memory_limit', '512M');
set_time_limit(300);

// Note: PDF Generation dipindahkan ke api_generate_jadwal.php
// File ini hanya menampilkan form HTML
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
            <form action="api_generate_jadwal.php" method="post" enctype="multipart/form-data" id="scheduleForm">
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
                        <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 5px; gap: 5px;">
                            <button type="button" id="deleteLogoBtn" style="display: none; font-size: 0.8rem; padding: 4px 8px; border-radius: 4px; background-color: #e74c3c; color: white; border: none; cursor: pointer; transition: background 0.3s;" onclick="deleteLogoFile()">
                                <i class="fa-solid fa-trash"></i> Hapus Logo
                            </button>
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
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px; gap: 5px;">
                            <button type="button" id="deleteCsvBtn" style="display: none; font-size: 0.8rem; padding: 4px 8px; border-radius: 4px; background-color: #e74c3c; color: white; border: none; cursor: pointer; transition: background 0.3s;" onclick="deleteCsvFile()">
                                <i class="fa-solid fa-trash"></i> Hapus CSV
                            </button>
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
                    <button type="button" id="generatePdfBtn" class="btn-submit" style="width: 100%; padding: 12px; font-size: 1.1rem; background: #e74c3c; color: white; border: none; border-radius: 6px; cursor: pointer; transition: background 0.3s;" onclick="generatePDF()">
                        <i class="fa-solid fa-file-pdf"></i> <span id="btnText">Generate PDF</span>
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
                // Show delete button
                document.getElementById('deleteCsvBtn').style.display = 'inline-block';
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
                    document.getElementById('deleteLogoBtn').style.display = 'none';
                    return;
                }

                const name = file.name;
                const display = document.getElementById('logoFileName');
                display.style.display = 'block';
                display.innerHTML = '<i class="fa-solid fa-image"></i> ' + name;
                // Show delete button
                document.getElementById('deleteLogoBtn').style.display = 'inline-block';
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

        // Delete Logo File Handler
        function deleteLogoFile() {
            const logoInput = document.getElementById('logo');
            const logoFileName = document.getElementById('logoFileName');
            const deleteLogoBtn = document.getElementById('deleteLogoBtn');
            const previewLogoImg = document.getElementById('previewLogoImg');
            
            // Clear file input
            logoInput.value = '';
            
            // Hide file name display
            logoFileName.style.display = 'none';
            logoFileName.innerHTML = '';
            
            // Hide delete button
            deleteLogoBtn.style.display = 'none';
            
            // Hide preview image
            if (previewLogoImg) {
                previewLogoImg.style.display = 'none';
                previewLogoImg.src = '';
            }
            
            showToast('Logo berhasil dihapus', 'success');
        }

        // Delete CSV File Handler
        function deleteCsvFile() {
            const csvInput = document.getElementById('student_csv');
            const csvFileName = document.getElementById('csvFileName');
            const deleteCsvBtn = document.getElementById('deleteCsvBtn');
            
            // Clear file input
            csvInput.value = '';
            
            // Hide file name display
            csvFileName.style.display = 'none';
            csvFileName.innerHTML = '';
            
            // Hide delete button
            deleteCsvBtn.style.display = 'none';
            
            showToast('File CSV berhasil dihapus', 'success');
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

        // AJAX Form Submission Handler
        function generatePDF() {
            const form = document.getElementById('scheduleForm');
            const formData = new FormData(form);
            const btn = document.getElementById('generatePdfBtn');
            const btnText = document.getElementById('btnText');
            
            // Disable button and show loading state
            btn.disabled = true;
            const originalText = btnText.innerHTML;
            btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            
            fetch('generate_pdf_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                
                // Check content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response bukan JSON. Kemungkinan ada error di server.');
                }
                
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    // Decode base64 PDF and trigger download
                    const binaryString = atob(data.pdf_data);
                    const bytes = new Uint8Array(binaryString.length);
                    for (let i = 0; i < binaryString.length; i++) {
                        bytes[i] = binaryString.charCodeAt(i);
                    }
                    
                    const blob = new Blob([bytes], { type: 'application/pdf' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showToast(data.message, 'success');
                } else if (data && !data.success) {
                    showToast(data.message || 'Gagal membuat PDF', 'error');
                } else {
                    throw new Error('Response tidak valid');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error: ' + error.message + ' - Cek browser console untuk details', 'error');
            })
            .finally(() => {
                // Re-enable button
                btn.disabled = false;
                btnText.innerHTML = originalText;
            });
        }

        // Init
        updatePreview();
    </script>
</body>
</html>
