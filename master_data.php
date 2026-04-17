<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data - Exam Tools</title>
    <link rel="stylesheet" href="style.css">
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
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 14px 0 18px; }
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
        #toast-container { position: fixed; top: 78px; right: 20px; z-index: 9999; display: grid; gap: 8px; }
        .toast { min-width: 240px; padding: 10px 14px; border-radius: 10px; color: #fff; background: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.16); opacity: 0; transform: translateY(-10px); transition: all .25s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        @media (max-width: 960px) { .grid, .stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div id="toast-container"></div>
    <div class="page">
        <div class="grid">
            <div class="card">
                <h1><i class="fa-solid fa-database"></i> Master Data</h1>
                <p class="muted">Kelola data mahasiswa, kelas, dan mata kuliah di sini. Setelah import, `jadwal.php` fokus dipakai untuk membuat jadwal.</p>
                <div class="stack">
                    <div>
                        <h2>Import Mahasiswa + Kelas</h2>
                        <label class="drop" id="studentsDrop" for="studentsCsv">
                            <strong><i class="fa-solid fa-users"></i> Pilih file CSV mahasiswa</strong>
                            <div id="studentsInfo" class="muted" style="margin-top:8px;">Gunakan format `No, Nama, NIM, Kelas`. Bisa pilih lebih dari 1 file.</div>
                        </label>
                        <input type="file" id="studentsCsv" accept=".csv" multiple style="display:none;">
                        <div class="actions">
                            <button type="button" class="btn primary" onclick="importStudents()"><i class="fa-solid fa-file-import"></i> Import Mahasiswa</button>
                            <a href="format_mahasiswa.csv" download class="btn gray"><i class="fa-solid fa-download"></i> Template Mahasiswa</a>
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
                            <a href="format_matakuliah.csv" download class="btn gray"><i class="fa-solid fa-download"></i> Template Mata Kuliah</a>
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
                </div>
                <h2>Daftar Kelas</h2>
                <div class="table-wrap" style="margin-bottom:18px;">
                    <table>
                        <thead><tr><th>Kelas</th><th>Kode</th><th>Mahasiswa</th></tr></thead>
                        <tbody id="classBody"><tr><td colspan="3" style="text-align:center; color:#64748b;">Belum ada data kelas.</td></tr></tbody>
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
            </div>
        </div>
    </div>
    <script>
        const pageSize = 5;
        let allClassRows = [];
        let allSubjectRows = [];
        let classPage = 1;
        let subjectPage = 1;

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
        function renderPagedTable(rows, bodyId, pageInfoId, prevBtnId, nextBtnId, currentPage, renderRow, emptyHtml) {
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

            const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
            const safePage = Math.min(Math.max(currentPage, 1), totalPages);
            const startIndex = (safePage - 1) * pageSize;
            const visibleRows = rows.slice(startIndex, startIndex + pageSize);

            body.innerHTML = visibleRows.map(renderRow).join('');
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
                item => `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.code)}</td><td>${escapeHtml(item.student_count)}</td></tr>`,
                '<tr><td colspan="3" style="text-align:center; color:#64748b;">Belum ada data kelas.</td></tr>'
            );
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

        function changeClassPage(direction) {
            classPage += direction;
            renderClassTable();
        }

        function changeSubjectPage(direction) {
            subjectPage += direction;
            renderSubjectTable();
        }

        function loadOverview(silent = false) {
            return fetch('api_master_data.php?action=list_all')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Gagal memuat master data');
                    }

                    const classes = res.classes || [];
                    const subjects = res.subjects || [];
                    const totalStudents = classes.reduce((sum, item) => sum + Number(item.student_count || 0), 0);

                    document.getElementById('classCount').textContent = classes.length;
                    document.getElementById('studentCount').textContent = totalStudents;
                    document.getElementById('subjectCount').textContent = subjects.length;

                    allClassRows = classes;
                    allSubjectRows = subjects;
                    classPage = 1;
                    subjectPage = 1;
                    renderClassTable();
                    renderSubjectTable();

                    if (!silent) {
                        showToast('Master data berhasil dimuat');
                    }
                })
                .catch(error => showToast(error.message, 'error'));
        }

        function importStudents() {
            const input = document.getElementById('studentsCsv');
            if (!input.files || !input.files.length) {
                showToast('Pilih file CSV mahasiswa terlebih dahulu', 'error');
                return;
            }

            const formData = new FormData();
            for (const file of input.files) {
                formData.append('students_csv[]', file);
            }

            fetch('api_master_data.php?action=import_students', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Import mahasiswa gagal');
                    }

                    document.getElementById('studentsInfo').textContent = `Import ${res.stats.processed_files} file selesai: +${res.stats.created_students} baru, ${res.stats.updated_students} update, +${res.stats.created_classes} kelas`;
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

            fetch('api_master_data.php?action=import_subjects', { method: 'POST', body: formData })
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
            handleMasterCsvSelect(this, 'studentsInfo', 'Gunakan format `No, Nama, NIM, Kelas`. Bisa pilih lebih dari 1 file.');
        });

        document.getElementById('subjectsCsv').addEventListener('change', function() {
            handleMasterCsvSelect(this, 'subjectsInfo', 'Satu nama mata kuliah per baris. Bisa pilih lebih dari 1 file.');
        });

        setupDragAndDrop('studentsDrop', 'studentsCsv', (input, files) => handleMasterCsvSelect(input, 'studentsInfo', 'Gunakan format `No, Nama, NIM, Kelas`. Bisa pilih lebih dari 1 file.', files));
        setupDragAndDrop('subjectsDrop', 'subjectsCsv', (input, files) => handleMasterCsvSelect(input, 'subjectsInfo', 'Satu nama mata kuliah per baris. Bisa pilih lebih dari 1 file.', files));
        loadOverview(true);
    </script>
</body>
</html>
