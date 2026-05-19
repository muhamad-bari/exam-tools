<div class="modal-backdrop" id="classSubjectsModal" onclick="handleClassSubjectsModalBackdrop(event)">
    <div class="modal-card class-subjects-modal-card">
        <div class="modal-head">
            <div>
                <h3 id="classSubjectsModalTitle" style="margin:0; color:var(--text-color);"><i class="fa-solid fa-circle-info"></i> Info Mata Kuliah</h3>
                <p class="muted" id="classSubjectsModalInfo" style="margin:var(--space-2) 0 0;">Pilih kelas untuk melihat mata kuliah tersimpan.</p>
            </div>
            <button type="button" class="btn gray" onclick="closeClassSubjectsModal()" title="Tutup info mata kuliah" aria-label="Tutup info mata kuliah"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Tutup</button>
        </div>
        <div class="class-subject-edit-panel" id="classSubjectEditPanel" style="display:none;">
            <div>
                <strong>Edit mata kuliah</strong>
                <p class="muted" id="classSubjectEditScopeLabel">Pilih mata kuliah pengganti dari master.</p>
            </div>
            <div class="class-subject-edit-grid">
                <div class="subject-search-wrap" id="classSubjectEditSearchWrap">
                    <input
                        type="text"
                        id="classSubjectEditSearchInput"
                        list="classSubjectEditSearchList"
                        placeholder="Cari mata kuliah master..."
                        autocomplete="off"
                        class="class-subject-edit-input"
                        aria-autocomplete="list"
                        aria-controls="classSubjectEditSuggestions"
                        aria-expanded="false"
                    >
                    <div id="classSubjectEditSuggestions" class="subject-suggestions" role="listbox"></div>
                    <datalist id="classSubjectEditSearchList"></datalist>
                </div>
                <div class="class-subject-edit-actions">
                    <button type="button" class="btn primary" id="classSubjectEditSaveBtn" onclick="saveClassSubjectEdit()"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan</button>
                    <button type="button" class="btn gray" onclick="cancelClassSubjectEdit()"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Batal</button>
                </div>
            </div>
        </div>
        <div class="modal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th style="width:190px;">Scope</th>
                        <th style="width:110px;">Mahasiswa</th>
                        <th style="width:110px;">Rata-rata</th>
                        <th style="width:140px;">Rentang</th>
                        <th style="width:96px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="classSubjectsBody">
                    <tr><td colspan="6" style="text-align:center; color:var(--muted-color);">Belum ada mata kuliah dipilih.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
