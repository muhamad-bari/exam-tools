<div id="defaultContent" class="content-section active">
    <div class="result-toolbar">
        <div>
            <h2 style="margin-bottom:6px;">Rekap Tersimpan per Kelas</h2>
            <p class="muted" id="storedSummaryInfo">Memuat data rekap nilai tersimpan...</p>
        </div>
        <div class="result-actions">
            <button type="button" class="btn primary" id="openDetailBtn" onclick="showUploadDetailContent()" style="display:none;"><i class="fa-solid fa-table"></i> Lihat Detail Upload Terakhir</button>
            <button type="button" class="btn gray" onclick="loadStoredClassRecaps(true)"><i class="fa-solid fa-rotate"></i> Refresh</button>
        </div>
    </div>
    <div class="card" style="padding:16px; box-shadow:none; background:#f8fafc; margin-bottom:14px;">
        <div class="actions">
            <select id="storedExamTypeFilter" style="flex:1 1 160px; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff; min-width:0;">
                <option value="">Semua Jenis Ujian</option>
            </select>
            <select id="storedAcademicYearFilter" style="flex:1 1 180px; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff; min-width:0;">
                <option value="">Semua Tahun Ajaran</option>
            </select>
            <select id="storedTermFilter" style="flex:1 1 160px; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff; min-width:0;">
                <option value="">Semua Periode</option>
            </select>
        </div>
    </div>
    <div class="table-wrap">
        <table class="class-list-table">
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th>Jumlah Mahasiswa</th>
                    <th>Jumlah Matkul</th>
                    <th>Rata-rata Final</th>
                    <th>Rentang Nilai</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="storedClassBody"><tr><td colspan="6" style="text-align:center; color:#64748b;">Belum ada data rekap tersimpan.</td></tr></tbody>
        </table>
    </div>
</div>
