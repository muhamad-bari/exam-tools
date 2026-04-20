<div id="uploadDetailContent" class="content-section">
    <div class="result-toolbar">
        <div>
            <h2 style="margin-bottom:6px;">Hasil Rekap</h2>
            <p class="muted" id="summaryInfo">Upload file nilai untuk melihat ringkasan nilai murni, SP, dan susulan.</p>
        </div>
        <div class="result-actions">
            <button type="button" class="btn gray" onclick="showDefaultContent()"><i class="fa-solid fa-list"></i> Daftar Kelas</button>
            <button type="button" class="btn gray" id="inactiveStudentsBtn" onclick="openInactiveStudentsModal()" style="display:none;">
                <i class="fa-solid fa-circle-info"></i> Siswa Nonaktif
            </button>
            <button type="button" class="btn primary" id="assignClassBtn" onclick="openAssignClassModal()" disabled>
                <i class="fa-solid fa-user-plus"></i> Masukkan ke Kelas
            </button>
        </div>
    </div>
    <div class="stats">
        <div class="stat"><strong id="totalRows">0</strong><span class="muted">Total ikut rekap</span></div>
        <div class="stat"><strong id="avgNormal">-</strong><span class="muted">Rata-rata normal</span></div>
        <div class="stat"><strong id="avgRemedial">-</strong><span class="muted">Rata-rata SP</span></div>
        <div class="stat"><strong id="avgSusulan">-</strong><span class="muted">Rata-rata susulan</span></div>
        <div class="stat"><strong id="avgFinal">-</strong><span class="muted">Rata-rata akhir</span></div>
        <div class="stat"><strong id="matchedStudents">0</strong><span class="muted">Cocok dengan master</span></div>
        <div class="stat"><strong id="unmatchedStudents">0</strong><span class="muted">Belum ada di master</span></div>
        <div class="stat"><strong id="inactiveStudents">0</strong><span class="muted">Nonaktif di master</span></div>
        <div class="stat"><strong id="duplicateRows">0</strong><span class="muted">Baris NIM duplikat</span></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Normal (B/S · N · H)</th>
                    <th>SP (B/S · N · H)</th>
                    <th>Susulan (B/S · N · H)</th>
                    <th>Final (B/S · N · H)</th>
                    <th>Duplikat NIM</th>
                    <th>Master</th>
                </tr>
            </thead>
            <tbody id="gradeBody"><tr><td colspan="10" style="text-align:center; color:#64748b;">Belum ada data rekap nilai.</td></tr></tbody>
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
