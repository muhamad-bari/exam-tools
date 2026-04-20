<?php
session_start();
require_once __DIR__ . '/../../../bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai - Exam Tools</title>
    <link rel="stylesheet" href="assets/css/core/base.css">
    <link rel="stylesheet" href="assets/css/components/layout.css">
    <link rel="stylesheet" href="assets/css/components/grade_recap.css">
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
        .drop strong { display: grid; gap: 10px; justify-items: center; width: 100%; min-width: 0; }
        .drop strong span { max-width: 100%; overflow-wrap: anywhere; word-break: break-word; }
        .drop i { font-size: 2rem; color: #2563eb; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; border-radius: 10px; padding: 10px 14px; color: #fff; text-decoration: none; cursor: pointer; font-weight: 600; }
        .btn.primary { background: #2563eb; }
        .btn.gray { background: #64748b; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; min-width: 0; }
        .actions .btn { flex: 1 1 160px; min-width: 0; }
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
        .badge.info { background: #fff7ed; color: #9a3412; }
        .result-toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
        .result-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .content-section { display: none; }
        .content-section.active { display: block; }
        .class-list-table td:last-child { width: 1%; white-space: nowrap; }
        .inline-link { border: none; background: transparent; color: #2563eb; cursor: pointer; padding: 0; font-weight: 600; }
        .inline-link:hover { text-decoration: underline; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: none; align-items: center; justify-content: center; z-index: 10000; padding: 16px; }
        .modal-backdrop.open { display: flex; }
        .modal-card { width: min(900px, 100%); max-height: calc(100vh - 48px); overflow: auto; background: #fff; border: 1px solid #dbe4ee; border-radius: 16px; box-shadow: 0 18px 36px rgba(15, 23, 42, 0.18); padding: 18px; }
        .modal-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .modal-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
        .modal-table-wrap { border: 1px solid #dbe4ee; border-radius: 12px; overflow: hidden; }
        .modal-table-wrap table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .modal-table-wrap th, .modal-table-wrap td { padding: 9px 10px; border-bottom: 1px solid #e5edf5; font-size: 0.86rem; }
        .modal-table-wrap tr:last-child td { border-bottom: none; }
        .modal-table-wrap th { background: #f8fafc; color: #475569; }
        .assign-row-failed { background: #fff1f2; }
        .assign-row-failed td { color: #9f1239; }
        .assign-error-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 8px; font-size: 0.75rem; background: #fee2e2; color: #991b1b; }
        #toast-container { position: fixed; top: 78px; right: 20px; z-index: 9999; display: grid; gap: 8px; }
        .toast { min-width: 240px; padding: 10px 14px; border-radius: 10px; color: #fff; background: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.16); opacity: 0; transform: translateY(-10px); transition: all .25s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        @media (max-width: 1100px) { .grid, .stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php require_once PROJECT_ROOT . '/app/shared/layout/navbar.php'; ?>
    <div id="toast-container"></div>
    <div class="page">
        <div class="grid">
            <?php require __DIR__ . '/partials/sidebar_panel.php'; ?>
            <div class="card">
                <?php require __DIR__ . '/partials/default_content.php'; ?>
                <?php require __DIR__ . '/partials/upload_detail_content.php'; ?>
            </div>
        </div>
    </div>
    <?php require __DIR__ . '/partials/assign_modal.php'; ?>
    <script src="assets/js/shared/utils.js"></script>
    <script src="assets/js/grade_recap/state.js"></script>
    <script src="assets/js/grade_recap/table.js"></script>
    <script src="assets/js/grade_recap/stored.js"></script>
    <script src="assets/js/grade_recap/assign.js"></script>
    <script src="assets/js/grade_recap/upload.js"></script>
    <script src="assets/js/grade_recap/bootstrap.js"></script>
</body>
</html>
