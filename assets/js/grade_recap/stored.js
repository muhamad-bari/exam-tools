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
                    <a class="btn primary icon-btn" href="${downloadUrl}" title="Download XLSX ${className}" aria-label="Download XLSX ${className}"><i class="fa-solid fa-download" aria-hidden="true"></i></a>
                    <button type="button" class="btn gray icon-btn" onclick="openClassSubjectsModal('${classNameParam}')" title="Info mata kuliah ${className}" aria-label="Info mata kuliah ${className}"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>
                    <button type="button" class="btn gray icon-btn" onclick="deleteClassRecap('${classNameParam}')" title="Hapus rekap ${className}" aria-label="Hapus rekap ${className}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
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

async function loadStoredClassRecaps(showToastOnSuccess = false) {
    const params = buildStoredFilterParams();
    params.set('action', 'list_class_recaps');
    try {
        const response = await fetch(`index.php?api=rekap_nilai&${params.toString()}`);
        const res = await response.json();
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
    } catch (error) {
        renderStoredClassList([], {});
        showToast(error.message, 'error');
    }
}

function buildClassSubjectScopePayload(row) {
    return {
        class_name: currentClassSubjectModalClassName,
        subject_id: Number(row?.subject_id || 0),
        exam_type: String(row?.exam_type || ''),
        academic_year: String(row?.academic_year || ''),
        term: String(row?.term || ''),
    };
}

function formatClassSubjectPeriod(row) {
    const bits = [];
    if (row?.exam_type) {
        bits.push(row.exam_type);
    }
    if (row?.term) {
        bits.push(row.term);
    }
    if (row?.academic_year) {
        bits.push(row.academic_year);
    }
    return bits.length ? bits.join(' • ') : '-';
}

function renderClassSubjectRows(items, meta = {}) {
    const body = document.getElementById('classSubjectsBody');
    const info = document.getElementById('classSubjectsModalInfo');
    if (!body || !info) {
        return;
    }

    classSubjectRows = Array.isArray(items) ? items.slice() : [];
    const className = meta.class_name || currentClassSubjectModalClassName || '-';
    const activeFilters = meta.active_filters || {};
    const filterBits = [activeFilters.exam_type, activeFilters.term, activeFilters.academic_year].filter(Boolean);
    info.textContent = `${classSubjectRows.length} mata kuliah tersimpan untuk kelas ${className}${filterBits.length ? ` pada filter ${filterBits.join(' • ')}` : ''}.`;

    if (!classSubjectRows.length) {
        body.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#64748b;">Belum ada mata kuliah tersimpan untuk kelas/filter ini.</td></tr>';
        cancelClassSubjectEdit();
        return;
    }

    body.innerHTML = classSubjectRows.map((row, index) => {
        const subjectName = escapeHtml(row.subject_name || '-');
        const subjectLabel = escapeHtml(row.label || row.subject_name || '-');
        return `<tr>
            <td><strong>${subjectName}</strong><div class="muted">${subjectLabel}</div></td>
            <td>${escapeHtml(formatClassSubjectPeriod(row))}</td>
            <td>${escapeHtml(row.total_students || 0)}</td>
            <td>${formatNumber(row.avg_final)}</td>
            <td>${formatNumber(row.lowest_final)} - ${formatNumber(row.highest_final)}</td>
            <td>
                <span class="class-list-actions">
                    <button type="button" class="btn primary icon-btn" onclick="startClassSubjectEdit(${index})" title="Edit mata kuliah ${subjectName}" aria-label="Edit mata kuliah ${subjectName}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                    <button type="button" class="btn gray icon-btn" onclick="deleteClassSubjectValues(${index})" title="Hapus nilai ${subjectName}" aria-label="Hapus nilai ${subjectName}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </span>
            </td>
        </tr>`;
    }).join('');
}

function setClassSubjectsLoading(message = 'Memuat mata kuliah kelas...') {
    const body = document.getElementById('classSubjectsBody');
    const info = document.getElementById('classSubjectsModalInfo');
    if (info) {
        info.textContent = message;
    }
    if (body) {
        body.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#64748b;">Memuat data...</td></tr>';
    }
}

function openClassSubjectsModal(encodedClassName) {
    const className = decodeURIComponent(encodedClassName || '');
    if (!className) {
        showToast('Nama kelas tidak valid', 'error');
        return;
    }

    currentClassSubjectModalClassName = className;
    classSubjectEditState = null;
    const title = document.getElementById('classSubjectsModalTitle');
    const modal = document.getElementById('classSubjectsModal');
    if (title) {
        title.innerHTML = `<i class="fa-solid fa-circle-info"></i> Info Mata Kuliah - ${escapeHtml(className)}`;
    }
    setClassSubjectsLoading();
    hideClassSubjectEditPanel();
    if (modal) {
        modal.classList.add('open');
    }

    const loadSubjectsPromise = Array.isArray(availableSubjects) && availableSubjects.length
        ? Promise.resolve()
        : loadSubjectOptions();

    loadSubjectsPromise
        .then(() => loadClassSubjectRows())
        .catch(error => showToast(error.message, 'error'));
}

function closeClassSubjectsModal() {
    const modal = document.getElementById('classSubjectsModal');
    if (modal) {
        modal.classList.remove('open');
    }
    classSubjectEditState = null;
    currentClassSubjectModalClassName = null;
    hideClassSubjectEditPanel();
}

function handleClassSubjectsModalBackdrop(event) {
    if (event.target && event.target.id === 'classSubjectsModal') {
        closeClassSubjectsModal();
    }
}

async function loadClassSubjectRows() {
    if (!currentClassSubjectModalClassName) {
        return;
    }

    const params = buildStoredFilterParams();
    params.set('action', 'list_class_subjects');
    params.set('class_name', currentClassSubjectModalClassName);

    const response = await fetch(`index.php?api=rekap_nilai&${params.toString()}`);
    const res = await response.json();
    if (!res.success) {
        throw new Error(res.message || 'Gagal memuat mata kuliah kelas');
    }
    renderClassSubjectRows(res.data || [], res.meta || {});
}

function hideClassSubjectEditPanel() {
    const panel = document.getElementById('classSubjectEditPanel');
    if (panel) {
        panel.style.display = 'none';
    }
    closeClassSubjectEditSuggestions();
}

function startClassSubjectEdit(index) {
    const row = classSubjectRows[index] || null;
    if (!row) {
        showToast('Mata kuliah tidak valid', 'error');
        return;
    }

    classSubjectEditState = { index, row };
    const panel = document.getElementById('classSubjectEditPanel');
    const input = document.getElementById('classSubjectEditSearchInput');
    const label = document.getElementById('classSubjectEditScopeLabel');
    const datalist = document.getElementById('classSubjectEditSearchList');
    if (!panel || !input || !label) {
        return;
    }

    input.value = row.subject_name || '';
    input.dataset.selectedSubjectId = String(row.subject_id || '');
    label.textContent = `Mengubah ${row.subject_name || '-'} (${formatClassSubjectPeriod(row)}) untuk kelas ${currentClassSubjectModalClassName || '-'}. Nilai tidak diubah.`;
    if (datalist) {
        datalist.innerHTML = availableSubjects.map(subject => `<option value="${escapeHtml(subject.name || '')}"></option>`).join('');
    }
    panel.style.display = 'block';
    input.focus();
    renderClassSubjectEditSuggestions();
}

function cancelClassSubjectEdit() {
    classSubjectEditState = null;
    const input = document.getElementById('classSubjectEditSearchInput');
    if (input) {
        input.value = '';
        delete input.dataset.selectedSubjectId;
    }
    hideClassSubjectEditPanel();
}

function getClassSubjectEditMatches() {
    const input = document.getElementById('classSubjectEditSearchInput');
    const query = String(input?.value || '').trim().toLowerCase();
    const source = Array.isArray(availableSubjects) ? availableSubjects : [];
    if (!query) {
        return source.slice(0, 10);
    }

    return source
        .filter(subject => String(subject?.name || '').toLowerCase().includes(query))
        .slice(0, 10);
}

function renderClassSubjectEditSuggestions() {
    const input = document.getElementById('classSubjectEditSearchInput');
    const suggestions = document.getElementById('classSubjectEditSuggestions');
    if (!input || !suggestions) {
        return;
    }

    const matches = getClassSubjectEditMatches();
    if (!matches.length) {
        closeClassSubjectEditSuggestions();
        return;
    }

    suggestions.innerHTML = matches.map(subject => `
        <button type="button" class="subject-suggestion" role="option" data-subject-id="${Number(subject.id)}">${escapeHtml(subject.name)}</button>
    `).join('');
    suggestions.classList.add('open');
    input.setAttribute('aria-expanded', 'true');
}

function closeClassSubjectEditSuggestions() {
    const input = document.getElementById('classSubjectEditSearchInput');
    const suggestions = document.getElementById('classSubjectEditSuggestions');
    if (!input || !suggestions) {
        return;
    }

    suggestions.classList.remove('open');
    suggestions.innerHTML = '';
    input.setAttribute('aria-expanded', 'false');
}

function chooseClassSubjectEditSuggestion(subjectId) {
    const input = document.getElementById('classSubjectEditSearchInput');
    const subject = availableSubjectsById.get(String(subjectId)) || null;
    if (!input || !subject) {
        return;
    }

    input.value = subject.name;
    input.dataset.selectedSubjectId = String(subject.id);
    closeClassSubjectEditSuggestions();
}

function getSelectedClassSubjectEditSubject() {
    const input = document.getElementById('classSubjectEditSearchInput');
    if (!input) {
        return null;
    }

    const selectedId = String(input.dataset.selectedSubjectId || '');
    const selectedById = selectedId ? (availableSubjectsById.get(selectedId) || null) : null;
    if (selectedById && selectedById.name === input.value) {
        return selectedById;
    }

    const selectedByName = availableSubjectsByName.get(String(input.value || '').trim().toLowerCase()) || null;
    if (selectedByName) {
        input.dataset.selectedSubjectId = String(selectedByName.id);
    }
    return selectedByName;
}

function setupClassSubjectModalInteractions() {
    const input = document.getElementById('classSubjectEditSearchInput');
    const suggestions = document.getElementById('classSubjectEditSuggestions');
    if (!input || !suggestions) {
        return;
    }

    input.addEventListener('input', () => {
        delete input.dataset.selectedSubjectId;
        renderClassSubjectEditSuggestions();
    });
    input.addEventListener('focus', renderClassSubjectEditSuggestions);
    input.addEventListener('click', renderClassSubjectEditSuggestions);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeClassSubjectEditSuggestions();
        }
    });

    suggestions.addEventListener('mousedown', (event) => {
        event.preventDefault();
        const option = event.target.closest('.subject-suggestion');
        if (option) {
            chooseClassSubjectEditSuggestion(option.dataset.subjectId);
        }
    });

    document.addEventListener('pointerdown', (event) => {
        const wrap = document.getElementById('classSubjectEditSearchWrap');
        if (wrap && !wrap.contains(event.target)) {
            closeClassSubjectEditSuggestions();
        }
    });
}

async function saveClassSubjectEdit() {
    if (!classSubjectEditState?.row || !currentClassSubjectModalClassName) {
        showToast('Pilih mata kuliah yang akan diedit', 'error');
        return;
    }

    const selectedSubject = getSelectedClassSubjectEditSubject();
    if (!selectedSubject) {
        showToast('Pilih mata kuliah dari daftar saran', 'error');
        return;
    }

    const submitBtn = document.getElementById('classSubjectEditSaveBtn');
    const originalHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan';
    }

    try {
        const payload = {
            ...buildClassSubjectScopePayload(classSubjectEditState.row),
            new_subject_id: Number(selectedSubject.id),
        };
        const response = await fetch('index.php?api=rekap_nilai&action=update_class_subject', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Gagal memperbarui mata kuliah kelas');
        }

        cancelClassSubjectEdit();
        await loadStoredClassRecaps(false);
        await loadClassSubjectRows();
        showToast(result.message || 'Mata kuliah kelas berhasil diperbarui');
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    }
}

async function deleteClassSubjectValues(index) {
    const row = classSubjectRows[index] || null;
    if (!row || !currentClassSubjectModalClassName) {
        showToast('Mata kuliah tidak valid', 'error');
        return;
    }

    const label = row.label || row.subject_name || 'mata kuliah ini';
    if (!confirm(`Hapus nilai ${label} untuk kelas "${currentClassSubjectModalClassName}"?`)) {
        return;
    }

    try {
        const response = await fetch('index.php?api=rekap_nilai&action=delete_class_subject_values', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(buildClassSubjectScopePayload(row)),
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Gagal menghapus nilai mata kuliah kelas');
        }

        cancelClassSubjectEdit();
        await loadStoredClassRecaps(false);
        await loadClassSubjectRows();
        showToast(`${result.deleted_rows || 0} baris nilai mata kuliah berhasil dihapus`);
    } catch (error) {
        showToast(error.message, 'error');
    }
}
