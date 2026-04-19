function loadSubjectOptions() {
    return fetch('index.php?api=master_data&action=list_subjects')
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Gagal memuat mata kuliah');
            }

            const select = document.getElementById('subjectSelect');
            select.innerHTML = '<option value="">-- Pilih mata kuliah --</option>';
            (res.data || []).forEach(subject => {
                const option = document.createElement('option');
                option.value = String(subject.id);
                option.textContent = subject.name;
                select.appendChild(option);
            });
        });
}

function loadAcademicPeriodOptions() {
    return fetch('index.php?api=master_data&action=list_academic_periods')
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Gagal memuat periode akademik');
            }

            setMasterAcademicPeriods(res.data || []);
        });
}

function preventDefaults(event) {
    event.preventDefault();
    event.stopPropagation();
}

function setupDragAndDrop() {
    const dropZone = document.getElementById('gradeDrop');
    const input = document.getElementById('gradeFile');
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });
    dropZone.addEventListener('drop', event => {
        const files = event.dataTransfer.files;
        if (!files || !files.length) {
            return;
        }
        try {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(files[0]);
            input.files = dataTransfer.files;
        } catch (error) {
        }
        updateSelectedFileInfo();
    }, false);
}

function updateSelectedFileInfo() {
    const input = document.getElementById('gradeFile');
    const info = document.getElementById('gradeFileInfo');
    const file = input.files && input.files[0] ? input.files[0] : null;

    if (!file) {
        info.textContent = 'Format tetap: `Nama(B)`, `NIM(D)`, `B/S(G)`, `Nilai(J)`, `Kategori(K)`. Upload ulang akan memperbarui recap aktif untuk kombinasi mata kuliah + jenis ujian + tahun ajaran + periode + kelas yang sama.';
        return;
    }

    if (!file.name.toLowerCase().endsWith('.xlsx')) {
        showToast('File harus berformat .xlsx', 'error');
        input.value = '';
        info.textContent = 'Format tetap: `Nama(B)`, `NIM(D)`, `B/S(G)`, `Nilai(J)`, `Kategori(K)`. Upload ulang akan memperbarui recap aktif untuk kombinasi mata kuliah + jenis ujian + tahun ajaran + periode + kelas yang sama.';
        return;
    }

    info.textContent = file.name;
}

function uploadGradeRecap() {
    const input = document.getElementById('gradeFile');
    if (!input.files || !input.files.length) {
        showToast('Pilih file Excel nilai terlebih dahulu', 'error');
        return;
    }

    const subject = syncSelectedSubjectFromDom();
    const examType = syncSelectedExamTypeFromDom();
    const academicYear = syncSelectedAcademicYearFromDom();
    const term = syncSelectedTermFromDom();

    const formData = new FormData();
    formData.append('grades_file', input.files[0]);
    if (!subject) {
        showToast('Pilih mata kuliah dulu sebelum upload', 'error');
        return;
    }
    if (!examType) {
        showToast('Pilih jenis ujian dulu sebelum upload', 'error');
        return;
    }
    if (!academicYear) {
        showToast('Pilih tahun ajaran dari master periode akademik terlebih dahulu', 'error');
        return;
    }
    if (!term) {
        showToast('Pilih periode semester dulu sebelum upload', 'error');
        return;
    }
    formData.append('subject_id', String(subject.id));
    formData.append('exam_type', examType);
    formData.append('academic_year', academicYear);
    formData.append('term', term);

    fetch('index.php?api=rekap_nilai', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Upload nilai gagal');
            }

            allGradeRows = res.data || [];
            gradePage = 1;
            assignFailedByNim = {};
            currentImportId = Number(res?.meta?.import_id || 0) || null;
            showUploadDetailContent();
            const subjectName = res?.meta?.subject?.name || subject?.name || '-';
            const resolvedExamType = res?.meta?.exam_type || examType || '-';
            const resolvedAcademicYear = res?.meta?.academic_year || academicYear || '-';
            const resolvedTerm = res?.meta?.term || term || '-';
            document.getElementById('summaryInfo').textContent = `${res.meta.file_name} - ${subjectName} (${resolvedExamType}, ${resolvedTerm}, ${resolvedAcademicYear}) - Sheet ${res.meta.sheet_name} (valid: ${res.summary.total_rows || 0}, unik NIM: ${res.summary.unique_nim_rows || 0})`;
            document.getElementById('detectedInfo').textContent = `Kolom tetap digunakan untuk sheet ${res.meta.sheet_name}. Upload terbaru akan menjadi recap aktif untuk ${subjectName} (${resolvedExamType}, ${resolvedTerm}, ${resolvedAcademicYear}) pada kelas yang sama.`;
            document.getElementById('totalRows').textContent = String(res.summary.total_rows || 0);
            document.getElementById('avgNormal').textContent = formatNumber(res.summary.avg_normal);
            document.getElementById('avgRemedial').textContent = formatNumber(res.summary.avg_remedial);
            document.getElementById('avgSusulan').textContent = formatNumber(res.summary.avg_susulan);
            document.getElementById('avgFinal').textContent = formatNumber(res.summary.avg_final);
            document.getElementById('matchedStudents').textContent = String(res.summary.matched_students || 0);
            document.getElementById('unmatchedStudents').textContent = String(res.summary.unmatched_students || 0);
            document.getElementById('duplicateRows').textContent = String(res.summary.duplicate_nim_rows || 0);
            renderFixedColumns(res.meta.fixed_columns || {});
            renderDistribution(res.summary.class_distribution || []);
            renderPagedTable();
            refreshMasterStats();
            updateOpenDetailButtonState();
            loadStoredClassRecaps(false);
            showToast(res.message);
        })
        .catch(error => showToast(error.message, 'error'));
}
