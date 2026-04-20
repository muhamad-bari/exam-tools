<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remedial/SP &amp; Susulan - Exam Tools</title>
    <link rel="stylesheet" href="assets/css/core/base.css">
    <link rel="stylesheet" href="assets/css/components/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { overflow: auto; background: linear-gradient(135deg, #eef4fb 0%, #f8fafc 55%, #f5efe6 100%); }
        .page { padding: 24px; min-height: calc(100vh - 60px); }
        .grid { display: grid; grid-template-columns: minmax(320px, 380px) minmax(0, 1fr); gap: 20px; align-items: start; }
        .card { min-width: 0; background: rgba(255, 255, 255, 0.96); border: 1px solid #dbe4ee; border-radius: 18px; padding: 20px; box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08); }
        .card h1, .card h2, .card h3 { margin: 0; color: #1f2937; }
        .muted { color: #64748b; font-size: 0.92rem; }
        .stack { display: grid; gap: 16px; }
        .filter-grid { display: grid; gap: 12px; }
        .field { display: grid; gap: 6px; }
        .field label { font-size: 0.86rem; font-weight: 700; color: #334155; }
        .field select, .field input, .field textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 11px 12px; background: #fff; color: #0f172a; font: inherit; }
        .field textarea { min-height: 96px; resize: vertical; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; border-radius: 12px; padding: 10px 14px; color: #fff; text-decoration: none; cursor: pointer; font-weight: 700; }
        .btn.primary { background: #2563eb; }
        .btn.gray { background: #64748b; }
        .btn.light { background: #e2e8f0; color: #334155; }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .stat { background: #f8fafc; border: 1px solid #dbe4ee; border-radius: 14px; padding: 14px; }
        .stat strong { display: block; font-size: 1.5rem; color: #0f172a; margin-bottom: 4px; }
        .result-toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .result-toolbar-controls { display: grid; gap: 10px; justify-items: end; flex: 1 1 360px; min-width: 280px; }
        .pill-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .result-toolbar-controls .pill-list { justify-content: flex-end; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px; background: #eef2ff; color: #334155; font-size: 0.82rem; border: 1px solid #dbe4ee; }
        .inline-search-field { width: min(360px, 100%); display: grid; gap: 6px; }
        .inline-search-label { font-size: 0.8rem; font-weight: 700; color: #475569; }
        .inline-search-control { display: flex; align-items: center; gap: 8px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; padding: 0 10px; }
        .inline-search-control i { color: #64748b; }
        .inline-search-control input { width: 100%; border: none; background: transparent; padding: 10px 0; font: inherit; color: #0f172a; }
        .inline-search-control input:focus { outline: none; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .empty-state { display: grid; gap: 10px; justify-items: center; text-align: center; padding: 42px 20px; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 16px; background: #f8fafc; }
        .empty-state i { font-size: 2rem; color: #94a3b8; }
        .group-list { display: grid; gap: 18px; }
        .class-card { border: 1px solid #dbe4ee; border-radius: 18px; background: #fff; overflow: hidden; }
        .class-header { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; padding: 18px 18px 14px; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border-bottom: 1px solid #dbe4ee; }
        .class-header-main { flex: 1 1 360px; min-width: 0; }
        .class-header-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .class-collapse-btn { border: 1px solid #cbd5e1; border-radius: 12px; padding: 10px 14px; background: #ffffff; color: #334155; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .class-collapse-btn:hover { background: #f8fafc; }
        .class-collapse-btn i { color: #2563eb; }
        .class-export-btn { white-space: nowrap; }
        .class-content[hidden] { display: none; }
        .class-meta, .student-meta, .item-meta { display: flex; flex-wrap: wrap; gap: 8px; }
        .student-list { display: grid; gap: 14px; padding: 16px; }
        .student-card { border: 1px solid #e5edf5; border-radius: 16px; background: #fcfdff; padding: 16px; display: grid; gap: 14px; }
        .student-card.expanded { background: #ffffff; border-color: #cbddee; box-shadow: inset 0 0 0 1px #eff6ff; }
        .student-summary { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .student-toggle { flex: 1 1 320px; min-width: 0; display: flex; gap: 12px; align-items: flex-start; border: none; background: transparent; padding: 0; text-align: left; cursor: pointer; color: inherit; }
        .student-toggle-indicator { width: 38px; height: 38px; flex: 0 0 38px; border-radius: 12px; background: #e0ebf8; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; box-shadow: inset 0 0 0 1px #c7d9ef; }
        .student-title { display: grid; gap: 6px; min-width: 0; }
        .student-name-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; color: #1f2937; font-size: 1rem; }
        .student-detail { display: grid; gap: 14px; padding-top: 14px; border-top: 1px solid #e5edf5; }
        .student-detail[hidden] { display: none; }
        .table-pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 16px 16px; border-top: 1px solid #e5edf5; background: #f8fafc; }
        .table-pagination-info { color: #64748b; font-size: 0.82rem; }
        .table-pagination-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .pager-btn { border: none; border-radius: 8px; background: #e2e8f0; color: #334155; padding: 7px 11px; cursor: pointer; font-size: 0.78rem; font-weight: 700; }
        .pager-btn:disabled { opacity: .45; cursor: not-allowed; }
        .item-list { display: grid; gap: 12px; }
        .item-card { border: 1px solid #dbe4ee; border-radius: 14px; background: #fff; padding: 14px; display: grid; gap: 12px; }
        .item-top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .item-title { display: grid; gap: 8px; }
        .type-badge, .status-badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 5px 10px; font-size: 0.76rem; font-weight: 800; letter-spacing: 0.02em; }
        .type-badge.remedial { background: #fee2e2; color: #991b1b; }
        .type-badge.susulan { background: #dbeafe; color: #1d4ed8; }
        .status-badge { background: #e2e8f0; color: #334155; text-transform: capitalize; }
        .status-badge.status-sudah-mengikuti { background: #dcfce7; color: #166534; }
        .status-badge.status-dijadwalkan { background: #fef3c7; color: #92400e; }
        .status-badge.status-belum-mengikuti { background: #fee2e2; color: #991b1b; }
        .status-badge.status-pending { background: #e2e8f0; color: #334155; }
        .item-status-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .status-box { border: 1px solid #e5edf5; border-radius: 12px; background: #f8fafc; padding: 10px 12px; }
        .status-box span { display: block; font-size: 0.78rem; color: #64748b; margin-bottom: 4px; }
        .status-box strong { color: #0f172a; font-size: 0.9rem; }
        .item-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .notes-block { border-radius: 12px; background: #f8fafc; border: 1px solid #e5edf5; padding: 10px 12px; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); display: none; align-items: center; justify-content: center; padding: 18px; z-index: 10000; }
        .modal-backdrop.open { display: flex; }
        .modal-card { width: min(720px, 100%); max-height: calc(100vh - 36px); overflow: auto; background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 18px 36px rgba(15, 23, 42, 0.18); }
        .modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 20px 20px 14px; border-bottom: 1px solid #e5edf5; }
        .modal-body { padding: 20px; display: grid; gap: 16px; }
        .modal-close { width: 38px; height: 38px; border: none; border-radius: 12px; background: #e2e8f0; color: #334155; cursor: pointer; }
        .modal-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .modal-info { display: grid; gap: 10px; border: 1px solid #e5edf5; border-radius: 14px; background: #f8fafc; padding: 14px; }
        #toast-container { position: fixed; top: 78px; right: 20px; z-index: 9999; display: grid; gap: 8px; }
        .toast { min-width: 240px; padding: 10px 14px; border-radius: 10px; color: #fff; background: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.16); opacity: 0; transform: translateY(-10px); transition: all .25s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        @media (max-width: 1180px) {
            .grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 820px) {
            .stats, .item-status-grid, .modal-form-grid { grid-template-columns: 1fr; }
            .class-header, .student-summary, .table-pagination { flex-direction: column; }
            .class-header-actions, .student-meta, .table-pagination-actions, .result-toolbar-controls .pill-list { width: 100%; justify-content: flex-start; }
            .student-toggle { width: 100%; }
            .result-toolbar-controls { justify-items: stretch; }
            .inline-search-field { width: 100%; }
        }
    </style>
</head>
<body>
    <?php require_once PROJECT_ROOT . '/app/shared/layout/navbar.php'; ?>
    <div id="toast-container"></div>
    <div class="page">
        <div class="grid">
            <aside class="card">
                <div class="stack">
                    <div class="stack" style="gap:8px;">
                        <h1><i class="fa-solid fa-clipboard-check"></i> Remedial/SP &amp; Susulan</h1>
                        <p class="muted">Pantau tindak lanjut per kelas dan per mahasiswa. Status, tanggal pelaksanaan, nilai, dan catatan tersimpan langsung ke backend follow-up.</p>
                    </div>

                    <div class="filter-grid">
                        <div class="field">
                            <label for="examTypeFilter">Jenis Ujian</label>
                            <select id="examTypeFilter">
                                <option value="">Semua jenis ujian</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="academicYearFilter">Tahun Ajaran</label>
                            <select id="academicYearFilter">
                                <option value="">Semua tahun ajaran</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="termFilter">Semester</label>
                            <select id="termFilter">
                                <option value="">Semua semester</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="classFilter">Kelas</label>
                            <select id="classFilter">
                                <option value="">Semua kelas</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="studentFilter">Mahasiswa</label>
                            <select id="studentFilter">
                                <option value="">Semua mahasiswa</option>
                            </select>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn primary" id="refreshBtn"><i class="fa-solid fa-rotate"></i> Muat Ulang</button>
                        <button type="button" class="btn light" id="resetBtn"><i class="fa-solid fa-filter-circle-xmark"></i> Reset Filter</button>
                    </div>

                    <div class="stats">
                        <div class="stat"><strong id="totalItemsStat">0</strong><span class="muted">Total item follow-up</span></div>
                        <div class="stat"><strong id="totalClassesStat">0</strong><span class="muted">Kelas terlibat</span></div>
                        <div class="stat"><strong id="totalStudentsStat">0</strong><span class="muted">Mahasiswa terlibat</span></div>
                        <div class="stat"><strong id="remedialStat">0</strong><span class="muted">Item remedial/SP</span></div>
                        <div class="stat"><strong id="susulanStat">0</strong><span class="muted">Item susulan</span></div>
                        <div class="stat"><strong id="scopeStat">0</strong><span class="muted">Scope kombinasi</span></div>
                    </div>
                </div>
            </aside>

            <section class="card">
                <div class="result-toolbar">
                    <div class="stack" style="gap:8px;">
                        <h2>Daftar Tindak Lanjut</h2>
                        <p class="muted" id="overviewMessage">Memuat data follow-up per kelas dan per mahasiswa.</p>
                    </div>
                    <div class="result-toolbar-controls">
                        <div class="pill-list" id="activeFilterSummary"></div>
                        <div class="inline-search-field">
                            <label for="studentNameSearch" class="inline-search-label">Cari Mahasiswa (Nama)</label>
                            <div class="inline-search-control">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="studentNameSearch" placeholder="Ketik nama mahasiswa..." autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="followUpContent" class="group-list">
                    <div class="empty-state">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <div>Sedang memuat data tindak lanjut...</div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="followUpModal" class="modal-backdrop" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-head">
                <div class="stack" style="gap:6px;">
                    <h2 id="modalTitle">Perbarui Status Tindak Lanjut</h2>
                    <p id="modalSubtitle" class="muted">Pilih status, tanggal, nilai, dan catatan tindak lanjut.</p>
                </div>
                <button type="button" class="modal-close" id="closeModalBtn" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalContext" class="modal-info"></div>
                <form id="followUpForm" class="stack">
                    <div class="modal-form-grid">
                        <div class="field">
                            <label for="statusInput">Status</label>
                            <input type="text" id="statusInput" list="followUpStatusSuggestions" required placeholder="Contoh: sudah mengikuti">
                            <datalist id="followUpStatusSuggestions">
                                <option value="pending"></option>
                                <option value="dijadwalkan"></option>
                                <option value="belum mengikuti"></option>
                                <option value="sudah mengikuti"></option>
                            </datalist>
                        </div>
                        <div class="field">
                            <label for="dateInput">Tanggal</label>
                            <input type="date" id="dateInput">
                        </div>
                        <div class="field">
                            <label for="scoreInput">Nilai</label>
                            <input type="number" id="scoreInput" min="0" max="100" step="0.01" placeholder="Contoh: 78.5">
                        </div>
                        <div class="field">
                            <label for="typeInput">Jenis Tindak Lanjut</label>
                            <input type="text" id="typeInput" readonly>
                        </div>
                    </div>
                    <div class="field">
                        <label for="notesInput">Catatan</label>
                        <textarea id="notesInput" placeholder="Tambahkan catatan tindak lanjut bila diperlukan"></textarea>
                    </div>
                    <div class="actions" style="justify-content:flex-end;">
                        <button type="button" class="btn light" id="cancelModalBtn">Batal</button>
                        <button type="submit" class="btn primary" id="saveStatusBtn"><i class="fa-solid fa-floppy-disk"></i> Simpan Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/shared/utils.js"></script>
    <script src="assets/js/follow_up/index.js"></script>
</body>
</html>
