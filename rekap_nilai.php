<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai - Exam Tools</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { overflow: auto; background: linear-gradient(135deg, #eef4fb 0%, #f6f1ea 100%); }
        .page { padding: 24px; min-height: calc(100vh - 60px); }
        .grid { display: grid; grid-template-columns: minmax(340px, 420px) minmax(0, 1fr); gap: 20px; }
        .card { min-width: 0; background: rgba(255, 255, 255, 0.96); border: 1px solid #dbe4ee; border-radius: 18px; padding: 20px; box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08); }
        .card h1, .card h2 { margin: 0 0 10px; color: #1f2937; }
        .muted { color: #64748b; font-size: 0.92rem; }
        .stack { display: grid; gap: 14px; }
        .drop { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 170px; border: 2px dashed #cbd5e1; border-radius: 16px; padding: 18px; background: #f8fafc; cursor: pointer; text-align: center; transition: border-color .2s ease, background-color .2s ease; }
        .drop:hover, .drop.dragover { border-color: #2563eb; background: #eff6ff; }
        .drop strong { display: grid; gap: 10px; justify-items: center; }
        .drop i { font-size: 2rem; color: #2563eb; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; border-radius: 10px; padding: 10px 14px; color: #fff; text-decoration: none; cursor: pointer; font-weight: 600; }
        .btn.primary { background: #2563eb; }
        .btn.gray { background: #64748b; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
        .stat { background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 14px; padding: 14px; }
        .stat strong { display: block; font-size: 1.5rem; color: #0f172a; }
        .pill-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px; background: #eef2ff; color: #334155; font-size: 0.82rem; border: 1px solid #dbe4ee; }
        .table-wrap { border: 1px solid #dbe4ee; border-radius: 14px; overflow: hidden; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5edf5; text-align: left; font-size: 0.9rem; vertical-align: top; }
        th { background: #f8fafc; color: #475569; }
        tr:last-child td { border-bottom: none; }
        .table-pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 12px; border-top: 1px solid #e5edf5; background: #f8fafc; }
        .table-pagination-info { color: #64748b; font-size: 0.8rem; }
        .table-pagination-actions { display: flex; gap: 8px; }
        .pager-btn { border: none; border-radius: 8px; background: #e2e8f0; color: #334155; padding: 6px 10px; cursor: pointer; font-size: 0.78rem; font-weight: 600; }
        .pager-btn:disabled { opacity: .45; cursor: not-allowed; }
        .badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 4px 9px; font-size: 0.76rem; font-weight: 700; }
        .badge.success { background: #dcfce7; color: #166534; }
        .badge.warn { background: #fee2e2; color: #991b1b; }
        #toast-container { position: fixed; top: 78px; right: 20px; z-index: 9999; display: grid; gap: 8px; }
        .toast { min-width: 240px; padding: 10px 14px; border-radius: 10px; color: #fff; background: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.16); opacity: 0; transform: translateY(-10px); transition: all .25s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        @media (max-width: 1100px) { .grid, .stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div id="toast-container"></div>
    <div class="page">
        <div class="grid">
            <div class="card">
                <div class="stack">
                    <div>
                        <h1><i class="fa-solid fa-chart-column"></i> Rekap Nilai</h1>
                        <p class="muted">Upload file Excel `.xlsx` untuk membaca rekap nilai UTS dan UAS. Sistem akan mencoba mencocokkan `NIM` ke master mahasiswa aktif.</p>
                    </div>
                    <div>
                        <label class="drop" id="gradeDrop" for="gradeFile">
                            <strong>
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Pilih file Excel nilai</span>
                                <span id="gradeFileInfo" class="muted">Format umum: `NIM`, `Nama`, `Kelas`, `UTS`, `UAS`</span>
                            </strong>
                        </label>
                        <input type="file" id="gradeFile" accept=".xlsx" style="display:none;">
                    </div>
                    <div class="actions">
                        <button type="button" class="btn primary" onclick="uploadGradeRecap()"><i class="fa-solid fa-upload"></i> Upload & Baca</button>
                        <button type="button" class="btn gray" onclick="resetGradeRecap()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                    </div>
                    <div class="card" style="padding:16px; box-shadow:none; background:#f8fafc;">
                        <h2 style="font-size:1rem;">Deteksi Header</h2>
                        <p class="muted" id="detectedInfo">Belum ada file diproses.</p>
                        <div class="pill-list" id="detectedHeaders"></div>
                    </div>
                    <div class="card" style="padding:16px; box-shadow:none; background:#f8fafc;">
                        <h2 style="font-size:1rem;">Distribusi Kelas</h2>
                        <div class="pill-list" id="classDistribution"><span class="muted">Belum ada data.</span></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h2 style="margin-bottom:6px;">Hasil Rekap</h2>
                        <p class="muted" id="summaryInfo">Upload file nilai untuk melihat ringkasan UTS/UAS.</p>
                    </div>
                </div>
                <div class="stats">
                    <div class="stat"><strong id="totalRows">0</strong><span class="muted">Total baris valid</span></div>
                    <div class="stat"><strong id="avgUts">-</strong><span class="muted">Rata-rata UTS</span></div>
                    <div class="stat"><strong id="avgUas">-</strong><span class="muted">Rata-rata UAS</span></div>
                    <div class="stat"><strong id="avgFinal">-</strong><span class="muted">Rata-rata akhir</span></div>
                    <div class="stat"><strong id="matchedStudents">0</strong><span class="muted">Cocok dengan master</span></div>
                    <div class="stat"><strong id="unmatchedStudents">0</strong><span class="muted">Belum ada di master</span></div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>UTS</th>
                                <th>UAS</th>
                                <th>Rata-rata</th>
                                <th>Master</th>
                            </tr>
                        </thead>
                        <tbody id="gradeBody"><tr><td colspan="8" style="text-align:center; color:#64748b;">Belum ada data rekap nilai.</td></tr></tbody>
                    </table>
                    <div class="table-pagination">
                        <span class="table-pagination-info" id="gradePageInfo">Page 1 / 1</span>
                        <div class="table-pagination-actions">
                            <button type="button" class="pager-btn" id="gradePrevBtn" onclick="changeGradePage(-1)" disabled>Prev</button>
                            <button type="button" class="pager-btn" id="gradeNextBtn" onclick="changeGradePage(1)" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const pageSize = 10;
        let allGradeRows = [];
        let gradePage = 1;

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

        function setupDragAndDrop() {
            const dropZone = document.getElementById('gradeDrop');
            const input = document.getElementById('gradeFile');
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });
            dropZone.addEventListener('drop', event => {
                const files = event.dataTransfer.files;
                if (!files || !files.length) {
                    return;
                }
                try {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(files[0]);
                    input.files = dataTransfer.files;
                } catch (error) {
                }
                updateSelectedFileInfo();
            }, false);
        }

        function updateSelectedFileInfo() {
            const input = document.getElementById('gradeFile');
            const info = document.getElementById('gradeFileInfo');
            const file = input.files && input.files[0] ? input.files[0] : null;

            if (!file) {
                info.textContent = 'Format umum: `NIM`, `Nama`, `Kelas`, `UTS`, `UAS`';
                return;
            }

            if (!file.name.toLowerCase().endsWith('.xlsx')) {
                showToast('File harus berformat .xlsx', 'error');
                input.value = '';
                info.textContent = 'Format umum: `NIM`, `Nama`, `Kelas`, `UTS`, `UAS`';
                return;
            }

            info.textContent = file.name;
        }

        function formatNumber(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }
            const number = Number(value);
            return Number.isFinite(number) ? number.toFixed(2).replace(/\.00$/, '') : '-';
        }

        function renderPagedTable() {
            const body = document.getElementById('gradeBody');
            const pageInfo = document.getElementById('gradePageInfo');
            const prevBtn = document.getElementById('gradePrevBtn');
            const nextBtn = document.getElementById('gradeNextBtn');

            if (!allGradeRows.length) {
                body.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#64748b;">Belum ada data rekap nilai.</td></tr>';
                pageInfo.textContent = 'Page 1 / 1';
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                return;
            }

            const totalPages = Math.max(1, Math.ceil(allGradeRows.length / pageSize));
            gradePage = Math.min(Math.max(gradePage, 1), totalPages);
            const startIndex = (gradePage - 1) * pageSize;
            const visibleRows = allGradeRows.slice(startIndex, startIndex + pageSize);

            body.innerHTML = visibleRows.map((item, index) => {
                const classLabel = item.kelas ? escapeHtml(item.kelas) : `<span class="muted">${escapeHtml(item.master_class || 'Tidak ada')}</span>`;
                const masterBadge = item.matched_master
                    ? '<span class="badge success">Cocok</span>'
                    : '<span class="badge warn">Belum ada</span>';
                return `<tr><td>${startIndex + index + 1}</td><td>${escapeHtml(item.nim)}</td><td>${escapeHtml(item.nama)}</td><td>${classLabel}</td><td>${formatNumber(item.uts)}</td><td>${formatNumber(item.uas)}</td><td>${formatNumber(item.final_score)}</td><td>${masterBadge}</td></tr>`;
            }).join('');

            pageInfo.textContent = `Page ${gradePage} / ${totalPages}`;
            prevBtn.disabled = gradePage <= 1;
            nextBtn.disabled = gradePage >= totalPages;
        }

        function changeGradePage(direction) {
            gradePage += direction;
            renderPagedTable();
        }

        function renderDistribution(items) {
            const container = document.getElementById('classDistribution');
            if (!items || !items.length) {
                container.innerHTML = '<span class="muted">Belum ada data.</span>';
                return;
            }

            container.innerHTML = items.map(item => `<span class="pill"><i class="fa-solid fa-users"></i> ${escapeHtml(item.name)} <strong>${escapeHtml(item.count)}</strong></span>`).join('');
        }

        function renderDetectedHeaders(headers) {
            const container = document.getElementById('detectedHeaders');
            if (!headers || !headers.length) {
                container.innerHTML = '<span class="muted">Belum ada header terdeteksi.</span>';
                return;
            }

            container.innerHTML = headers.map(item => `<span class="pill"><i class="fa-solid fa-tag"></i> ${escapeHtml(item)}</span>`).join('');
        }

        function resetGradeRecap() {
            document.getElementById('gradeFile').value = '';
            allGradeRows = [];
            gradePage = 1;
            updateSelectedFileInfo();
            document.getElementById('summaryInfo').textContent = 'Upload file nilai untuk melihat ringkasan UTS/UAS.';
            document.getElementById('detectedInfo').textContent = 'Belum ada file diproses.';
            document.getElementById('totalRows').textContent = '0';
            document.getElementById('avgUts').textContent = '-';
            document.getElementById('avgUas').textContent = '-';
            document.getElementById('avgFinal').textContent = '-';
            document.getElementById('matchedStudents').textContent = '0';
            document.getElementById('unmatchedStudents').textContent = '0';
            renderDetectedHeaders([]);
            renderDistribution([]);
            renderPagedTable();
        }

        function uploadGradeRecap() {
            const input = document.getElementById('gradeFile');
            if (!input.files || !input.files.length) {
                showToast('Pilih file Excel nilai terlebih dahulu', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('grades_file', input.files[0]);

            fetch('api_rekap_nilai.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        throw new Error(res.message || 'Upload nilai gagal');
                    }

                    allGradeRows = res.data || [];
                    gradePage = 1;
                    document.getElementById('summaryInfo').textContent = `${res.meta.file_name} - Sheet ${res.meta.sheet_name}`;
                    document.getElementById('detectedInfo').textContent = `Header terdeteksi dari sheet ${res.meta.sheet_name}.`;
                    document.getElementById('totalRows').textContent = String(res.summary.total_rows || 0);
                    document.getElementById('avgUts').textContent = formatNumber(res.summary.avg_uts);
                    document.getElementById('avgUas').textContent = formatNumber(res.summary.avg_uas);
                    document.getElementById('avgFinal').textContent = formatNumber(res.summary.avg_final);
                    document.getElementById('matchedStudents').textContent = String(res.summary.matched_students || 0);
                    document.getElementById('unmatchedStudents').textContent = String(res.summary.unmatched_students || 0);
                    renderDetectedHeaders(res.meta.detected_headers || []);
                    renderDistribution(res.summary.class_distribution || []);
                    renderPagedTable();
                    showToast(res.message);
                })
                .catch(error => showToast(error.message, 'error'));
        }

        document.getElementById('gradeFile').addEventListener('change', updateSelectedFileInfo);
        setupDragAndDrop();
        resetGradeRecap();
    </script>
</body>
</html>
