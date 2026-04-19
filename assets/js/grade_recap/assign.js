function renderAssignRows() {
    const body = document.getElementById('assignRowsBody');
    const rows = getUnmatchedRows();

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#64748b;">Semua mahasiswa sudah cocok dengan master.</td></tr>';
        return;
    }

    body.innerHTML = rows.map(item => {
        const key = String(item.nim);
        const checked = assignSelectedNims.has(String(item.nim)) ? 'checked' : '';
        const failedMessage = assignFailedByNim[key] || '';
        const rowClass = failedMessage ? 'assign-row-failed' : '';
        const statusCell = failedMessage
            ? `<span class="assign-error-pill" title="${escapeHtml(failedMessage)}">Gagal: ${escapeHtml(failedMessage)}</span>`
            : '<span class="muted">Siap diproses</span>';
        return `<tr class="${rowClass}">
            <td><input type="checkbox" class="assign-row-checkbox" data-nim="${escapeHtml(String(item.nim))}" ${checked}></td>
            <td>${escapeHtml(item.nim)}</td>
            <td>${escapeHtml(item.nama)}</td>
            <td>${formatNumber(item.final_score)}</td>
            <td>${statusCell}</td>
        </tr>`;
    }).join('');

    body.querySelectorAll('.assign-row-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', (event) => {
            const target = event.currentTarget;
            const nim = target?.dataset?.nim || '';
            toggleAssignNim(nim, target.checked);
        });
    });
}

function toggleAssignNim(nim, checked) {
    const key = String(nim);
    if (checked) {
        assignSelectedNims.add(key);
    } else {
        assignSelectedNims.delete(key);
    }
    if (checked && assignFailedByNim[key]) {
        delete assignFailedByNim[key];
        renderAssignRows();
    }
}

function toggleAssignSelectAll(checked) {
    const rows = getUnmatchedRows();
    assignSelectedNims = new Set(checked ? rows.map(item => String(item.nim)) : []);
    renderAssignRows();
}

function closeAssignClassModal() {
    document.getElementById('assignClassModal').classList.remove('open');
}

function handleAssignModalBackdrop(event) {
    if (event.target && event.target.id === 'assignClassModal') {
        closeAssignClassModal();
    }
}

function loadClassOptions() {
    return fetch('index.php?api=master_data&action=list_classes')
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Gagal memuat daftar kelas');
            }

            const select = document.getElementById('assignClassSelect');
            select.innerHTML = '<option value="">-- Pilih kelas tujuan --</option>';
            (res.data || []).forEach(cls => {
                const option = document.createElement('option');
                option.value = String(cls.id);
                option.textContent = cls.name || cls.code || `Kelas #${cls.id}`;
                select.appendChild(option);
            });
        });
}

function openAssignClassModal() {
    const rows = getUnmatchedRows();
    if (!rows.length) {
        showToast('Semua mahasiswa sudah cocok dengan master', 'success');
        return;
    }

    assignSelectedNims = new Set(rows.map(item => String(item.nim)));
    assignFailedByNim = {};
    document.getElementById('assignSelectAll').checked = true;
    renderAssignRows();
    loadClassOptions()
        .then(() => {
            document.getElementById('assignModalInfo').textContent = `${rows.length} mahasiswa belum ada di master. Pilih kelas tujuan lalu simpan.`;
            document.getElementById('assignClassModal').classList.add('open');
        })
        .catch(error => showToast(error.message, 'error'));
}

async function submitAssignClass() {
    const classSelect = document.getElementById('assignClassSelect');
    const classId = parseInt(classSelect.value || '0', 10);
    if (!classId) {
        showToast('Pilih kelas tujuan terlebih dahulu', 'error');
        return;
    }

    const className = classSelect.options[classSelect.selectedIndex]?.textContent || '';
    const selectedRows = getUnmatchedRows().filter(item => assignSelectedNims.has(String(item.nim)));
    if (!selectedRows.length) {
        showToast('Pilih minimal 1 mahasiswa', 'error');
        return;
    }

    const submitBtn = document.getElementById('assignSubmitBtn');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    let successCount = 0;
    let failedCount = 0;
    const failedNow = {};
    const syncedRows = [];

    for (const row of selectedRows) {
        try {
            const response = await fetch('index.php?api=master_data&action=create_student', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    class_id: classId,
                    name: String(row.nama || '').trim(),
                    nim: String(row.nim || '').trim(),
                }),
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || `Gagal menambah ${row.nim}`);
            }

            row.matched_master = true;
            row.kelas = className;
            row.master_class = className;
            row.student_id = result?.data?.id || row.student_id || null;
            row.nama = result?.data?.nama || row.nama;
            delete assignFailedByNim[String(row.nim)];
            syncedRows.push({
                nim: String(row.nim),
                class_name: className,
                student_id: row.student_id,
                student_name: String(row.nama || '').trim(),
            });
            successCount++;
        } catch (error) {
            failedCount++;
            failedNow[String(row.nim)] = error?.message || 'Gagal menambah mahasiswa';
        }
    }

    assignFailedByNim = failedNow;

    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHtml;

    renderPagedTable();
    renderDistribution(buildDistributionFromRows());
    refreshMasterStats();

    if (syncedRows.length > 0 && currentImportId) {
        try {
            await fetch('index.php?api=rekap_nilai&action=sync_assigned_students', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ import_id: currentImportId, rows: syncedRows }),
            });
        } catch (error) {
            showToast('Sinkronisasi rekap tersimpan gagal, silakan refresh', 'error');
        }
    }

    loadStoredClassRecaps(false);

    if (successCount > 0) {
        showToast(`${successCount} mahasiswa berhasil dimasukkan ke master`);
    }
    if (failedCount > 0) {
        assignSelectedNims = new Set(Object.keys(failedNow));
        document.getElementById('assignSelectAll').checked = false;
        renderAssignRows();
        showToast(`${failedCount} mahasiswa gagal diproses. Baris gagal sudah di-highlight untuk retry.`, 'error');
    } else {
        closeAssignClassModal();
    }
}
