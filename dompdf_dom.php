<!DOCTYPE html>
<html>
<head>
<style>
    @page {
        margin: 10mm; 
    }
    body {
        font-family: 'Helvetica', sans-serif;
        font-size: 11px;
    }

    /* Grid Utama untuk menata 2 kartu per baris */
    table.grid-container {
        width: 100%;
        border-collapse: separate; 
        border-spacing: 10px; /* Jarak antar kartu */
        margin-top: -10px; /* Kompensasi margin atas */
    }

    table.grid-container td.grid-cell {
        width: 50%;
        vertical-align: top;
        padding: 0;
    }

    /* Kotak Kartu */
    .card-box {
        border: 1px solid #000;
        padding: 5px;
        background-color: #fff;
        height: 110px; /* Tinggi fix agar seragam */
    }

    /* Layout Internal Kartu (QR | Teks) */
    table.card-content {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    /* Kolom QR Code */
    td.qr-area {
        width: 90px;
        text-align: center;
        vertical-align: middle;
        border-right: 1px solid #ccc; /* Garis pemisah abu-abu */
        padding-right: 5px;
    }

    /* Kolom Teks Data */
    td.text-area {
        vertical-align: middle;
        padding-left: 10px;
    }

    /* Gambar QR */
    img.qr-code {
        width: 80px;
        height: 80px;
        display: inline-block; /* Lebih aman daripada block di table cell */
    }

    /* Tabel Data Rincian (NIM, Nama, dll) */
    table.details {
        width: 100%;
        border-collapse: collapse;
    }
    
    table.details td {
        vertical-align: top;
        padding: 1px 0;
    }

    .label {
        font-weight: bold;
        width: 40px;
        white-space: nowrap;
    }
    
    .sep {
        width: 10px;
        text-align: center;
    }
    
    .val {
        /* Membiarkan kolom ini mengisi sisa ruang */
    }

</style>
</head>
<body>

    <table class="grid-container">
        <?php 
        $i = 0;
        foreach($data as $row) {
            // Buka baris baru setiap item genap (0, 2, 4...)
            if ($i % 2 == 0) {
                echo '<tr>';
            }

            // Generate QR Code (In-Memory)
            ob_start();
            QRcode::png($row["isiqr"], null, QR_ECLEVEL_L, 3, 1); 
            $imageString = ob_get_clean();
            $base64 = base64_encode($imageString);
            ?>
            
            <td class="grid-cell">
                <div class="card-box">
                    <table class="card-content">
                        <tr>
                            <td class="qr-area">
                                <?php if($base64): ?>
                                    <img src="data:image/png;base64,<?= $base64 ?>" class="qr-code">
                                <?php endif; ?>
                            </td>

                            <td class="text-area">
                                <table class="details">
                                    <tr>
                                        <td class="label">NIM</td>
                                        <td class="sep">:</td>
                                        <td class="val"><strong><?= htmlspecialchars($row["nim"]) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Nama</td>
                                        <td class="sep">:</td>
                                        <td class="val"><?= htmlspecialchars($row["nama"]) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Kelas</td>
                                        <td class="sep">:</td>
                                        <td class="val"><?= htmlspecialchars($row["kelas"]) ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <?php
            // Tutup baris setiap item ganjil (1, 3, 5...)
            if ($i % 2 == 1) {
                echo '</tr>';
            }
            $i++;
        }

        // Jika jumlah data ganjil, tutup baris terakhir dengan sel kosong
        if ($i % 2 != 0) {
            echo '<td class="grid-cell"></td></tr>';
        }
        ?>
    </table>

</body>
</html>