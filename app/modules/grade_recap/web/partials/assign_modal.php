<div class="modal-backdrop" id="assignClassModal" onclick="handleAssignModalBackdrop(event)">
    <div class="modal-card">
        <div class="modal-head">
            <div>
                <h3 style="margin:0; color:#0f172a;"><i class="fa-solid fa-users-gear"></i> Tambah Mahasiswa ke Master</h3>
                <p class="muted" id="assignModalInfo" style="margin:6px 0 0;">Pilih kelas tujuan lalu tentukan mahasiswa belum-match yang ingin dimasukkan.</p>
            </div>
            <button type="button" class="btn gray" onclick="closeAssignClassModal()"><i class="fa-solid fa-xmark"></i> Tutup</button>
        </div>
        <div class="modal-actions">
            <select id="assignClassSelect" style="min-width:240px; border:1px solid #cfd8e3; border-radius:8px; padding:9px 10px;">
                <option value="">-- Pilih kelas tujuan --</option>
            </select>
            <label class="muted" style="display:inline-flex; align-items:center; gap:6px;">
                <input type="checkbox" id="assignSelectAll" onchange="toggleAssignSelectAll(this.checked)">
                Pilih semua
            </label>
            <button type="button" class="btn primary" id="assignSubmitBtn" onclick="submitAssignClass()">
                <i class="fa-solid fa-floppy-disk"></i> Simpan ke Master
            </button>
        </div>
        <div class="modal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:44px;"></th>
                        <th style="width:160px;">NIM</th>
                        <th>Nama</th>
                        <th style="width:120px;">Final</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="assignRowsBody">
                    <tr><td colspan="5" style="text-align:center; color:#64748b;">Belum ada mahasiswa yang perlu dimasukkan.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
