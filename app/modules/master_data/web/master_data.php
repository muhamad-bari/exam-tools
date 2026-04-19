<?php
session_start();
require_once __DIR__ . '/../../../bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data - Exam Tools</title>
    <link rel="stylesheet" href="assets/css/core/base.css">
    <link rel="stylesheet" href="assets/css/components/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { overflow: auto; background: #f3f6fa; }
        .page { padding: 24px; min-height: calc(100vh - 60px); }
        .grid { display: grid; grid-template-columns: minmax(0, 460px) minmax(0, 1fr); gap: 20px; }
        .card { min-width: 0; background: #fff; border: 1px solid #dbe4ee; border-radius: 16px; padding: 20px; box-shadow: 0 18px 36px rgba(15, 23, 42, 0.07); }
        .card h1, .card h2 { margin-bottom: 10px; color: #1f2937; }
        .muted { color: #64748b; font-size: 0.9rem; }
        .stack { display: grid; gap: 14px; }
        .drop { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; min-height: 112px; border: 2px dashed #cbd5e1; border-radius: 14px; padding: 14px; background: #f8fafc; cursor: pointer; overflow-wrap: anywhere; text-align: center; transition: border-color .2s ease, background-color .2s ease; }
        .drop strong { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; text-align: center; }
        .drop:hover, .drop.dragover { border-color: #4a90e2; background: #eef6ff; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; border-radius: 10px; padding: 10px 14px; color: #fff; text-decoration: none; cursor: pointer; font-weight: 600; flex: 1 1 180px; text-align: center; }
        .btn.primary { background: #2563eb; }
        .btn.green { background: #0f766e; }
        .btn.gray { background: #64748b; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 14px 0 18px; }
        .stat { background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 14px; padding: 14px; }
        .stat strong { display: block; font-size: 1.5rem; color: #0f172a; }
        .table-wrap { border: 1px solid #dbe4ee; border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5edf5; text-align: left; font-size: 0.9rem; }
        th { background: #f8fafc; color: #475569; }
        tr:last-child td { border-bottom: none; }
        .table-pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 12px; border-top: 1px solid #e5edf5; background: #f8fafc; }
        .table-pagination-info { color: #64748b; font-size: 0.8rem; }
        .table-pagination-actions { display: flex; gap: 8px; }
        .pager-btn { border: none; border-radius: 8px; background: #e2e8f0; color: #334155; padding: 6px 10px; cursor: pointer; font-size: 0.78rem; font-weight: 600; }
        .pager-btn:disabled { opacity: .45; cursor: not-allowed; }
        .icon-btn { width: 34px; height: 34px; border: none; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; }
        .icon-btn:disabled { opacity: .45; cursor: not-allowed; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.56); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 9998; }
        .modal-backdrop.show { display: flex; }
        .modal-card { width: min(920px, 100%); max-height: calc(100vh - 40px); overflow: auto; background: #fff; border-radius: 18px; border: 1px solid #dbe4ee; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #e5edf5; }
        .modal-body { padding: 20px; display: grid; gap: 14px; }
        .modal-close { width: 36px; height: 36px; border: none; border-radius: 10px; background: #e2e8f0; color: #334155; cursor: pointer; }
        .modal-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        #toast-container { position: fixed; top: 78px; right: 20px; z-index: 9999; display: grid; gap: 8px; }
        .toast { min-width: 240px; padding: 10px 14px; border-radius: 10px; color: #fff; background: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.16); opacity: 0; transform: translateY(-10px); transition: all .25s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        @media (max-width: 960px) { .grid, .stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php require_once PROJECT_ROOT . '/app/shared/layout/navbar.php'; ?>
    <div id="toast-container"></div>
    <div class="page">
        <div class="grid">
            <div class="card">
                <h1><i class="fa-solid fa-database"></i> Master Data</h1>
                <p class="muted">Kelola data mahasiswa, kelas, dan mata kuliah di sini. Setelah import, `jadwal.php` fokus dipakai untuk membuat jadwal.</p>
                <div class="stack">
                    <div>
                        <h2>Tambah Kelas Manual</h2>
                        <div class="stack" style="gap:10px;">
                            <input type="text" id="newClassName" class="form-control" placeholder="Nama kelas, mis. S1 KESMAS TK I/II" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                            <input type="text" id="newClassCode" class="form-control" placeholder="Kode kelas (opsional)" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                            <div class="actions" style="margin-top:0;">
                                <button type="button" class="btn gray" onclick="createClass()"><i class="fa-solid fa-plus"></i> Tambah Kelas</button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h2>Import Mahasiswa</h2>
                        <div style="display:grid; gap:8px; margin-bottom:12px;">
                            <label for="studentImportClass" class="muted" style="font-weight:600;">Pilih kelas tujuan</label>
                            <select id="studentImportClass" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                                <option value="">Pilih kelas terlebih dahulu</option>
                            </select>
                            <div class="muted">CSV mahasiswa sekarang cukup berisi `No, Nama, NIM`. Semua baris akan masuk ke kelas yang dipilih.</div>
                        </div>
                        <label class="drop" id="studentsDrop" for="studentsCsv">
                            <strong><i class="fa-solid fa-users"></i> Pilih file CSV mahasiswa</strong>
                            <div id="studentsInfo" class="muted" style="margin-top:8px;">Gunakan format `No, Nama, NIM`. Bisa pilih lebih dari 1 file.</div>
                        </label>
                        <input type="file" id="studentsCsv" accept=".csv" multiple style="display:none;">
                        <div class="actions">
                            <button type="button" class="btn primary" onclick="importStudents()"><i class="fa-solid fa-file-import"></i> Import Mahasiswa</button>
                            <a href="format/format_mahasiswa.csv" download class="btn gray"><i class="fa-solid fa-download"></i> Template Mahasiswa</a>
                        </div>
                    </div>
                    <div>
                        <h2>Import Mata Kuliah</h2>
                        <label class="drop" id="subjectsDrop" for="subjectsCsv">
                            <strong><i class="fa-solid fa-book-open"></i> Pilih file CSV mata kuliah</strong>
                            <div id="subjectsInfo" class="muted" style="margin-top:8px;">Satu nama mata kuliah per baris. Bisa pilih lebih dari 1 file.</div>
                        </label>
                        <input type="file" id="subjectsCsv" accept=".csv" multiple style="display:none;">
                        <div class="actions">
                            <button type="button" class="btn green" onclick="importSubjects()"><i class="fa-solid fa-book-medical"></i> Import Mata Kuliah</button>
                            <a href="format/format_matakuliah.csv" download class="btn gray"><i class="fa-solid fa-download"></i> Template Mata Kuliah</a>
                        </div>
                    </div>
                    <div>
                        <h2>Periode Akademik</h2>
                        <div class="stack" style="gap:10px;">
                            <input type="text" id="newAcademicYear" class="form-control" placeholder="Tahun ajaran, mis. 2025/2026" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                            <select id="newTerm" class="form-control" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff;">
                                <option value="">Pilih periode semester</option>
                                <option value="GANJIL">Ganjil</option>
                                <option value="GENAP">Genap</option>
                            </select>
                            <div class="actions" style="margin-top:0;">
                                <button type="button" class="btn gray" onclick="createAcademicPeriod()"><i class="fa-solid fa-calendar-plus"></i> Simpan Periode</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h2 style="margin-bottom:6px;">Ringkasan Master Data</h2>
                        <p class="muted" style="margin:0;">Preview cepat agar data siap dipakai di generator jadwal.</p>
                    </div>
                    <button type="button" class="btn gray" onclick="loadOverview(true)"><i class="fa-solid fa-rotate"></i> Refresh</button>
                </div>
                <div class="stats">
                    <div class="stat"><strong id="classCount">0</strong><span class="muted">Total kelas</span></div>
                    <div class="stat"><strong id="studentCount">0</strong><span class="muted">Total mahasiswa aktif</span></div>
                    <div class="stat"><strong id="subjectCount">0</strong><span class="muted">Total mata kuliah</span></div>
                    <div class="stat"><strong id="academicPeriodCount">0</strong><span class="muted">Total periode akademik</span></div>
                </div>
                <h2>Daftar Kelas</h2>
                <p class="muted" style="margin:0 0 10px;">Fitur `Naik Kelas` mengikuti pola semester: ganjil ke genap tetap tingkat, genap ke ganjil berikutnya otomatis naik tingkat.</p>
                <div class="table-wrap" style="margin-bottom:18px;">
                    <table>
                        <thead><tr><th>Kelas</th><th>Kode</th><th>Mahasiswa</th><th>Naik Ke</th><th>Aksi</th></tr></thead>
                        <tbody id="classBody"><tr><td colspan="5" style="text-align:center; color:#64748b;">Belum ada data kelas.</td></tr></tbody>
                    </table>
                    <div class="table-pagination">
                        <span class="table-pagination-info" id="classPageInfo">Page 1 / 1</span>
                        <div class="table-pagination-actions">
                            <button type="button" class="pager-btn" id="classPrevBtn" onclick="changeClassPage(-1)" disabled>Prev</button>
                            <button type="button" class="pager-btn" id="classNextBtn" onclick="changeClassPage(1)" disabled>Next</button>
                        </div>
                    </div>
                </div>
                <h2>Daftar Mata Kuliah</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Nama Mata Kuliah</th></tr></thead>
                        <tbody id="subjectBody"><tr><td style="text-align:center; color:#64748b;">Belum ada data mata kuliah.</td></tr></tbody>
                    </table>
                    <div class="table-pagination">
                        <span class="table-pagination-info" id="subjectPageInfo">Page 1 / 1</span>
                        <div class="table-pagination-actions">
                            <button type="button" class="pager-btn" id="subjectPrevBtn" onclick="changeSubjectPage(-1)" disabled>Prev</button>
                            <button type="button" class="pager-btn" id="subjectNextBtn" onclick="changeSubjectPage(1)" disabled>Next</button>
                        </div>
                    </div>
                </div>
                <h2 style="margin-top:18px;">Daftar Periode Akademik</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Tahun Ajaran</th><th>Periode</th></tr></thead>
                        <tbody id="academicPeriodBody"><tr><td colspan="2" style="text-align:center; color:#64748b;">Belum ada data periode akademik.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div id="classStudentsModal" class="modal-backdrop" onclick="handleClassStudentsBackdrop(event)">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h2 id="classStudentsModalTitle" style="margin:0 0 6px;">Mahasiswa Kelas</h2>
                    <p id="classStudentsModalInfo" class="muted" style="margin:0;">Memuat data mahasiswa...</p>
                </div>
                <button type="button" class="modal-close" onclick="closeClassStudentsModal()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-toolbar">
                    <button type="button" class="btn primary" style="flex:0 0 auto;" onclick="addStudentManually()"><i class="fa-solid fa-user-plus"></i> Tambah Mahasiswa</button>
                    <button type="button" class="pager-btn" onclick="reloadClassStudentsModal()"><i class="fa-solid fa-rotate"></i> Refresh</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>No</th><th>Nama</th><th>NIM</th></tr></thead>
                        <tbody id="classStudentsModalBody"><tr><td colspan="3" style="text-align:center; color:#64748b;">Memuat data mahasiswa...</td></tr></tbody>
                    </table>
                    <div class="table-pagination">
                        <span class="table-pagination-info" id="classStudentsModalPageInfo">Page 1 / 1</span>
                        <div class="table-pagination-actions">
                            <button type="button" class="pager-btn" id="classStudentsModalPrevBtn" onclick="changeClassStudentsModalPage(-1)" disabled>Prev</button>
                            <button type="button" class="pager-btn" id="classStudentsModalNextBtn" onclick="changeClassStudentsModalPage(1)" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/shared/utils.js"></script>
    <script>
        const pageSize = 5;
        const studentModalPageSize = 10;
        let allClassRows = [];
        let allSubjectRows = [];
        let allAcademicPeriodRows = [];
        let classPage = 1;
        let subjectPage = 1;
        let currentStudentModalClassId = null;
        let currentStudentModalRows = [];
        let currentStudentModalPage = 1;

        function escapeHtml(value) {
            return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
        function preventDefaults(event) {
            event.preventDefault();
            event.stopPropagation();
        }
        function handleDrop(event, input, callback) {
            const files = event.dataTransfer.files;
            if (!files || !files.length) {
                return;
            }
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
        function setupDragAndDrop(dropZoneId, inputId, selectHandler) {
            const dropZone = document.getElementById(dropZoneId);
            const input = document.getElementById(inputId);
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });
            dropZone.addEventListener('drop', event => handleDrop(event, input, selectHandler), false);
        }
        function handleMasterCsvSelect(input, infoId, defaultMessage, overrideFiles = null) {
            const files = overrideFiles || input.files;
            if (!files || !files[0]) {
                return;
            }
            const fileNames = [];
            for (const file of files) {
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    showToast('Hanya format CSV yang diperbolehkan!', 'error');
                    input.value = '';
                    document.getElementById(infoId).textContent = defaultMessage;
                    return;
                }
                fileNames.push(file.name);
            }
            document.getElementById(infoId).textContent = fileNames.length === 1
                ? fileNames[0]
                : `${fileNames.length} file dipilih: ${fileNames.join(', ')}`;
        }
        function renderPagedTable(rows, bodyId, pageInfoId, prevBtnId, nextBtnId, currentPage, renderRow, emptyHtml, pageSizeOverride = pageSize) {
            const body = document.getElementById(bodyId);
            const pageInfo = document.getElementById(pageInfoId);
            const prevBtn = document.getElementById(prevBtnId);
            const nextBtn = document.getElementById(nextBtnId);

            if (!rows.length) {
                body.innerHTML = emptyHtml;
                pageInfo.textContent = 'Page 1 / 1';
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                return 1;
            }

            const totalPages = Math.max(1, Math.ceil(rows.length / pageSizeOverride));
            const safePage = Math.min(Math.max(currentPage, 1), totalPages);
            const startIndex = (safePage - 1) * pageSizeOverride;
            const visibleRows = rows.slice(startIndex, startIndex + pageSizeOverride);

            body.innerHTML = visibleRows.map((item, index) => renderRow(item, index, startIndex)).join('');
            pageInfo.textContent = `Page ${safePage} / ${totalPages}`;
            prevBtn.disabled = safePage <= 1;
            nextBtn.disabled = safePage >= totalPages;

            return safePage;
        }

        function renderClassTable() {
            classPage = renderPagedTable(
                allClassRows,
                'classBody',
                'classPageInfo',
                'classPrevBtn',
                'classNextBtn',
                classPage,
                item => {
                    const canPromote = !!item.next_class_name;
                    const promoteLabel = canPromote ? escapeHtml(item.next_class_name) : '<span style="color:#94a3b8;">Format belum dikenali</span>';
                    const actionButton = `<div style="display:flex; gap:6px; flex-wrap:wrap;">`
                        + `<button type="button" class="icon-btn" style="background:#475569;" onclick="openClassStudentsModal(${Number(item.id)})" title="Lihat mahasiswa"><i class="fa-solid fa-circle-exclamation"></i></button>`
                        + `<button type="button" class="icon-btn" style="background:#2563eb;" onclick="promoteClass(${Number(item.id)})" title="Naik kelas" ${canPromote ? '' : 'disabled'}><i class="fa-solid fa-arrow-up-right-dots"></i></button>`
                        + `<button type="button" class="icon-btn" style="background:#f59e0b;" onclick="editClass(${Number(item.id)})" title="Edit kelas"><i class="fa-solid fa-pen"></i></button>`
                        + `<button type="button" class="icon-btn" style="background:#dc2626;" onclick="deleteClass(${Number(item.id)})" title="Hapus kelas"><i class="fa-solid fa-trash"></i></button>`
                        + `</div>`;
                    return `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.code)}</td><td>${escapeHtml(item.student_count)}</td><td>${promoteLabel}</td><td>${actionButton}</td></tr>`;
                },
                '<tr><td colspan="5" style="text-align:center; color:#64748b;">Belum ada data kelas.</td></tr>'
            );
        }

        function getClassRowById(classId) {
            return allClassRows.find(item => String(item.id) === String(classId)) || null;
        }

        function renderSubjectTable() {
            subjectPage = renderPagedTable(
                allSubjectRows,
                'subjectBody',
                'subjectPageInfo',
                'subjectPrevBtn',
                'subjectNextBtn',
                subjectPage,
                item => `<tr><td>${escapeHtml(item.name)}</td></tr>`,
                '<tr><td style="text-align:center; color:#64748b;">Belum ada data mata kuliah.</td></tr>'
            );
        }

        function renderAcademicPeriodTable() {
            const body = document.getElementById('academicPeriodBody');
            if (!allAcademicPeriodRows.length) {
                body.innerHTML = '<tr><td colspan="2" style="text-align:center; color:#64748b;">Belum ada data periode akademik.</td></tr>';
                return;
            }

            body.innerHTML = allAcademicPeriodRows.map(item => `<tr><td>${escapeHtml(item.academic_year)}</td><td>${escapeHtml(item.term)}</td></tr>`).join('');
        }

        function renderStudentImportClassOptions(selectedClassId = '') {
            const select = document.getElementById('studentImportClass');
            const options = ['<option value="">Pilih kelas terlebih dahulu</option>'];

            for (const item of allClassRows) {
                const selected = String(item.id) === String(selectedClassId) ? ' selected' : '';
                options.push(`<option value="${escapeHtml(item.id)}"${selected}>${escapeHtml(item.name)}</option>`);
            }

            select.innerHTML = options.join('');
        }

        function changeClassPage(direction) {
            classPage += direction;
            renderClassTable();
        }

        function changeSubjectPage(direction) {
            subjectPage += direction;
            renderSubjectTable();
        }

        function createClass() {
            const nameInput = document.getElementById('newClassName');
            const codeInput = document.getElementById('newClassCode');
            const name = nameInput.value.trim();
            const code = codeInput.value.trim();

            if (!name) {
                showToast('Nama kelas wajib diisi', 'error');
                return;
            }

                fetch('index.php?api=master_data&action=create_class', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, code })
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Tambah kelas gagal');
                    }

                    nameInput.value = '';
                    codeInput.value = '';
                    return loadOverview(true).then(() => showToast(`Kelas ditambahkan: ${res.data.name}`));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function createAcademicPeriod() {
            const academicYearInput = document.getElementById('newAcademicYear');
            const termSelect = document.getElementById('newTerm');
            const academicYear = academicYearInput.value.trim();
            const term = termSelect.value.trim();

            if (!academicYear) {
                showToast('Tahun ajaran wajib diisi', 'error');
                return;
            }

            if (!term) {
                showToast('Pilih periode semester terlebih dahulu', 'error');
                return;
            }

            fetch('index.php?api=master_data&action=create_academic_period', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ academic_year: academicYear, term })
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Simpan periode akademik gagal');
                    }

                    academicYearInput.value = '';
                    termSelect.value = '';
                    return loadOverview(true).then(() => showToast(`Periode akademik disimpan: ${res.data.academic_year} ${res.data.term}`));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function handleClassStudentsBackdrop(event) {
            if (event.target && event.target.id === 'classStudentsModal') {
                closeClassStudentsModal();
            }
        }

        function closeClassStudentsModal() {
            document.getElementById('classStudentsModal').classList.remove('show');
            currentStudentModalClassId = null;
            currentStudentModalRows = [];
            currentStudentModalPage = 1;
        }

        function renderClassStudentsModalBody() {
            currentStudentModalPage = renderPagedTable(
                currentStudentModalRows,
                'classStudentsModalBody',
                'classStudentsModalPageInfo',
                'classStudentsModalPrevBtn',
                'classStudentsModalNextBtn',
                currentStudentModalPage,
                (item, index, startIndex) => {
                    const rowNumber = startIndex + index + 1;
                    return `<tr><td>${rowNumber}</td><td>${escapeHtml(item.nama)}</td><td>${escapeHtml(item.nim)}</td></tr>`;
                },
                '<tr><td colspan="3" style="text-align:center; color:#64748b;">Belum ada mahasiswa di kelas ini.</td></tr>',
                studentModalPageSize
            );
        }

        function changeClassStudentsModalPage(direction) {
            currentStudentModalPage += direction;
            renderClassStudentsModalBody();
        }

        function setClassStudentsModalLoading(message) {
            const body = document.getElementById('classStudentsModalBody');
            const pageInfo = document.getElementById('classStudentsModalPageInfo');
            const prevBtn = document.getElementById('classStudentsModalPrevBtn');
            const nextBtn = document.getElementById('classStudentsModalNextBtn');

            body.innerHTML = `<tr><td colspan="3" style="text-align:center; color:#64748b;">${escapeHtml(message)}</td></tr>`;
            pageInfo.textContent = 'Page 1 / 1';
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }

        function setClassStudentsModalError(message) {
            const body = document.getElementById('classStudentsModalBody');
            const pageInfo = document.getElementById('classStudentsModalPageInfo');
            const prevBtn = document.getElementById('classStudentsModalPrevBtn');
            const nextBtn = document.getElementById('classStudentsModalNextBtn');

            body.innerHTML = `<tr><td colspan="3" style="text-align:center; color:#dc2626;">${escapeHtml(message)}</td></tr>`;
            pageInfo.textContent = 'Page 1 / 1';
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }

        function reloadClassStudentsModal() {
            if (!currentStudentModalClassId) {
                return Promise.resolve();
            }

            setClassStudentsModalLoading('Memuat data mahasiswa...');

            return fetch('index.php?api=master_data&action=list_students_by_class&class_id=' + encodeURIComponent(currentStudentModalClassId))
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Gagal memuat mahasiswa kelas');
                    }

                    const rows = res.data || [];
                    currentStudentModalRows = rows;
                    currentStudentModalPage = 1;
                    document.getElementById('classStudentsModalTitle').textContent = `Mahasiswa ${res.class.name}`;
                    document.getElementById('classStudentsModalInfo').textContent = `${rows.length} mahasiswa aktif di kelas ini`;
                    renderClassStudentsModalBody();
                })
                .catch(error => {
                    setClassStudentsModalError(error.message);
                    showToast(error.message, 'error');
                });
        }

        function openClassStudentsModal(classId) {
            const classRow = getClassRowById(classId);
            if (!classRow) {
                showToast('Data kelas tidak ditemukan', 'error');
                return;
            }

            currentStudentModalClassId = classId;
            document.getElementById('classStudentsModalTitle').textContent = `Mahasiswa ${classRow.name}`;
            document.getElementById('classStudentsModalInfo').textContent = 'Memuat data mahasiswa...';
            document.getElementById('classStudentsModal').classList.add('show');
            reloadClassStudentsModal();
        }

        function addStudentManually() {
            if (!currentStudentModalClassId) {
                showToast('Pilih kelas terlebih dahulu', 'error');
                return;
            }

            const classRow = getClassRowById(currentStudentModalClassId);
            const name = prompt(`Nama mahasiswa baru untuk kelas ${classRow ? classRow.name : ''}:`, '');
            if (name === null) {
                return;
            }

            const normalizedName = name.trim();
            if (!normalizedName) {
                showToast('Nama mahasiswa wajib diisi', 'error');
                return;
            }

            const nim = prompt('NIM mahasiswa baru:', '');
            if (nim === null) {
                return;
            }

            const normalizedNim = nim.trim();
            if (!normalizedNim) {
                showToast('NIM mahasiswa wajib diisi', 'error');
                return;
            }

            fetch('index.php?api=master_data&action=create_student', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    class_id: currentStudentModalClassId,
                    name: normalizedName,
                    nim: normalizedNim
                })
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Tambah mahasiswa gagal');
                    }

                    return Promise.all([
                        loadOverview(true),
                        reloadClassStudentsModal()
                    ]).then(() => showToast(`Mahasiswa ditambahkan: ${res.data.nama}`));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function loadOverview(silent = false) {
            return fetch('index.php?api=master_data&action=list_all')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Gagal memuat master data');
                    }

                    const classes = res.classes || [];
                    const subjects = res.subjects || [];
                    const academicPeriods = res.academic_periods || [];
                    const totalStudents = classes.reduce((sum, item) => sum + Number(item.student_count || 0), 0);

                    document.getElementById('classCount').textContent = classes.length;
                    document.getElementById('studentCount').textContent = totalStudents;
                    document.getElementById('subjectCount').textContent = subjects.length;
                    document.getElementById('academicPeriodCount').textContent = academicPeriods.length;

                    allClassRows = classes;
                    allSubjectRows = subjects;
                    allAcademicPeriodRows = academicPeriods;
                    classPage = 1;
                    subjectPage = 1;
                    renderClassTable();
                    renderSubjectTable();
                    renderAcademicPeriodTable();
                    renderStudentImportClassOptions(document.getElementById('studentImportClass').value || '');

                    if (!silent) {
                        showToast('Master data berhasil dimuat');
                    }
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function promoteClass(classId) {
            const classRow = getClassRowById(classId);
            if (!classRow) {
                showToast('Data kelas tidak ditemukan', 'error');
                return;
            }

            if (!classRow.next_class_name) {
                showToast('Format kelas belum mendukung naik kelas otomatis', 'error');
                return;
            }

            const studentCount = Number(classRow.student_count || 0);
            const message = studentCount > 0
                ? `Pindahkan ${studentCount} mahasiswa aktif dari ${classRow.name} ke ${classRow.next_class_name}?`
                : `Kelas ${classRow.name} belum punya mahasiswa aktif. Tetap buat atau sinkronkan kelas tujuan ${classRow.next_class_name}?`;

            if (!confirm(message)) {
                return;
            }

            fetch('index.php?api=master_data&action=promote_class', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ class_id: classId })
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Naik kelas gagal');
                    }

                    const movedStudents = Number(res.data && res.data.moved_students ? res.data.moved_students : 0);
                    return loadOverview(true).then(() => {
                        showToast(`Naik kelas selesai: ${movedStudents} mahasiswa dipindahkan ke ${res.data.target_class.name}`);
                    });
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function editClass(classId) {
            const classRow = getClassRowById(classId);
            if (!classRow) {
                showToast('Data kelas tidak ditemukan', 'error');
                return;
            }

            const nextName = prompt('Nama kelas baru:', classRow.name || '');
            if (nextName === null) {
                return;
            }

            const normalizedName = nextName.trim();
            if (!normalizedName) {
                showToast('Nama kelas wajib diisi', 'error');
                return;
            }

            const nextCode = prompt('Kode kelas baru:', classRow.code || normalizedName);
            if (nextCode === null) {
                return;
            }

            fetch('index.php?api=master_data&action=update_class', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    class_id: classId,
                    name: normalizedName,
                    code: nextCode.trim() || normalizedName
                })
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Edit kelas gagal');
                    }

                    return loadOverview(true).then(() => showToast(`Kelas diperbarui: ${res.data.name}`));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function deleteClass(classId) {
            const classRow = getClassRowById(classId);
            if (!classRow) {
                showToast('Data kelas tidak ditemukan', 'error');
                return;
            }

            const studentCount = Number(classRow.student_count || 0);
            const message = studentCount > 0
                ? `Hapus kelas ${classRow.name} beserta ${studentCount} mahasiswa di dalamnya? Tindakan ini tidak bisa dibatalkan.`
                : `Hapus kelas ${classRow.name}?`;

            if (!confirm(message)) {
                return;
            }

            fetch('index.php?api=master_data&action=delete_class', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ class_id: classId })
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Hapus kelas gagal');
                    }

                    const deletedStudents = Number(res.deleted_students || 0);
                    return loadOverview(true).then(() => showToast(`Kelas dihapus: ${classRow.name}${deletedStudents > 0 ? `, ${deletedStudents} mahasiswa ikut dihapus` : ''}`));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function importStudents() {
            const input = document.getElementById('studentsCsv');
            const classSelect = document.getElementById('studentImportClass');
            const classId = classSelect.value || '';
            if (!input.files || !input.files.length) {
                showToast('Pilih file CSV mahasiswa terlebih dahulu', 'error');
                return;
            }

            if (!classId) {
                showToast('Pilih kelas tujuan import mahasiswa terlebih dahulu', 'error');
                return;
            }

            const classRow = getClassRowById(classId);

            const formData = new FormData();
            formData.append('class_id', classId);
            for (const file of input.files) {
                formData.append('students_csv[]', file);
            }

            fetch('index.php?api=master_data&action=import_students', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Import mahasiswa gagal');
                    }

                    const className = res.stats.class && res.stats.class.name ? res.stats.class.name : (classRow ? classRow.name : 'kelas terpilih');
                    document.getElementById('studentsInfo').textContent = `Import ${res.stats.processed_files} file ke ${className} selesai: +${res.stats.created_students} baru, ${res.stats.updated_students} update`;
                    input.value = '';
                    return loadOverview(true).then(() => showToast(res.message));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function importSubjects() {
            const input = document.getElementById('subjectsCsv');
            if (!input.files || !input.files.length) {
                showToast('Pilih file CSV mata kuliah terlebih dahulu', 'error');
                return;
            }

            const formData = new FormData();
            for (const file of input.files) {
                formData.append('subjects_csv[]', file);
            }

            fetch('index.php?api=master_data&action=import_subjects', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Import mata kuliah gagal');
                    }

                    document.getElementById('subjectsInfo').textContent = `Import ${res.stats.processed_files} file selesai: +${res.stats.created_subjects} mata kuliah baru`;
                    input.value = '';
                    return loadOverview(true).then(() => showToast(res.message));
                })
                .catch(error => showToast(error.message, 'error'));
        }

        document.getElementById('studentsCsv').addEventListener('change', function() {
            handleMasterCsvSelect(this, 'studentsInfo', 'Gunakan format `No, Nama, NIM`. Bisa pilih lebih dari 1 file.');
        });

        document.getElementById('subjectsCsv').addEventListener('change', function() {
            handleMasterCsvSelect(this, 'subjectsInfo', 'Satu nama mata kuliah per baris. Bisa pilih lebih dari 1 file.');
        });

        setupDragAndDrop('studentsDrop', 'studentsCsv', (input, files) => handleMasterCsvSelect(input, 'studentsInfo', 'Gunakan format `No, Nama, NIM`. Bisa pilih lebih dari 1 file.', files));
        setupDragAndDrop('subjectsDrop', 'subjectsCsv', (input, files) => handleMasterCsvSelect(input, 'subjectsInfo', 'Satu nama mata kuliah per baris. Bisa pilih lebih dari 1 file.', files));
        loadOverview(true);
    </script>
</body>
</html>
