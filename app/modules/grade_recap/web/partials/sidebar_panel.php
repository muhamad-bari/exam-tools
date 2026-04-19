<div class="card">
    <div class="stack">
        <div>
            <h1><i class="fa-solid fa-chart-column"></i> Rekap Nilai</h1>
            <p class="muted">Upload satu atau banyak file Excel `.xlsx` per kelas dan mata kuliah. Pilih mata kuliah dulu sebelum upload.</p>
        </div>
        <div>
            <label for="subjectSearchInput" class="muted" style="display:block; margin-bottom:8px; font-weight:600;">Mata Kuliah (wajib)</label>
            <input
                type="text"
                id="subjectSearchInput"
                list="subjectSearchList"
                placeholder="Cari mata kuliah..."
                autocomplete="off"
                style="width:100%; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff;"
            >
            <datalist id="subjectSearchList"></datalist>
            <select id="subjectSelect" style="display:none;" aria-hidden="true" tabindex="-1">
                <option value="">-- Pilih mata kuliah --</option>
            </select>
            <p id="subjectSearchInfo" class="muted" style="margin:8px 0 0; font-size:0.84rem;">Ketik nama mata kuliah lalu pilih dari daftar saran.</p>
        </div>
        <div>
            <label for="examTypeSelect" class="muted" style="display:block; margin-bottom:8px; font-weight:600;">Jenis Ujian (wajib)</label>
            <select id="examTypeSelect" style="width:100%; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff;">
                <option value="">-- Pilih jenis ujian --</option>
                <option value="UTS">UTS</option>
                <option value="UAS">UAS</option>
            </select>
        </div>
        <div>
            <label for="academicYearInput" class="muted" style="display:block; margin-bottom:8px; font-weight:600;">Tahun Ajaran (wajib)</label>
            <select id="academicYearInput" style="width:100%; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff;">
                <option value="">-- Pilih tahun ajaran --</option>
            </select>
        </div>
        <div>
            <label for="termSelect" class="muted" style="display:block; margin-bottom:8px; font-weight:600;">Periode Semester (wajib)</label>
            <select id="termSelect" style="width:100%; border:1px solid #cfd8e3; border-radius:10px; padding:10px 12px; background:#fff;">
                <option value="">-- Pilih periode --</option>
            </select>
        </div>
        <div>
            <label class="drop" id="gradeDrop" for="gradeFile">
                <strong>
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Pilih file Excel nilai (bisa multi-file)</span>
                    <span id="gradeFileInfo" class="muted">Format tetap: `Nama(B)`, `NIM(D)`, `B/S(G)`, `Nilai(J)`, `Kategori(K)`. Bulk upload akan memproses semua file untuk kombinasi mata kuliah + jenis ujian + tahun ajaran + periode yang dipilih.</span>
                </strong>
            </label>
            <input type="file" id="gradeFile" accept=".xlsx" multiple style="display:none;">
            <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                <button type="button" id="clearGradeFileBtn" class="btn gray" style="display:none; flex:0 0 auto;" onclick="removeSelectedGradeFiles()"><i class="fa-solid fa-trash"></i> Hapus File Terpilih</button>
            </div>
        </div>
        <div class="actions">
            <button type="button" class="btn primary" onclick="uploadGradeRecap()" id="uploadBtn"><i class="fa-solid fa-upload"></i> Upload & Baca</button>
            <button type="button" class="btn gray" onclick="resetGradeRecap()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
        </div>
        <div class="card" style="padding:16px; box-shadow:none; background:#f8fafc;">
            <h2 style="font-size:1rem; margin-bottom:10px;">Download Template</h2>
            <div class="actions">
                <a href="format/Format-Nilai-PerMatakuliah.xlsx" download class="btn gray"><i class="fa-solid fa-file-arrow-down"></i> Template Per Mata Kuliah</a>
            </div>
        </div>
        <div class="card" style="padding:16px; box-shadow:none; background:#f8fafc;">
            <h2 style="font-size:1rem;">Konfigurasi Kolom Tetap</h2>
            <p class="muted" id="detectedInfo">Belum ada file diproses.</p>
            <div class="pill-list" id="fixedColumns"></div>
        </div>
        <div class="card" style="padding:16px; box-shadow:none; background:#f8fafc;">
            <h2 style="font-size:1rem;">Distribusi Kelas</h2>
            <div class="pill-list" id="classDistribution"><span class="muted">Belum ada data.</span></div>
        </div>
    </div>
</div>
