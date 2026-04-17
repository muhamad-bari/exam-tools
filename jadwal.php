<?php
session_start();
ini_set('memory_limit', '512M');
set_time_limit(300);
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
        :root {
            --panel-bg: #ffffff;
            --surface: #f7f9fb;
            --border: #dfe5ec;
            --soft-border: #ebeff4;
            --primary: #1f6fb2;
            --primary-soft: #e8f2fb;
            --accent: #d66d3d;
            --success: #2f9d65;
            --danger: #d64545;
            --text: #22313f;
            --muted: #657786;
        }

        body {
            overflow: hidden;
            color: var(--text);
            background: linear-gradient(135deg, #eef4f8 0%, #f6f1e8 100%);
        }

        .split-container {
            display: flex;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        .left-panel,
        .right-panel {
            overflow-y: auto;
            padding: 20px;
        }

        .left-panel {
            width: 42%;
            background: rgba(255, 255, 255, 0.95);
            border-right: 1px solid var(--border);
        }

        .right-panel {
            width: 58%;
            background: linear-gradient(180deg, #52606d 0%, #3f4a53 100%);
            display: flex;
            justify-content: center;
        }

        .preview-paper {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            padding: 10mm;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.22);
            font-family: Arial, sans-serif;
            font-size: 12px;
            transform: scale(0.8);
            transform-origin: top center;
        }

        .form-section {
            background: var(--surface);
            border: 1px solid var(--soft-border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .form-section h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: var(--primary);
            border-bottom: 1px solid var(--soft-border);
            padding-bottom: 8px;
        }

        .form-grid {
            display: grid;
            gap: 12px;
        }

        .form-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.84rem;
            font-weight: 700;
            color: #44505c;
        }

        .form-hint {
            margin: 0 0 8px;
            font-size: 0.76rem;
            color: var(--muted);
        }

        .form-control,
        .subject-select {
            width: 100%;
            border: 1px solid #cfd8e3;
            border-radius: 8px;
            padding: 9px 10px;
            font-size: 0.9rem;
            background: #fff;
            color: var(--text);
        }

        .upload-area,
        .master-drop {
            border: 2px dashed #d8e1ea;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            background: #fbfcfd;
            cursor: pointer;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .upload-area:hover,
        .master-drop:hover {
            border-color: var(--primary);
            background: #f5fafe;
        }

        .btn-action,
        .btn-inline,
        .btn-submit {
            border: none;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn-action:hover,
        .btn-inline:hover,
        .btn-submit:hover {
            transform: translateY(-1px);
        }

        .btn-action {
            width: 100%;
            padding: 8px 10px;
        }

        .btn-inline {
            padding: 8px 10px;
            font-size: 0.82rem;
        }

        .info-card {
            border: 1px solid var(--soft-border);
            border-radius: 10px;
            background: #fff;
            padding: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.82rem;
            color: var(--muted);
        }

        .info-row strong {
            color: var(--text);
        }

        .student-preview-list {
            margin-top: 10px;
            border: 1px solid var(--soft-border);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .student-preview-list table {
            width: 100%;
            border-collapse: collapse;
        }

        .student-preview-list th,
        .student-preview-list td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--soft-border);
            font-size: 0.8rem;
            text-align: left;
        }

        .student-preview-list th {
            background: #f3f7fb;
            color: #44505c;
        }

        .student-preview-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border-top: 1px solid var(--soft-border);
            background: #f8fafc;
        }

        .student-preview-pagination button {
            border: none;
            border-radius: 8px;
            background: #e2e8f0;
            color: #334155;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .student-preview-pagination button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .badge.warn {
            background: #fff2df;
            color: #9a5a21;
        }

        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .schedule-row td {
            vertical-align: top;
        }

        .schedule-cell-card {
            background: #fff;
            border: 1px solid var(--soft-border);
            border-radius: 10px;
            padding: 10px;
        }

        .subject-select-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .subject-select-row .subject-select {
            flex: 1;
        }

        .actions-bar {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        #toast-container {
            position: fixed;
            top: 76px;
            right: 20px;
            z-index: 9999;
            display: grid;
            gap: 8px;
        }

        .toast {
            min-width: 240px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #fff;
            background: #2c3e50;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.16);
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.25s ease;
        }

        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        @media (max-width: 1100px) {
            body { overflow: auto; }
            .split-container { display: block; height: auto; }
            .left-panel, .right-panel { width: 100%; }
            .preview-paper { transform: scale(0.58); margin: 0 auto; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div id="toast-container"></div>

    <div class="split-container">
        <div class="left-panel">
            <form action="generate_pdf_api.php" method="post" enctype="multipart/form-data" id="scheduleForm">
                <input type="hidden" name="generate_pdf" value="true">
                <input type="hidden" name="existing_logo_data" id="existing_logo_data">

                <div class="form-section" style="border-left: 4px solid var(--primary);">
                    <div style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="toggleSessionSection()">
                        <h3 style="margin:0; border:none; padding:0;"><i class="fa-solid fa-save"></i> Saved Sessions</h3>
                        <i id="sessionToggleIcon" class="fa-solid fa-chevron-up" style="color: var(--primary);"></i>
                    </div>
                    <div id="sessionContent" style="margin-top:14px;">
                        <div class="actions-bar" style="margin-bottom:10px;">
                            <div style="position:relative; flex:1;">
                                <i class="fa-solid fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9aa7b4;"></i>
                                <input type="text" id="sessionSearchInput" class="form-control" placeholder="Search sessions..." style="padding-left:30px;" onkeyup="filterSessions()">
                            </div>
                            <button type="button" class="btn-inline" style="background:var(--success);" onclick="createFolder()"><i class="fa-solid fa-folder-plus"></i></button>
                        </div>
                        <div id="sessionList" style="max-height:170px; overflow-y:auto; border:1px solid var(--soft-border); border-radius:10px; padding:8px; background:#fff;">
                            <p style="text-align:center; color:#88939e; font-size:0.85rem; margin:12px 0;">Loading sessions...</p>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 style="margin:0;"><i class="fa-solid fa-gears"></i> Konfigurasi Jadwal</h2>
                    <span class="badge" id="studentSourceBadge"><i class="fa-solid fa-database"></i> Menggunakan master data bila dipilih</span>
                </div>

                <div class="form-section">
                    <h3>1. Header &amp; Logo</h3>
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label">Institusi (Baris 1)</label>
                            <input type="text" name="header_line1" class="form-control" value="AKADEMI KEBIDANAN WIJAYA HUSADA" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Institusi (Baris 2)</label>
                            <input type="text" name="header_line2" class="form-control" placeholder="(Opsional)" oninput="updatePreview()">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Judul / Tahun Ajaran</label>
                        <input type="text" name="sub_title" class="form-control" value="JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Upload Logo</label>
                        <div class="upload-area" id="logoDropZone">
                            <i class="fa-solid fa-image" id="logoIcon" style="font-size:1.9rem; color:#94a3b1; margin-bottom:8px;"></i>
                            <p id="logoText" style="margin:0; color:#607080;">Drag image here or <strong style="color:var(--primary);">Browse</strong></p>
                            <p id="logoFileName" style="display:none; margin:8px 0 0; color:var(--success); font-size:0.8rem; font-weight:700;"></p>
                            <p id="logoLoadingSpinner" style="display:none; margin:8px 0 0; color:var(--primary); font-size:0.8rem;"><i class="fa-solid fa-spinner fa-spin"></i> Processing image...</p>
                            <input type="file" name="logo" id="logo" accept="image/*" style="display:none;" onchange="handleLogoSelect(this)">
                        </div>
                        <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                            <button type="button" id="deleteLogoBtn" class="btn-inline" style="display:none; background:var(--danger);" onclick="deleteLogoFile()"><i class="fa-solid fa-trash"></i> Hapus Logo</button>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>2. Penanda Tangan</h3>
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="signer_name" class="form-control" value="Elpinaria Girsang, S.ST., M.K.M." oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jabatan</label>
                            <input type="text" name="signer_title" class="form-control" value="Direktur" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Institusi (Penanda Tangan)</label>
                            <input type="text" name="signer_institution" class="form-control" value="Akademi Kebidanan Wijaya Husada" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Tanda Tangan</label>
                            <input type="text" name="signer_date" class="form-control" value="<?= date('d F Y') ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>3. Data Mahasiswa</h3>
                    <div class="info-card">
                        <label class="form-label">Pilih Kelas Master</label>
                        <div class="actions-bar">
                            <select name="class_id" id="classSelect" class="form-control" onchange="handleClassChange()">
                                <option value="">-- Pilih kelas dari master data --</option>
                            </select>
                            <button type="button" class="btn-inline" style="background:var(--primary);" onclick="loadMasterData(true)"><i class="fa-solid fa-rotate"></i></button>
                        </div>
                        <p class="form-hint">Pilih kelas yang sudah dikelola di menu `Master Data`. Jika belum tersedia, Anda masih bisa menggunakan CSV fallback.</p>
                        <div class="info-row"><span>Kelas aktif</span><strong id="selectedClassLabel">Belum dipilih</strong></div>
                        <div class="info-row" style="margin-top:6px;"><span>Mahasiswa aktif</span><strong id="selectedClassCount">0</strong></div>
                            <div class="student-preview-list" id="studentPreviewWrap">
                                <table>
                                    <thead><tr><th>NIM</th><th>Nama</th></tr></thead>
                                    <tbody id="studentPreviewBody"><tr><td colspan="2" style="text-align:center; color:#88939e;">Pilih kelas untuk melihat mahasiswa.</td></tr></tbody>
                                </table>
                                <div class="student-preview-pagination">
                                    <button type="button" id="studentPreviewPrev" onclick="changeStudentPreviewPage(-1)" disabled>
                                        <i class="fa-solid fa-chevron-left"></i> Prev
                                    </button>
                                    <span id="studentPreviewPageInfo" style="font-size:0.78rem; color:#64748b;">Page 1 / 1</span>
                                    <button type="button" id="studentPreviewNext" onclick="changeStudentPreviewPage(1)" disabled>
                                        Next <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                    <div class="form-group" style="margin-top:14px;">
                        <label class="form-label">Fallback CSV Mahasiswa</label>
                        <p class="form-hint">Tetap tersedia untuk generate jadwal tanpa memilih kelas master.</p>
                        <div class="upload-area" id="csvDropZone">
                            <i class="fa-solid fa-cloud-arrow-up" id="csvIcon" style="font-size:1.9rem; color:#94a3b1; margin-bottom:8px;"></i>
                            <p id="csvText" style="margin:0; color:#607080;">Drag CSV here or <strong style="color:var(--primary);">Browse</strong></p>
                            <p id="csvFileName" style="display:none; margin:8px 0 0; color:var(--success); font-size:0.8rem; font-weight:700;"></p>
                            <p id="csvLoadingSpinner" style="display:none; margin:8px 0 0; color:var(--primary); font-size:0.8rem;"><i class="fa-solid fa-spinner fa-spin"></i> Validating file...</p>
                            <input type="file" name="student_csv" id="student_csv" accept=".csv" style="display:none;" onchange="handleCsvSelect(this)">
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; gap:8px; flex-wrap:wrap;">
                            <button type="button" id="deleteCsvBtn" class="btn-inline" style="display:none; background:var(--danger);" onclick="deleteCsvFile()"><i class="fa-solid fa-trash"></i> Hapus CSV</button>
                            <span class="badge warn" id="fallbackCsvBadge"><i class="fa-solid fa-file-csv"></i> CSV fallback belum dipilih</span>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>4. Baris Jadwal Ujian</h3>
                    <p class="form-hint">Pilih mata kuliah dari dropdown, lalu sesuaikan manual bila perlu. Tombol plus di tiap baris menambahkan mata kuliah baru ke master.</p>
                    <table class="schedule-table">
                        <tbody id="scheduleBody"></tbody>
                    </table>
                    <button type="button" class="btn-action" style="background:var(--success); margin-top:6px;" onclick="addRow()"><i class="fa-solid fa-plus"></i> Tambah Baris</button>
                </div>

                <div class="form-section">
                    <h3>Actions</h3>
                    <div class="actions-bar" style="margin-bottom:10px; flex-wrap:wrap;">
                        <button type="button" class="btn-inline" style="background:#f39c12;" onclick="newSession()"><i class="fa-solid fa-file-circle-plus"></i> New</button>
                        <input type="text" id="sessionNameInput" class="form-control" placeholder="Session Name" style="flex:1; min-width:180px;">
                        <select id="saveFolderSelect" class="form-control" style="max-width:150px;">
                            <option value="">(No Folder)</option>
                        </select>
                        <button type="button" class="btn-inline" style="background:var(--primary);" onclick="saveSession()"><i class="fa-solid fa-save"></i> Save</button>
                    </div>
                    <button type="button" id="generatePdfBtn" class="btn-submit" style="width:100%; padding:12px; background:var(--danger); font-size:1.05rem;" onclick="generatePDF()">
                        <i class="fa-solid fa-file-pdf"></i> <span id="btnText">Generate PDF</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="right-panel">
            <div class="preview-paper">
                <div id="previewCard">
                    <img id="previewLogoImg" src="" style="display:none; width:60px; height:auto; position:absolute; margin-left:20px;">
                    <div style="text-align:center; margin-bottom:20px; padding:0 40px 0 110px;">
                        <h3 id="prev_h1" style="margin:0; font-size:14px;">AKADEMI KEBIDANAN WIJAYA HUSADA</h3>
                        <h3 id="prev_h2" style="margin:0; font-size:14px;"></h3>
                        <h4 id="prev_sub" style="margin:5px 0 0 0; font-weight:normal; font-size:12px;">JADWAL UJIAN...</h4>
                    </div>
                    <div style="margin-bottom:10px; font-weight:bold; font-size:11px; border:1px dashed #ccc; padding:5px; display:flex; justify-content:space-between; gap:12px;">
                        <div>
                            <span id="previewStudentSource">[Preview data mahasiswa]</span><br>
                            KELAS: <span id="previewClassName">-</span><br>
                            NAMA: <span id="previewStudentName">CONTOH MAHASISWA</span><br>
                            NIM: <span id="previewStudentNim">12345678</span>
                        </div>
                        <div>
                            <img id="prev_qr" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=CONTOH_I_12345678" style="width:60px; height:60px; border:1px solid #ddd;">
                        </div>
                    </div>
                    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                        <thead>
                            <tr style="background:#eee;">
                                <th style="border:1px solid #000; padding:4px; font-size:11px;">No</th>
                                <th style="border:1px solid #000; padding:4px; font-size:11px;">Mata Kuliah</th>
                                <th style="border:1px solid #000; padding:4px; font-size:11px;">Hari/Tanggal</th>
                                <th style="border:1px solid #000; padding:4px; font-size:11px;">Jam</th>
                                <th style="border:1px solid #000; padding:4px; font-size:11px;">Ruang</th>
                                <th style="border:1px solid #000; padding:4px; font-size:11px;">TTD</th>
                            </tr>
                        </thead>
                        <tbody id="previewScheduleBody"></tbody>
                    </table>
                    <div style="text-align:center; float:right; width:45%;">
                        <p id="prev_date" style="margin-bottom:0; font-size:11px;">Bogor, <?= date('d F Y') ?></p>
                        <p style="margin-bottom:40px; font-size:11px;">Mengetahui<br><span id="prev_inst_signer">Akademi Kebidanan Wijaya Husada</span><br><span id="prev_title">Direktur</span></p>
                        <p id="prev_name" style="font-weight:bold; text-decoration:underline; font-size:11px;">Elpinaria Girsang, S.ST., M.K.M.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentSessionId = null;
        let allSessions = [];
        let allFolders = [];
        let filteredSessions = null;
        let masterClasses = [];
        let masterSubjects = [];
        let previewStudents = [];
        let studentPreviewPage = 1;
        const studentPreviewPageSize = 5;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'circle-exclamation'}"></i> <span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 250);
                }, 2800);
            });
        }

        function setupDragAndDrop(dropZoneId, inputId, selectHandler) {
            const dropZone = document.getElementById(dropZoneId);
            const input = document.getElementById(inputId);
            dropZone.addEventListener('click', () => input.click());
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, preventDefaults, false));
            ['dragenter', 'dragover'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.style.borderColor = 'var(--primary)', false));
            ['dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.style.borderColor = '#d8e1ea', false));
            dropZone.addEventListener('drop', (e) => handleDrop(e, input, selectHandler), false);
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function handleDrop(e, input, callback) {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                try {
                    const dataTransfer = new DataTransfer();
                    for (const file of files) {
                        dataTransfer.items.add(file);
                    }
                    input.files = dataTransfer.files;
                } catch (error) {
                }
                callback(input, files);
            }
        }

        function handleLogoSelect(input, overrideFiles = null) {
            const files = overrideFiles || input.files;
            if (!files || !files[0]) {
                return;
            }
            const file = files[0];
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!validTypes.includes(file.type)) {
                showToast('Hanya format JPG, JPEG, dan PNG yang diperbolehkan!', 'error');
                input.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showToast('Ukuran logo terlalu besar (max 5MB)', 'error');
                input.value = '';
                return;
            }
            document.getElementById('logoLoadingSpinner').style.display = 'block';
            document.getElementById('logoIcon').style.display = 'none';
            document.getElementById('logoText').style.display = 'none';
            const reader = new FileReader();
            reader.onload = function(e) {
                setTimeout(() => {
                    document.getElementById('logoLoadingSpinner').style.display = 'none';
                    document.getElementById('logoIcon').style.display = 'block';
                    document.getElementById('logoText').style.display = 'block';
                    document.getElementById('previewLogoImg').src = e.target.result;
                    document.getElementById('previewLogoImg').style.display = 'block';
                    document.getElementById('existing_logo_data').value = e.target.result;
                    document.getElementById('logoFileName').style.display = 'block';
                    document.getElementById('logoFileName').innerHTML = `<i class="fa-solid fa-image"></i> ${escapeHtml(file.name)}`;
                    document.getElementById('deleteLogoBtn').style.display = 'inline-flex';
                    updatePreview();
                    showToast('Logo berhasil dimuat!');
                }, 250);
            };
            reader.onerror = () => showToast('Gagal membaca file logo!', 'error');
            reader.readAsDataURL(file);
        }

        function deleteLogoFile() {
            document.getElementById('logo').value = '';
            document.getElementById('existing_logo_data').value = '';
            document.getElementById('logoFileName').style.display = 'none';
            document.getElementById('logoFileName').innerHTML = '';
            document.getElementById('deleteLogoBtn').style.display = 'none';
            document.getElementById('previewLogoImg').style.display = 'none';
            document.getElementById('previewLogoImg').src = '';
            updatePreview();
        }

        function handleCsvSelect(input, overrideFiles = null) {
            const files = overrideFiles || input.files;
            if (!files || !files[0]) {
                return;
            }
            const file = files[0];
            if (!file.name.toLowerCase().endsWith('.csv')) {
                showToast('Hanya format CSV yang diperbolehkan!', 'error');
                input.value = '';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                showToast('Ukuran file terlalu besar (max 10MB)', 'error');
                input.value = '';
                return;
            }
            document.getElementById('csvLoadingSpinner').style.display = 'block';
            document.getElementById('csvIcon').style.display = 'none';
            document.getElementById('csvText').style.display = 'none';
            setTimeout(() => {
                document.getElementById('csvLoadingSpinner').style.display = 'none';
                document.getElementById('csvIcon').style.display = 'block';
                document.getElementById('csvText').style.display = 'block';
                document.getElementById('csvFileName').style.display = 'block';
                document.getElementById('csvFileName').innerHTML = `<i class="fa-solid fa-file-csv"></i> ${escapeHtml(file.name)} (${(file.size / 1024).toFixed(2)} KB)`;
                document.getElementById('deleteCsvBtn').style.display = 'inline-flex';
                document.getElementById('fallbackCsvBadge').innerHTML = '<i class="fa-solid fa-file-csv"></i> CSV fallback siap dipakai';
                updatePreview();
            }, 250);
        }

        function deleteCsvFile() {
            document.getElementById('student_csv').value = '';
            document.getElementById('csvFileName').style.display = 'none';
            document.getElementById('csvFileName').innerHTML = '';
            document.getElementById('deleteCsvBtn').style.display = 'none';
            document.getElementById('fallbackCsvBadge').innerHTML = '<i class="fa-solid fa-file-csv"></i> CSV fallback belum dipilih';
            updatePreview();
        }

        function createScheduleRow(item = {}) {
            const row = document.createElement('tr');
            row.className = 'schedule-row';
            row.innerHTML = `
                <td>
                    <div class="schedule-cell-card"><input type="text" name="hari[]" class="form-control" placeholder="Senin, 10 Mar" value="${escapeHtml(item.hari || '')}" oninput="updatePreview()"></div>
                </td>
                <td>
                    <div class="schedule-cell-card">
                        <div class="subject-select-row">
                            <select class="subject-select form-control" onchange="handleSubjectSelect(this)"></select>
                            <button type="button" class="btn-inline" style="background:var(--accent); white-space:nowrap;" onclick="quickAddSubject(this)"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <input type="hidden" name="subject_id[]" value="${escapeHtml(item.subject_id || '')}">
                        <input type="hidden" name="matkul[]" value="${escapeHtml(item.matkul || '')}">
                    </div>
                </td>
                <td>
                    <div class="schedule-cell-card"><input type="text" name="jam[]" class="form-control" placeholder="09.00-10.00" value="${escapeHtml(item.jam || '')}" oninput="updatePreview()"></div>
                </td>
                <td>
                    <div class="schedule-cell-card"><input type="text" name="ruang[]" class="form-control" placeholder="R.206" value="${escapeHtml(item.ruang || '')}" oninput="updatePreview()"></div>
                </td>
                <td style="width:58px;">
                    <button type="button" class="btn-action" style="background:var(--danger); height:100%;" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                </td>`;
            hydrateSubjectSelect(row, item.subject_id || '', item.matkul || '');
            return row;
        }

        function hydrateSubjectSelect(row, subjectId = '', matkul = '') {
            const select = row.querySelector('.subject-select');
            const hidden = row.querySelector('input[name="subject_id[]"]');
            const textInput = row.querySelector('input[name="matkul[]"]');
            const currentText = matkul || textInput.value || '';
            const currentId = String(subjectId || hidden.value || '');
            let html = '<option value="">-- Pilih mata kuliah master --</option>';
            masterSubjects.forEach(subject => {
                html += `<option value="${subject.id}">${escapeHtml(subject.name)}</option>`;
            });
            select.innerHTML = html;
            if (currentId && masterSubjects.some(subject => String(subject.id) === currentId)) {
                select.value = currentId;
                hidden.value = currentId;
                textInput.value = select.options[select.selectedIndex].text;
            } else if (currentText) {
                const match = masterSubjects.find(subject => subject.name.toLowerCase() === currentText.toLowerCase());
                if (match) {
                    select.value = String(match.id);
                    hidden.value = match.id;
                    textInput.value = match.name;
                } else {
                    const customValue = `custom:${currentText}`;
                    const customLabel = `${escapeHtml(currentText)} (Custom)`;
                    select.innerHTML += `<option value="${escapeHtml(customValue)}">${customLabel}</option>`;
                    select.value = customValue;
                    hidden.value = '';
                    textInput.value = currentText;
                }
            }
            if (select.value && !textInput.value) {
                textInput.value = select.options[select.selectedIndex].text.replace(/\s*\(Custom\)$/,'');
            }
        }

        function addRow(item = {}) {
            const tbody = document.getElementById('scheduleBody');
            tbody.appendChild(createScheduleRow(item));
            updatePreview();
        }

        function removeRow(button) {
            const tbody = document.getElementById('scheduleBody');
            if (tbody.children.length <= 1) {
                return;
            }
            button.closest('tr').remove();
            updatePreview();
        }

        function handleSubjectSelect(select) {
            const row = select.closest('tr');
            const hidden = row.querySelector('input[name="subject_id[]"]');
            const textInput = row.querySelector('input[name="matkul[]"]');
            hidden.value = select.value || '';
            if (select.value) {
                textInput.value = select.options[select.selectedIndex].text.replace(/\s*\(Custom\)$/,'');
                if (String(select.value).startsWith('custom:')) {
                    hidden.value = '';
                }
            } else {
                textInput.value = '';
            }
            updatePreview();
        }

        function quickAddSubject(button) {
            const name = prompt('Nama mata kuliah baru:');
            if (!name || !name.trim()) {
                return;
            }
            fetch('api_master_data.php?action=create_subject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name.trim() })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    throw new Error(res.message || 'Gagal menyimpan mata kuliah');
                }
                return loadMasterData(true).then(() => {
                    const row = button.closest('tr');
                    row.querySelector('input[name="subject_id[]"]').value = res.data.id;
                    row.querySelector('input[name="matkul[]"]').value = res.data.name;
                    hydrateSubjectSelect(row, res.data.id, res.data.name);
                    updatePreview();
                    showToast('Mata kuliah master diperbarui');
                });
            })
            .catch(error => showToast(error.message, 'error'));
        }

        function loadMasterData(silent = false) {
            return fetch('api_master_data.php?action=list_all')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Gagal memuat master data');
                    }
                    masterClasses = res.classes || [];
                    masterSubjects = res.subjects || [];
                    populateClassSelect();
                    document.querySelectorAll('#scheduleBody tr').forEach(row => hydrateSubjectSelect(row));
                    if (!silent) {
                        showToast('Master data berhasil dimuat');
                    }
                })
                .catch(error => {
                    showToast(error.message, 'error');
                });
        }

        function populateClassSelect(selectedValue = null) {
            const select = document.getElementById('classSelect');
            const current = selectedValue !== null ? String(selectedValue || '') : String(select.value || '');
            select.innerHTML = '<option value="">-- Pilih kelas dari master data --</option>';
            masterClasses.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `${item.name} (${item.student_count} mahasiswa)`;
                select.appendChild(option);
            });
            select.value = current;
        }

        function handleClassChange() {
            const classId = document.getElementById('classSelect').value;
            updateStudentSourceBadge();
            studentPreviewPage = 1;
            if (!classId) {
                previewStudents = [];
                renderStudentPreview(null, []);
                updatePreview();
                return;
            }
            fetch('api_master_data.php?action=list_students_by_class&class_id=' + encodeURIComponent(classId))
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Gagal memuat mahasiswa kelas');
                    }
                    previewStudents = res.data || [];
                    renderStudentPreview(res.class || null, previewStudents);
                    updatePreview();
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function renderStudentPreview(classInfo, students) {
            const body = document.getElementById('studentPreviewBody');
            const prevButton = document.getElementById('studentPreviewPrev');
            const nextButton = document.getElementById('studentPreviewNext');
            const pageInfo = document.getElementById('studentPreviewPageInfo');
            document.getElementById('selectedClassLabel').textContent = classInfo ? classInfo.name : 'Belum dipilih';
            document.getElementById('selectedClassCount').textContent = String(students.length || 0);
            if (!students.length) {
                body.innerHTML = '<tr><td colspan="2" style="text-align:center; color:#88939e;">Belum ada mahasiswa untuk ditampilkan.</td></tr>';
                prevButton.disabled = true;
                nextButton.disabled = true;
                pageInfo.textContent = 'Page 1 / 1';
                return;
            }

            const totalPages = Math.max(1, Math.ceil(students.length / studentPreviewPageSize));
            if (studentPreviewPage > totalPages) {
                studentPreviewPage = totalPages;
            }
            if (studentPreviewPage < 1) {
                studentPreviewPage = 1;
            }

            const startIndex = (studentPreviewPage - 1) * studentPreviewPageSize;
            const visibleStudents = students.slice(startIndex, startIndex + studentPreviewPageSize);

            body.innerHTML = visibleStudents.map(student => `<tr><td>${escapeHtml(student.nim)}</td><td>${escapeHtml(student.nama)}</td></tr>`).join('');
            prevButton.disabled = studentPreviewPage <= 1;
            nextButton.disabled = studentPreviewPage >= totalPages;
            pageInfo.textContent = `Page ${studentPreviewPage} / ${totalPages}`;
        }

        function changeStudentPreviewPage(direction) {
            const totalPages = Math.max(1, Math.ceil(previewStudents.length / studentPreviewPageSize));
            const nextPage = studentPreviewPage + direction;
            if (nextPage < 1 || nextPage > totalPages) {
                return;
            }

            studentPreviewPage = nextPage;
            const selected = masterClasses.find(item => String(item.id) === String(document.getElementById('classSelect').value));
            renderStudentPreview(selected || null, previewStudents);
        }

        function updateStudentSourceBadge() {
            const classId = document.getElementById('classSelect').value;
            const badge = document.getElementById('studentSourceBadge');
            if (classId) {
                const selected = masterClasses.find(item => String(item.id) === String(classId));
                badge.className = 'badge';
                badge.innerHTML = `<i class="fa-solid fa-database"></i> Master class aktif: ${escapeHtml(selected ? selected.name : classId)}`;
            } else {
                badge.className = 'badge warn';
                badge.innerHTML = '<i class="fa-solid fa-file-csv"></i> Menggunakan CSV fallback bila diunggah';
            }
        }

        function updatePreview() {
            document.getElementById('prev_h1').innerText = document.getElementsByName('header_line1')[0].value;
            document.getElementById('prev_h2').innerText = document.getElementsByName('header_line2')[0].value;
            document.getElementById('prev_sub').innerText = document.getElementsByName('sub_title')[0].value;
            document.getElementById('prev_name').innerText = document.getElementsByName('signer_name')[0].value;
            document.getElementById('prev_inst_signer').innerText = document.getElementsByName('signer_institution')[0].value;
            document.getElementById('prev_title').innerText = document.getElementsByName('signer_title')[0].value;
            document.getElementById('prev_date').innerText = 'Bogor, ' + document.getElementsByName('signer_date')[0].value;

            const selectedClassId = document.getElementById('classSelect').value;
            const previewSource = document.getElementById('previewStudentSource');
            const previewClassName = document.getElementById('previewClassName');
            const previewStudentName = document.getElementById('previewStudentName');
            const previewStudentNim = document.getElementById('previewStudentNim');
            const sampleStudent = previewStudents[0];

            if (selectedClassId && sampleStudent) {
                previewSource.textContent = '[Preview mahasiswa dari master data]';
                previewClassName.textContent = sampleStudent.tingkat || '-';
                previewStudentName.textContent = sampleStudent.nama || 'CONTOH MAHASISWA';
                previewStudentNim.textContent = sampleStudent.nim || '12345678';
            } else if (document.getElementById('student_csv').files.length > 0) {
                previewSource.textContent = '[Preview mahasiswa dari CSV fallback]';
                previewClassName.textContent = 'CSV';
                previewStudentName.textContent = 'Mahasiswa dari file unggahan';
                previewStudentNim.textContent = '...';
            } else {
                previewSource.textContent = '[Preview data mahasiswa]';
                previewClassName.textContent = '-';
                previewStudentName.textContent = 'CONTOH MAHASISWA';
                previewStudentNim.textContent = '12345678';
            }

            const tbody = document.getElementById('previewScheduleBody');
            tbody.innerHTML = '';
            const inputsHari = document.getElementsByName('hari[]');
            const inputsMatkul = document.getElementsByName('matkul[]');
            const inputsJam = document.getElementsByName('jam[]');
            const inputsRuang = document.getElementsByName('ruang[]');
            for (let i = 0; i < inputsMatkul.length; i++) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${i + 1}</td>
                    <td style="border:1px solid #000; padding:4px; font-size:11px;">${escapeHtml(inputsMatkul[i].value)}</td>
                    <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${escapeHtml(inputsHari[i].value)}</td>
                    <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${escapeHtml(inputsJam[i].value)}</td>
                    <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${escapeHtml(inputsRuang[i].value)}</td>
                    <td style="border:1px solid #000; padding:4px; font-size:11px;"></td>`;
                tbody.appendChild(tr);
            }
        }

        function generatePDF() {
            const hasClass = !!document.getElementById('classSelect').value;
            const csvInput = document.getElementById('student_csv');
            if (!hasClass && (!csvInput.files || csvInput.files.length === 0)) {
                showToast('Pilih kelas master atau upload CSV mahasiswa terlebih dahulu!', 'error');
                return;
            }
            const formData = new FormData(document.getElementById('scheduleForm'));
            const btn = document.getElementById('generatePdfBtn');
            const btnText = document.getElementById('btnText');
            btn.disabled = true;
            const originalText = btnText.innerHTML;
            btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            fetch('generate_pdf_api.php', { method: 'POST', body: formData })
                .then(async response => {
                    const contentType = response.headers.get('content-type') || '';
                    const payload = contentType.includes('application/json') ? await response.json() : null;
                    if (!response.ok || !payload || !payload.success) {
                        throw new Error(payload && payload.message ? payload.message : 'Gagal membuat PDF');
                    }
                    return payload;
                })
                .then(data => {
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
                    showToast(data.message);
                })
                .catch(error => showToast(error.message, 'error'))
                .finally(() => {
                    btn.disabled = false;
                    btnText.innerHTML = originalText;
                });
        }

        function saveSession() {
            const name = document.getElementById('sessionNameInput').value.trim();
            if (!name) {
                showToast('Please enter a session name!', 'error');
                return;
            }
            const data = {
                header_line1: document.getElementsByName('header_line1')[0].value,
                header_line2: document.getElementsByName('header_line2')[0].value,
                sub_title: document.getElementsByName('sub_title')[0].value,
                signer_name: document.getElementsByName('signer_name')[0].value,
                signer_institution: document.getElementsByName('signer_institution')[0].value,
                signer_title: document.getElementsByName('signer_title')[0].value,
                signer_date: document.getElementsByName('signer_date')[0].value,
                logo_data: document.getElementById('existing_logo_data').value,
                class_id: document.getElementById('classSelect').value || '',
                schedule: []
            };
            const inputsHari = document.getElementsByName('hari[]');
            const inputsMatkul = document.getElementsByName('matkul[]');
            const inputsSubjectId = document.getElementsByName('subject_id[]');
            const inputsJam = document.getElementsByName('jam[]');
            const inputsRuang = document.getElementsByName('ruang[]');
            for (let i = 0; i < inputsMatkul.length; i++) {
                data.schedule.push({
                    hari: inputsHari[i].value,
                    matkul: inputsMatkul[i].value,
                    subject_id: inputsSubjectId[i].value,
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
                    folder_id: document.getElementById('saveFolderSelect').value
                })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    throw new Error(res.message || 'Gagal menyimpan session');
                }
                currentSessionId = res.id;
                fetchSessions();
                showToast('Session saved!');
            })
            .catch(error => showToast(error.message, 'error'));
        }

        function restoreForm(data, sessionName = '', folderId = null) {
            document.getElementById('sessionNameInput').value = sessionName || '';
            document.getElementById('saveFolderSelect').value = folderId || '';
            document.getElementsByName('header_line1')[0].value = data.header_line1 || 'AKADEMI KEBIDANAN WIJAYA HUSADA';
            document.getElementsByName('header_line2')[0].value = data.header_line2 || '';
            document.getElementsByName('sub_title')[0].value = data.sub_title || '';
            document.getElementsByName('signer_name')[0].value = data.signer_name || '';
            document.getElementsByName('signer_institution')[0].value = data.signer_institution || '';
            document.getElementsByName('signer_title')[0].value = data.signer_title || '';
            document.getElementsByName('signer_date')[0].value = data.signer_date || '';
            if (data.logo_data) {
                document.getElementById('existing_logo_data').value = data.logo_data;
                document.getElementById('previewLogoImg').src = data.logo_data;
                document.getElementById('previewLogoImg').style.display = 'block';
                document.getElementById('deleteLogoBtn').style.display = 'inline-flex';
                document.getElementById('logoFileName').style.display = 'block';
                document.getElementById('logoFileName').innerHTML = '<i class="fa-solid fa-image"></i> (Restored from Session)';
            } else {
                deleteLogoFile();
            }
            populateClassSelect(data.class_id || '');
            document.getElementById('classSelect').value = data.class_id || '';
            const tbody = document.getElementById('scheduleBody');
            tbody.innerHTML = '';
            if (Array.isArray(data.schedule) && data.schedule.length) {
                data.schedule.forEach(item => addRow(item));
            } else {
                addRow();
            }
            handleClassChange();
            updatePreview();
        }

        function newSession() {
            if (!confirm('Are you sure you want to start a new session? This will reset all fields.')) {
                return;
            }
            currentSessionId = null;
            document.getElementById('sessionNameInput').value = '';
            document.getElementById('saveFolderSelect').value = '';
            document.getElementsByName('header_line1')[0].value = 'AKADEMI KEBIDANAN WIJAYA HUSADA';
            document.getElementsByName('header_line2')[0].value = '';
            document.getElementsByName('sub_title')[0].value = 'JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025';
            document.getElementsByName('signer_name')[0].value = 'Elpinaria Girsang, S.ST., M.K.M.';
            document.getElementsByName('signer_institution')[0].value = 'Akademi Kebidanan Wijaya Husada';
            document.getElementsByName('signer_title')[0].value = 'Direktur';
            document.getElementsByName('signer_date')[0].value = <?= json_encode(date('d F Y')) ?>;
            document.getElementById('classSelect').value = '';
            deleteLogoFile();
            deleteCsvFile();
            previewStudents = [];
            renderStudentPreview(null, []);
            document.getElementById('scheduleBody').innerHTML = '';
            addRow();
            updateStudentSourceBadge();
            updatePreview();
            fetchSessions();
            showToast('New session started');
        }

        function toggleSessionSection() {
            const content = document.getElementById('sessionContent');
            const icon = document.getElementById('sessionToggleIcon');
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            icon.className = isHidden ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
        }

        function fetchSessions() {
            fetch('api_sessions.php?action=list')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to load sessions');
                    }
                    allSessions = data.data || [];
                    allFolders = data.folders || [];
                    updateFolderSelect();
                    renderSessionTree();
                })
                .catch(() => {
                    document.getElementById('sessionList').innerHTML = '<p style="text-align:center; color:#d64545; font-size:0.85rem; margin:12px 0;">Failed to load sessions.</p>';
                });
        }

        function updateFolderSelect() {
            const select = document.getElementById('saveFolderSelect');
            const current = select.value;
            select.innerHTML = '<option value="">(No Folder)</option>';
            allFolders.forEach(folder => {
                const option = document.createElement('option');
                option.value = folder.id;
                option.textContent = folder.name;
                select.appendChild(option);
            });
            select.value = current;
        }

        function createFolder() {
            const name = prompt('Enter new folder name:');
            if (!name || !name.trim()) return;
            fetch('api_sessions.php?action=create_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: name.trim() }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder created'); })
                .catch(error => showToast(error.message, 'error'));
        }

        function createSubfolder(parentId) {
            const name = prompt('Enter new subfolder name:');
            if (!name || !name.trim()) return;
            fetch('api_sessions.php?action=create_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: name.trim(), parent_id: parentId }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Subfolder created'); })
                .catch(error => showToast(error.message, 'error'));
        }

        function renameFolder(id, currentName) {
            const name = prompt('Rename folder:', currentName);
            if (name === null || !name.trim() || name === currentName) return;
            fetch('api_sessions.php?action=rename_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name: name.trim() }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder renamed'); })
                .catch(error => showToast(error.message, 'error'));
        }

        function deleteFolder(id) {
            if (!confirm('Delete this folder? Sessions inside will be moved to No Folder.')) return;
            fetch('api_sessions.php?action=delete_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder deleted'); })
                .catch(error => showToast(error.message, 'error'));
        }

        function renderSessionTree() {
            const list = document.getElementById('sessionList');
            list.innerHTML = '';
            list.ondragover = handleFolderDragOver;
            list.ondragleave = handleFolderDragLeave;
            list.ondrop = (e) => handleFolderDrop(e, null);
            const sessionsToRender = filteredSessions ? filteredSessions : allSessions;
            if (!sessionsToRender.length && !allFolders.length) {
                list.innerHTML = '<p style="text-align:center; color:#88939e; font-size:0.85rem; margin:12px 0;">No sessions found.</p>';
                return;
            }
            const foldersByParent = {};
            allFolders.forEach(folder => {
                const key = folder.parent_id === null ? 'root' : String(folder.parent_id);
                if (!foldersByParent[key]) {
                    foldersByParent[key] = [];
                }
                foldersByParent[key].push(folder);
            });
            const sessionsByFolder = {};
            sessionsToRender.forEach(session => {
                const key = session.folder_id === null ? 'root' : String(session.folder_id);
                if (!sessionsByFolder[key]) {
                    sessionsByFolder[key] = [];
                }
                sessionsByFolder[key].push(session);
            });

            function buildBranch(parentKey, container) {
                let hasAnyContent = false;
                (foldersByParent[parentKey] || []).forEach(folder => {
                    const folderDiv = createFolderElement(folder);
                    const contentDiv = folderDiv.querySelector('.folder-content');
                    const hasChildContent = buildBranch(String(folder.id), contentDiv);
                    if (filteredSessions && !hasChildContent) {
                        return;
                    }
                    if (filteredSessions) {
                        contentDiv.style.display = 'block';
                        folderDiv.querySelector('.toggle-icon').className = 'fa-solid fa-folder-open toggle-icon';
                    }
                    container.appendChild(folderDiv);
                    hasAnyContent = true;
                });
                (sessionsByFolder[parentKey] || []).forEach(session => {
                    container.appendChild(createSessionCard(session));
                    hasAnyContent = true;
                });
                return hasAnyContent;
            }

            buildBranch('root', list);
        }

        function createFolderElement(folder) {
            const div = document.createElement('div');
            const safeName = String(folder.name).replace(/'/g, "\\'");
            div.className = 'folder-item';
            div.setAttribute('draggable', 'true');
            div.setAttribute('data-id', folder.id);
            div.setAttribute('data-type', 'folder');
            div.ondragstart = handleFolderDragStart;
            div.ondragend = handleFolderDragEnd;
            div.ondragover = handleFolderDragOver;
            div.ondragleave = handleFolderDragLeave;
            div.ondrop = (e) => handleFolderDrop(e, folder.id);
            div.innerHTML = `
                <div class="folder-header" style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:8px 10px; margin-top:6px; border:1px solid transparent; border-radius:8px; background:#f8fafc;">
                    <div onclick="toggleFolderContent(this)" style="display:flex; align-items:center; gap:8px; min-width:0; flex:1; cursor:pointer;">
                        <i class="fa-solid fa-folder toggle-icon" style="color:#d97706;"></i>
                        <strong style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(folder.name)}</strong>
                    </div>
                    <div style="display:flex; gap:4px;">
                        <button type="button" style="background:var(--success); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); createSubfolder(${folder.id})"><i class="fa-solid fa-folder-plus"></i></button>
                        <button type="button" style="background:#f39c12; color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); renameFolder(${folder.id}, '${safeName}')"><i class="fa-solid fa-pen-to-square"></i></button>
                        <button type="button" style="background:var(--danger); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); deleteFolder(${folder.id})"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <div class="folder-content" style="display:none; margin-left:14px;"></div>`;
            return div;
        }

        function toggleFolderContent(trigger) {
            const folderItem = trigger.closest('.folder-item');
            const content = folderItem.querySelector('.folder-content');
            const icon = folderItem.querySelector('.toggle-icon');
            const isOpen = content.style.display === 'block';
            content.style.display = isOpen ? 'none' : 'block';
            icon.className = isOpen ? 'fa-solid fa-folder toggle-icon' : 'fa-solid fa-folder-open toggle-icon';
        }

        function createSessionCard(session) {
            const div = document.createElement('div');
            const isActive = String(session.id) === String(currentSessionId);
            div.setAttribute('draggable', 'true');
            div.setAttribute('data-id', session.id);
            div.setAttribute('data-type', 'session');
            div.ondragstart = handleFolderDragStart;
            div.ondragend = handleFolderDragEnd;
            const safeName = String(session.name).replace(/'/g, "\\'");
            div.style.cssText = `display:flex; justify-content:space-between; align-items:center; padding:6px 4px 6px 10px; margin-left:10px; border-bottom:1px solid #edf2f6; ${isActive ? 'background:#eaf5ff; border-left:4px solid var(--primary);' : ''}`;
            div.innerHTML = `<div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%;"><i class="fa-solid fa-grip-vertical" style="color:#bcc7d2; margin-right:5px;"></i><strong>${escapeHtml(session.name)}</strong><br><span style="font-size:0.72rem; color:#8a97a5; margin-left:15px;">${new Date(session.created_at).toLocaleDateString()}</span>${isActive ? ' <span style="font-size:0.7rem; color:var(--primary); font-weight:700;">(Active)</span>' : ''}</div><div><button type="button" style="background:var(--success); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="loadSession(${session.id})"><i class="fa-solid fa-folder-open"></i></button> <button type="button" style="background:#f39c12; color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="renameSession(${session.id}, '${safeName}')"><i class="fa-solid fa-pen-to-square"></i></button> <button type="button" style="background:var(--primary); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="duplicateSession(${session.id}, '${safeName}')"><i class="fa-solid fa-copy"></i></button> <button type="button" style="background:var(--danger); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="deleteSession(${session.id})"><i class="fa-solid fa-trash"></i></button></div>`;
            return div;
        }

        function handleFolderDragStart(e) {
            e.stopPropagation();
            const item = e.currentTarget;
            e.dataTransfer.setData('type', item.getAttribute('data-type'));
            e.dataTransfer.setData('id', item.getAttribute('data-id'));
            item.style.opacity = '0.4';
        }
        function handleFolderDragEnd(e) { e.currentTarget.style.opacity = '1'; }
        function handleFolderDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            const target = e.currentTarget.classList.contains('folder-item') ? e.currentTarget.querySelector('.folder-header') : e.currentTarget;
            if (target) target.style.border = '2px dashed var(--primary)';
        }
        function handleFolderDragLeave(e) {
            e.stopPropagation();
            const target = e.currentTarget.classList.contains('folder-item') ? e.currentTarget.querySelector('.folder-header') : e.currentTarget;
            if (target) target.style.border = '1px solid transparent';
            if (e.currentTarget.id === 'sessionList') e.currentTarget.style.border = '1px solid var(--soft-border)';
        }
        function handleFolderDrop(e, targetFolderId) {
            e.preventDefault();
            e.stopPropagation();
            const target = e.currentTarget.classList.contains('folder-item') ? e.currentTarget.querySelector('.folder-header') : e.currentTarget;
            if (target) target.style.border = '1px solid transparent';
            if (e.currentTarget.id === 'sessionList') e.currentTarget.style.border = '1px solid var(--soft-border)';
            const type = e.dataTransfer.getData('type');
            const id = e.dataTransfer.getData('id');
            const draggedEl = document.querySelector(`[data-type="${type}"][data-id="${id}"]`);
            if (draggedEl) draggedEl.style.opacity = '1';
            if (!type || !id) return;
            fetch('api_sessions.php?action=move_item', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type, id: parseInt(id, 10), target_id: targetFolderId === null ? null : targetFolderId }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); })
                .catch(error => showToast(error.message, 'error'));
        }

        function filterSessions() {
            const query = document.getElementById('sessionSearchInput').value.toLowerCase();
            filteredSessions = query ? allSessions.filter(session => session.name.toLowerCase().includes(query)) : null;
            renderSessionTree();
        }

        function loadSession(id) {
            fetch('api_sessions.php?action=load&id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) throw new Error(res.message || 'Gagal memuat session');
                    currentSessionId = id;
                    restoreForm(res.data || {}, res.name || '', res.folder_id || '');
                    fetchSessions();
                    showToast('Session loaded!');
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function renameSession(id, currentName) {
            const name = prompt('Rename session:', currentName);
            if (name === null || !name.trim() || name === currentName) return;
            fetch('api_sessions.php?action=rename', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Session renamed'); })
                .catch(error => showToast(error.message, 'error'));
        }

        function duplicateSession(id, currentName) {
            const name = prompt('Enter name for the duplicate session:', currentName + ' (Copy)');
            if (name === null || !name.trim()) return;
            fetch('api_sessions.php?action=duplicate', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Session duplicated'); })
                .catch(error => showToast(error.message, 'error'));
        }

        function deleteSession(id) {
            if (!confirm('Are you sure you want to delete this session?')) return;
            fetch('api_sessions.php?action=delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message); if (String(currentSessionId) === String(id)) currentSessionId = null; fetchSessions(); showToast('Session deleted'); })
                .catch(error => showToast(error.message, 'error'));
        }

        setupDragAndDrop('csvDropZone', 'student_csv', handleCsvSelect);
        setupDragAndDrop('logoDropZone', 'logo', handleLogoSelect);
        addRow();
        updateStudentSourceBadge();
        updatePreview();
        fetchSessions();
        loadMasterData(true);
    </script>
</body>
</html>
