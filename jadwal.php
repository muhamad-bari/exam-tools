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
                <input type="hidden" name="existing_logo_data" id="existing_logo_data">

                <!-- SESSIONS MANAGER -->
                <div class="form-section" style="border-left: 4px solid #3498db;">
                    <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="toggleSessionSection()">
                        <h3 style="color: #3498db; margin: 0; border: none; padding: 0;"><i class="fa-solid fa-save"></i> Saved Sessions</h3>
                        <i id="sessionToggleIcon" class="fa-solid fa-chevron-up" style="color: #3498db;"></i>
                    </div>
                    
                    <div id="sessionContent" style="margin-top: 15px;">
                        <!-- Search & Tools -->
                        <div style="margin-bottom: 10px; display: flex; gap: 5px;">
                            <div style="position: relative; flex-grow: 1;">
                                <i class="fa-solid fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa;"></i>
                                <input type="text" id="sessionSearchInput" placeholder="Search sessions..." class="form-control" style="padding-left: 30px;" onkeyup="filterSessions()">
                            </div>
                            <button type="button" class="btn-action" style="background:#27ae60; width: auto; padding: 0 12px;" onclick="createFolder()" title="Create New Folder">
                                <i class="fa-solid fa-folder-plus"></i>
                            </button>
                        </div>

                        <div id="sessionList" style="max-height: 150px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px; padding: 5px;">
                            <p style="text-align: center; color: #999; font-size: 0.8rem;">Loading sessions...</p>
                        </div>
                    </div>
                </div>

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
                            <input type="file" name="student_csv" id="student_csv" accept=".csv" class="form-control" style="display: none;" onchange="handleCsvSelect(this)">
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

                <div style="margin-top: 20px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                    <h3 style="margin: 0 0 10px 0; font-size: 1rem; color: #2c3e50;">Actions</h3>
                    
                    <div style="display: flex; gap: 5px; margin-bottom: 10px; align-items: center;">
                        <button type="button" class="btn-action" style="background:#f39c12; width: auto; padding: 0 15px; white-space: nowrap;" onclick="newSession()" title="Reset Form / New Session">
                            <i class="fa-solid fa-file-circle-plus"></i> New
                        </button>
                        <input type="text" id="sessionNameInput" class="form-control" placeholder="Session Name" style="flex: 1;">
                        <select id="saveFolderSelect" class="form-control" style="width: auto; max-width: 120px;">
                            <option value="">(No Folder)</option>
                        </select>
                        <button type="button" class="btn-action" style="background:#3498db; width: auto; padding: 0 15px;" onclick="saveSession()">
                            <i class="fa-solid fa-save"></i> Save
                        </button>
                    </div>

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
                        <p id="prev_date" style="margin-bottom: 0; font-size: 11px;">Bogor, <?= date('d F Y') ?></p>
                        <p style="margin-bottom: 40px; font-size: 11px;">Mengetahui<br><span id="prev_inst_signer">Akademi Kebidanan Wijaya Husada</span><br><span id="prev_title">Direktur</span></p>
                        <p id="prev_name" style="font-weight: bold; text-decoration: underline; font-size: 11px;">Elpinaria Girsang, S.ST., M.K.M.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentSessionId = null;

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
            document.getElementById('prev_date').innerText = 'Bogor, ' + document.getElementsByName('signer_date')[0].value;

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
                    // Update hidden input for session saving
                    document.getElementById('existing_logo_data').value = e.target.result;
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
            const hiddenLogoInput = document.getElementById('existing_logo_data');
            
            // Clear file input
            logoInput.value = '';
            hiddenLogoInput.value = '';
            
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
            document.getElementById('prev_date').innerText = 'Bogor, ' + document.getElementsByName('signer_date')[0].value;

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
            
            // Manual Validation for CSV
            const csvInput = document.getElementById('student_csv');
            if (!csvInput.files || csvInput.files.length === 0) {
                showToast('Harap upload file CSV mahasiswa terlebih dahulu!', 'error');
                // Highlight dropzone
                const dropZone = document.getElementById('csvDropZone');
                dropZone.style.borderColor = '#e74c3c';
                setTimeout(() => dropZone.style.borderColor = '#dfe6e9', 2000);
                return;
            }

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
            .then(async response => {
                // Determine if response is JSON
                const contentType = response.headers.get('content-type');
                const isJson = contentType && contentType.includes('application/json');

                if (!response.ok) {
                    // Try to parse error message from server
                    if (isJson) {
                        const errData = await response.json();
                        throw new Error(errData.message || `HTTP Error: ${response.status}`);
                    } else {
                        // Fallback to text
                        const text = await response.text();
                        throw new Error(`Server Error (${response.status}): ${text.substring(0, 50)}...`);
                    }
                }
                
                if (!isJson) {
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
        fetchSessions(); // Load sessions on startup

        // --- SESSION MANAGEMENT LOGIC ---

        let allSessions = [];
        let allFolders = [];

        function toggleSessionSection() {
            const content = document.getElementById('sessionContent');
            const icon = document.getElementById('sessionToggleIcon');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.className = 'fa-solid fa-chevron-up';
            } else {
                content.style.display = 'none';
                icon.className = 'fa-solid fa-chevron-down';
            }
        }

        function fetchSessions() {
            fetch('api_sessions.php?action=list')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        allSessions = data.data; 
                        allFolders = data.folders || [];
                        updateFolderSelect();
                        renderSessionTree();
                    } else {
                        document.getElementById('sessionList').innerHTML = '<p style="text-align: center; color: #e74c3c; font-size: 0.8rem;">Failed to load sessions.</p>';
                    }
                })
                .catch(e => console.error(e));
        }

        function updateFolderSelect() {
            const select = document.getElementById('saveFolderSelect');
            const currentVal = select.value;
            select.innerHTML = '<option value="">(No Folder)</option>';
            allFolders.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.innerText = f.name;
                select.appendChild(opt);
            });
            // Try restore selection if exists
            select.value = currentVal;
        }

        function createFolder() {
            const name = prompt('Enter new folder name:');
            if (!name || !name.trim()) return;

            fetch('api_sessions.php?action=create_folder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name.trim() })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Folder created', 'success');
                    fetchSessions();
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        function createSubfolder(parentId) {
            const name = prompt('Enter new subfolder name:');
            if (!name || !name.trim()) return;

            fetch('api_sessions.php?action=create_folder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name.trim(), parent_id: parentId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Subfolder created', 'success');
                    fetchSessions();
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        function deleteFolder(id) {
            if (!confirm('Delete this folder? Sessions inside will be moved to "No Folder".')) return;

            fetch('api_sessions.php?action=delete_folder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Folder deleted', 'success');
                    fetchSessions();
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        function renameFolder(id, currentName) {
            const newName = prompt('Rename folder:', currentName);
            if (newName === null) return;
            if (!newName.trim() || newName === currentName) return;

            fetch('api_sessions.php?action=rename_folder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, name: newName.trim() })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Folder renamed!', 'success');
                    fetchSessions();
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        // --- TREE RENDER LOGIC ---

        function renderSessionTree() {
            const list = document.getElementById('sessionList');
            list.innerHTML = '';

            // Allow dropping on the main container (Root)
            list.ondragover = handleDragOver;
            list.ondragleave = handleDragLeave;
            list.ondrop = (e) => handleDrop(e, null);

            const isFiltering = !!filteredSessions;
            const sessionsToRender = isFiltering ? filteredSessions : allSessions;

            if (sessionsToRender.length === 0 && allFolders.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: #999; font-size: 0.8rem;">No sessions found.</p>';
                return;
            }

            const foldersByParent = {};
            allFolders.forEach(f => {
                const key = f.parent_id == null ? 'root' : String(f.parent_id);
                if (!foldersByParent[key]) foldersByParent[key] = [];
                foldersByParent[key].push(f);
            });

            const sessionsByFolder = {};
            sessionsToRender.forEach(s => {
                const key = s.folder_id == null ? 'root' : String(s.folder_id);
                if (!sessionsByFolder[key]) sessionsByFolder[key] = [];
                sessionsByFolder[key].push(s);
            });

            function buildBranch(parentKey, container) {
                let hasAnyContent = false;
                const folders = foldersByParent[parentKey] || [];

                folders.forEach(f => {
                    const folderDiv = createFolderElement(f);
                    const contentDiv = folderDiv.querySelector('.folder-content');
                    const hasChildContent = buildBranch(String(f.id), contentDiv);

                    if (isFiltering && !hasChildContent) {
                        return;
                    }

                    if (isFiltering) {
                        contentDiv.style.display = 'block';
                        const icon = folderDiv.querySelector('.toggle-icon');
                        if (icon) icon.className = 'fa-solid fa-folder-open toggle-icon';
                    }

                    container.appendChild(folderDiv);
                    hasAnyContent = true;
                });

                const sessions = sessionsByFolder[parentKey] || [];
                sessions.forEach(s => {
                    container.appendChild(createSessionCard(s));
                    hasAnyContent = true;
                });

                return hasAnyContent;
            }

            buildBranch('root', list);
        }

        function createFolderElement(f) {
            const folderDiv = document.createElement('div');
            folderDiv.className = 'folder-item';
            folderDiv.style.marginBottom = '5px';
            folderDiv.style.marginLeft = '10px';
            folderDiv.setAttribute('draggable', 'true');
            folderDiv.setAttribute('data-id', f.id);
            folderDiv.setAttribute('data-type', 'folder');
            
            // Drag Events
            folderDiv.ondragstart = handleDragStart;
            folderDiv.ondragover = (e) => { e.stopPropagation(); handleDragOver(e); }; // Stop propagation to allow nested drops
            folderDiv.ondragleave = handleDragLeave;
            folderDiv.ondrop = (e) => { e.stopPropagation(); handleDrop(e, f.id); };

            // Header
            const header = document.createElement('div');
            header.style.cssText = 'background: #ecf0f1; padding: 6px; border-radius: 4px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 0.9rem; color: #2c3e50; border: 1px solid transparent;';
            header.className = 'folder-header';
            
            // Toggle Logic
            header.onclick = function(e) {
                if (e.target.closest('.folder-action')) return;
                const content = folderDiv.querySelector('.folder-content');
                const icon = this.querySelector('.toggle-icon');
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    icon.className = 'fa-solid fa-folder-open toggle-icon';
                } else {
                    content.style.display = 'none';
                    icon.className = 'fa-solid fa-folder toggle-icon';
                }
            };

            header.innerHTML = `
                <div style="display:flex; align-items:center;">
                    <i class="fa-solid fa-grip-vertical" style="color:#bdc3c7; margin-right:5px; cursor:grab; font-size:0.8rem;"></i>
                    <i class="fa-solid fa-folder toggle-icon" style="margin-right: 5px; color: #f39c12;"></i> 
                    ${f.name}
                </div>
                <div>
                    <button type="button" class="folder-action" onclick="createSubfolder(${f.id})" style="background: none; border: none; color: #27ae60; cursor: pointer; padding: 2px;" title="New Subfolder">
                        <i class="fa-solid fa-folder-plus"></i>
                    </button>
                    <button type="button" class="folder-action" onclick="renameFolder(${f.id}, '${f.name.replace(/'/g, "\\'")}')" style="background: none; border: none; color: #f39c12; cursor: pointer; padding: 2px;" title="Rename Folder">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="folder-action" onclick="deleteFolder(${f.id})" style="background: none; border: none; color: #e74c3c; cursor: pointer; padding: 2px;" title="Delete Folder">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            `;

            // Content Container
            const content = document.createElement('div');
            content.className = 'folder-content';
            content.style.display = 'none'; // Collapsed by default
            content.style.paddingLeft = '5px';
            content.style.marginTop = '2px';
            content.style.borderLeft = '2px solid #ecf0f1';

            folderDiv.appendChild(header);
            folderDiv.appendChild(content);
            return folderDiv;
        }

        function createSessionCard(s) {
            const date = new Date(s.created_at).toLocaleDateString();
            const isActive = s.id == currentSessionId;
            const activeStyle = isActive ? 'background-color: #d1ecf1; border-left: 4px solid #17a2b8;' : 'border-bottom: 1px solid #eee;';
            
            const div = document.createElement('div');
            div.setAttribute('draggable', 'true');
            div.setAttribute('data-id', s.id);
            div.setAttribute('data-type', 'session');
            div.ondragstart = handleDragStart;
            div.ondragend = handleDragEnd;

            div.style.cssText = `display: flex; justify-content: space-between; align-items: center; padding: 5px; font-size: 0.9rem; margin-left: 10px; cursor: grab; ${activeStyle}`;
            div.innerHTML = `
                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                    <i class="fa-solid fa-grip-vertical" style="color:#bdc3c7; margin-right:5px;"></i>
                    <strong title="${s.name}">${s.name}</strong> <br>
                    <span style="font-size: 0.75rem; color: #888; margin-left: 15px;">${date}</span>
                    ${isActive ? '<span style="font-size: 0.7rem; color: #17a2b8; font-weight: bold;">(Active)</span>' : ''}
                </div>
                <div style="flex-shrink: 0;">
                    <button type="button" onclick="loadSession(${s.id})" style="background: #27ae60; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; margin-right: 2px;" title="Load Session"><i class="fa-solid fa-folder-open"></i></button>
                    <button type="button" onclick="renameSession(${s.id}, '${s.name.replace(/'/g, "\\'")}')" style="background: #f39c12; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; margin-right: 2px;" title="Rename Session"><i class="fa-solid fa-pen-to-square"></i></button>
                    <button type="button" onclick="duplicateSession(${s.id}, '${s.name.replace(/'/g, "\\'")}')" style="background: #3498db; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; margin-right: 2px;" title="Duplicate Session"><i class="fa-solid fa-copy"></i></button>
                    <button type="button" onclick="deleteSession(${s.id})" style="background: #e74c3c; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer;" title="Delete Session"><i class="fa-solid fa-trash"></i></button>
                </div>
            `;
            return div;
        }

        // --- DRAG AND DROP HANDLERS ---

        function handleDragStart(e) {
            e.dataTransfer.setData('type', e.target.getAttribute('data-type'));
            e.dataTransfer.setData('id', e.target.getAttribute('data-id'));
            e.target.style.opacity = '0.4';
        }

        function handleDragEnd(e) {
            e.target.style.opacity = '1';
        }

        function handleDragOver(e) {
            e.preventDefault();
            // Add visual cue
            const target = e.currentTarget.classList.contains('folder-item') 
                ? e.currentTarget.querySelector('.folder-header') 
                : e.currentTarget; // or sessionList
            
            if(target) target.style.border = '2px dashed #3498db';
        }

        function handleDragLeave(e) {
            const target = e.currentTarget.classList.contains('folder-item') 
                ? e.currentTarget.querySelector('.folder-header') 
                : e.currentTarget;
            
            if(target) target.style.border = '1px solid transparent';
            if(e.currentTarget.id === 'sessionList') e.currentTarget.style.border = '1px solid #eee'; // Reset root style
        }

        function handleDrop(e, targetFolderId) {
            e.preventDefault();
            
            // Reset styles
            const target = e.currentTarget.classList.contains('folder-item') 
                ? e.currentTarget.querySelector('.folder-header') 
                : e.currentTarget;
            if(target) target.style.border = '1px solid transparent';
            if(e.currentTarget.id === 'sessionList') e.currentTarget.style.border = '1px solid #eee';

            const type = e.dataTransfer.getData('type');
            const id = e.dataTransfer.getData('id');

            // Reset Opacity (Need to find element again as e.target might be drop zone)
            const draggedEl = document.querySelector(`[data-type="${type}"][data-id="${id}"]`);
            if(draggedEl) draggedEl.style.opacity = '1';

            if (!id || !type) return;

            fetch('api_sessions.php?action=move_item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    type: type, 
                    id: id, 
                    target_id: targetFolderId 
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    fetchSessions(); // Refresh tree
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        let filteredSessions = null;

        function filterSessions() {
            const query = document.getElementById('sessionSearchInput').value.toLowerCase();
            if (!query) {
                filteredSessions = null;
                renderSessionTree();
                return;
            }

            filteredSessions = allSessions.filter(s => s.name.toLowerCase().includes(query));
            renderSessionTree();
        }

        function duplicateSession(id, currentName) {
            const newName = prompt('Enter name for the duplicate session:', currentName + ' (Copy)');
            if (newName === null) return; // Cancelled
            if (newName.trim() === '') return;

            fetch('api_sessions.php?action=duplicate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, name: newName })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Session duplicated!', 'success');
                    fetchSessions();
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        function renameSession(id, currentName) {
            const newName = prompt('Rename session:', currentName);
            if (newName === null) return; // Cancelled
            if (newName.trim() === '' || newName === currentName) return;

            fetch('api_sessions.php?action=rename', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, name: newName })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Session renamed!', 'success');
                    fetchSessions();
                } else {
                    showToast('Error: ' + res.message, 'error');
                }
            });
        }

        function saveSession() {
            const name = document.getElementById('sessionNameInput').value.trim();
            const folderId = document.getElementById('saveFolderSelect').value;

            if (!name) {
                showToast('Please enter a session name!', 'error');
                return;
            }

            // Gather Data
            const data = {
                header_line1: document.getElementsByName('header_line1')[0].value,
                header_line2: document.getElementsByName('header_line2')[0].value,
                sub_title: document.getElementsByName('sub_title')[0].value,
                signer_name: document.getElementsByName('signer_name')[0].value,
                signer_institution: document.getElementsByName('signer_institution')[0].value,
                signer_title: document.getElementsByName('signer_title')[0].value,
                signer_date: document.getElementsByName('signer_date')[0].value,
                logo_data: document.getElementById('existing_logo_data').value,
                schedule: []
            };

            const inputsHari = document.getElementsByName('hari[]');
            const inputsMatkul = document.getElementsByName('matkul[]');
            const inputsJam = document.getElementsByName('jam[]');
            const inputsRuang = document.getElementsByName('ruang[]');

            for (let i = 0; i < inputsMatkul.length; i++) {
                data.schedule.push({
                    hari: inputsHari[i].value,
                    matkul: inputsMatkul[i].value,
                    jam: inputsJam[i].value,
                    ruang: inputsRuang[i].value
                });
            }

            fetch('api_sessions.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    name: name, 
                    data: JSON.stringify(data), 
                    id: currentSessionId,
                    folder_id: folderId
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Session saved!', 'success');
                    document.getElementById('sessionNameInput').value = '';
                    fetchSessions();
                } else {
                    showToast('Error saving: ' + res.message, 'error');
                }
            })
            .catch(e => showToast('Error: ' + e, 'error'));
        }

        function deleteSession(id) {
            if (!confirm('Are you sure you want to delete this session?')) return;

            fetch('api_sessions.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Session deleted.', 'success');
                    fetchSessions();
                } else {
                    showToast('Error deleting: ' + res.message, 'error');
                }
            });
        }

        function loadSession(id) {
            fetch('api_sessions.php?action=load&id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        currentSessionId = id; // Track session ID
                        restoreForm(res.data, res.name, res.folder_id);
                        fetchSessions(); // Refresh list to update highlight
                        showToast('Session loaded!', 'success');
                    } else {
                        showToast('Error loading: ' + res.message, 'error');
                    }
                })
                .catch(e => showToast('Error: ' + e, 'error'));
        }

        function newSession() {
            if (!confirm('Are you sure you want to start a new session? This will reset all fields.')) return;

            currentSessionId = null; // Reset session ID
            fetchSessions(); // Refresh list to remove highlight

            // Reset Text Fields to Defaults
            document.getElementsByName('header_line1')[0].value = 'AKADEMI KEBIDANAN WIJAYA HUSADA';
            document.getElementsByName('header_line2')[0].value = '';
            document.getElementsByName('sub_title')[0].value = 'JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025';
            document.getElementsByName('signer_name')[0].value = 'Elpinaria Girsang, S.ST., M.K.M.';
            document.getElementsByName('signer_institution')[0].value = 'Akademi Kebidanan Wijaya Husada';
            document.getElementsByName('signer_title')[0].value = 'Direktur';
            
            // Date (Today)
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            const month = today.toLocaleString('en-US', { month: 'long' });
            const year = today.getFullYear();
            document.getElementsByName('signer_date')[0].value = `${day} ${month} ${year}`;

            // Reset Folder Selection
            document.getElementById('saveFolderSelect').value = "";

            // Clear Logo
            deleteLogoFile();

            // Clear CSV
            deleteCsvFile();

            // Reset Session Name
            document.getElementById('sessionNameInput').value = '';

            // Reset Schedule Table to 1 empty row
            const tbody = document.getElementById('scheduleBody');
            tbody.innerHTML = '';
            addRow(); // Adds one empty row

            updatePreview();
            showToast('New session started', 'success');
        }

        function restoreForm(data, sessionName = '', folderId = null) {
            // Set Session Name Input
            if (sessionName) {
                document.getElementById('sessionNameInput').value = sessionName;
            }

            // Set Folder
            document.getElementById('saveFolderSelect').value = folderId || "";

            // Restore Text Fields
            document.getElementsByName('header_line1')[0].value = data.header_line1 || '';
            document.getElementsByName('header_line2')[0].value = data.header_line2 || '';
            document.getElementsByName('sub_title')[0].value = data.sub_title || '';
            document.getElementsByName('signer_name')[0].value = data.signer_name || '';
            document.getElementsByName('signer_institution')[0].value = data.signer_institution || '';
            document.getElementsByName('signer_title')[0].value = data.signer_title || '';
            document.getElementsByName('signer_date')[0].value = data.signer_date || '';

            // Restore Logo
            if (data.logo_data) {
                document.getElementById('existing_logo_data').value = data.logo_data;
                const img = document.getElementById('previewLogoImg');
                img.src = data.logo_data;
                img.style.display = 'block';
                // Also show the delete button if we have a logo
                document.getElementById('deleteLogoBtn').style.display = 'inline-block';
                document.getElementById('logoFileName').style.display = 'block';
                document.getElementById('logoFileName').innerHTML = '<i class="fa-solid fa-image"></i> (Restored from Session)';
            } else {
                deleteLogoFile(); // Clear if no logo in session
            }

            // Restore Schedule Table
            const tbody = document.getElementById('scheduleBody');
            tbody.innerHTML = ''; // Clear existing

            if (data.schedule && data.schedule.length > 0) {
                data.schedule.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input type="text" name="hari[]" placeholder="Hari, dd MMM" value="${item.hari}" oninput="updatePreview()"></td>
                        <td><input type="text" name="matkul[]" placeholder="Mata Kuliah" value="${item.matkul}" oninput="updatePreview()"></td>
                        <td><input type="text" name="jam[]" placeholder="00.00-00.00" value="${item.jam}" oninput="updatePreview()"></td>
                        <td style="width: 60px;"><input type="text" name="ruang[]" placeholder="R.206" value="${item.ruang}" oninput="updatePreview()"></td>
                        <td style="width: 30px;"><button type="button" class="btn-action" style="background:#e74c3c" onclick="removeRow(this); updatePreview();"><i class="fa-solid fa-trash"></i></button></td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                // Add one empty row if none
                addRow();
            }

            updatePreview();
        }

    </script>
</body>
</html>
