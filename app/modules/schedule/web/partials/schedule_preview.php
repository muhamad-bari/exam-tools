<div class="right-panel">
    <div class="preview-paper">
        <div id="previewCard">
            <img id="previewLogoImg" src="" style="display:none; width:60px; height:auto; position:absolute; margin-left:20px;">
            <div style="text-align:center; margin-bottom:20px; padding:0 40px 0 110px;">
                <h3 id="prev_h1" style="margin:0; font-size:14px;">AKADEMI KEBIDANAN WIJAYA HUSADA</h3>
                <h3 id="prev_h2" style="margin:0; font-size:14px;"></h3>
                <h4 id="prev_sub" style="margin:5px 0 0 0; font-weight:normal; font-size:12px;">JADWAL UJIAN...</h4>
            </div>
            <div style="margin-bottom:10px; font-weight:bold; font-size:11px; border:1px dashed #ccc; padding:5px; display:flex; justify-content:space-between; gap:12px;">
                <div>
                    <span id="previewStudentSource">[Preview data mahasiswa]</span><br>
                    KELAS: <span id="previewClassName">-</span><br>
                    NAMA: <span id="previewStudentName">CONTOH MAHASISWA</span><br>
                    NIM: <span id="previewStudentNim">12345678</span>
                </div>
                <div>
                    <img id="prev_qr" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=CONTOH_I_12345678" style="width:60px; height:60px; border:1px solid #ddd;">
                </div>
            </div>
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                <thead>
                    <tr style="background:#eee;">
                        <th style="border:1px solid #000; padding:4px; font-size:11px;">No</th>
                        <th style="border:1px solid #000; padding:4px; font-size:11px;">Mata Kuliah</th>
                        <th style="border:1px solid #000; padding:4px; font-size:11px;">Hari/Tanggal</th>
                        <th style="border:1px solid #000; padding:4px; font-size:11px;">Jam</th>
                        <th style="border:1px solid #000; padding:4px; font-size:11px;">Ruang</th>
                        <th style="border:1px solid #000; padding:4px; font-size:11px;">TTD</th>
                    </tr>
                </thead>
                <tbody id="previewScheduleBody"></tbody>
            </table>
            <div style="text-align:center; float:right; width:45%;">
                <p id="prev_date" style="margin-bottom:0; font-size:11px;">Bogor, <?= htmlspecialchars($currentDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p style="margin-bottom:40px; font-size:11px;">Mengetahui<br><span id="prev_inst_signer">Akademi Kebidanan Wijaya Husada</span><br><span id="prev_title">Direktur</span></p>
                <p id="prev_name" style="font-weight:bold; text-decoration:underline; font-size:11px;">Elpinaria Girsang, S.ST., M.K.M.</p>
            </div>
        </div>
    </div>
</div>
