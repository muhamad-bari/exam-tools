<div class="left-panel">
    <form action="index.php?api=generate_pdf" method="post" enctype="multipart/form-data" id="scheduleForm">
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
                    <input type="text" name="signer_date" class="form-control" value="<?= htmlspecialchars($currentDateLabel, ENT_QUOTES, 'UTF-8') ?>" oninput="updatePreview()">
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
