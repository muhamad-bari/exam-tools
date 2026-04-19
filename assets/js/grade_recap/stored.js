function renderStoredClassList(items, meta = {}) {
    const body = document.getElementById('storedClassBody');
    const summary = document.getElementById('storedSummaryInfo');
    applyStoredFilterMeta(meta);
    const activeFilters = meta.active_filters || {};
    const activeBits = [];
    if (activeFilters.exam_type) {
        activeBits.push(activeFilters.exam_type);
    }
    if (activeFilters.term) {
        activeBits.push(activeFilters.term);
    }
    if (activeFilters.academic_year) {
        activeBits.push(activeFilters.academic_year);
    }

    if (!items || !items.length) {
        summary.textContent = activeBits.length ? `Belum ada data rekap untuk filter: ${activeBits.join(' • ')}` : 'Belum ada data kelas dengan rekap nilai tersimpan.';
        body.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#64748b;">Belum ada data rekap tersimpan.</td></tr>';
        return;
    }

    summary.textContent = `Import terakhir: ${meta.file_name || '-'} (sheet ${meta.sheet_name || '-'})${activeBits.length ? ` • Filter: ${activeBits.join(' • ')}` : ''}`;
    body.innerHTML = items.map(item => {
        const className = escapeHtml(item.class_name || 'Tanpa kelas');
        const classNameParam = encodeURIComponent(item.class_name || '');
        const downloadParams = buildStoredFilterParams();
        downloadParams.set('class_name', item.class_name || '');
        downloadParams.set('action', 'export_class_recap_xlsx');
        const downloadUrl = `index.php?api=rekap_nilai&${downloadParams.toString()}`;
        return `<tr>
            <td>${className}</td>
            <td>${escapeHtml(item.total_students || 0)}</td>
            <td>${escapeHtml(item.total_subjects || 0)}</td>
            <td>${formatNumber(item.avg_final)}</td>
            <td>${formatNumber(item.lowest_final)} - ${formatNumber(item.highest_final)}</td>
            <td>
                <span class="class-list-actions">
                    <a class="btn primary" href="${downloadUrl}"><i class="fa-solid fa-download"></i> XLSX</a>
                    <button type="button" class="btn gray" onclick="deleteClassRecap('${classNameParam}')"><i class="fa-solid fa-trash"></i> Hapus</button>
                </span>
            </td>
        </tr>`;
    }).join('');
}

function deleteClassRecap(encodedClassName) {
    const className = decodeURIComponent(encodedClassName || '');
    if (!className) {
        showToast('Nama kelas tidak valid', 'error');
        return;
    }

    if (!confirm(`Hapus seluruh nilai tersimpan untuk kelas "${className}"?`)) {
        return;
    }

    fetch('index.php?api=rekap_nilai&action=delete_class_recap', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ class_name: className }),
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Gagal menghapus nilai kelas');
            }
            loadStoredClassRecaps(false);
            showToast(`${res.deleted_rows || 0} baris nilai berhasil dihapus`);
        })
        .catch(error => showToast(error.message, 'error'));
}

function loadStoredClassRecaps(showToastOnSuccess = false) {
    const params = buildStoredFilterParams();
    params.set('action', 'list_class_recaps');
    return fetch(`index.php?api=rekap_nilai&${params.toString()}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Gagal memuat daftar rekap kelas');
            }
            renderStoredClassList(res.data || [], res.meta || {});
            if (!currentImportId && res?.meta?.import_id) {
                currentImportId = Number(res.meta.import_id) || null;
            }
            if (showToastOnSuccess) {
                showToast(res.message || 'Daftar rekap kelas diperbarui');
            }
        })
        .catch(error => {
            renderStoredClassList([], {});
            showToast(error.message, 'error');
        });
}
