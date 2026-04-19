function loadSubjectOptions() {
    return fetch('index.php?api=master_data&action=list_subjects')
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                throw new Error(res.message || 'Gagal memuat mata kuliah');
            }

            const select = document.getElementById('subjectSelect');
            const searchInput = document.getElementById('subjectSearchInput');
            const searchList = document.getElementById('subjectSearchList');
            select.innerHTML = '<option value="">-- Pilih mata kuliah --</option>';
            if (searchList) {
                searchList.innerHTML = '';
            }

            const subjects = res.data || [];
            setAvailableSubjects(subjects);

            subjects.forEach(subject => {
                const option = document.createElement('option');
                option.value = String(subject.id);
                option.textContent = subject.name;
                select.appendChild(option);

                if (searchList) {
                    const datalistOption = document.createElement('option');
                    datalistOption.value = subject.name;
                    searchList.appendChild(datalistOption);
                }
            });

            if (searchInput) {
                const selectedId = String(select.value || '');
                const selectedOption = Array.from(select.options).find((option) => String(option.value) === selectedId);
                searchInput.value = selectedOption ? selectedOption.textContent : '';
            }
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
            Array.from(files).forEach((file) => {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;
        } catch (error) {
        }
        updateSelectedFileInfo();
    }, false);
}

function getSelectedGradeFiles() {
    const input = document.getElementById('gradeFile');
    if (!input || !input.files) {
        return [];
    }
    return Array.from(input.files);
}

function getDefaultGradeFileInfoText() {
    return 'Format tetap: `Nama(B)`, `NIM(D)`, `B/S(G)`, `Nilai(J)`, `Kategori(K)`. Bulk upload akan memproses semua file untuk kombinasi mata kuliah + jenis ujian + tahun ajaran + periode yang dipilih.';
}

function updateClearGradeFileButtonState() {
    const clearBtn = document.getElementById('clearGradeFileBtn');
    if (!clearBtn) {
        return;
    }

    clearBtn.style.display = getSelectedGradeFiles().length ? 'inline-flex' : 'none';
}

function clearSelectedGradeFiles(showToastMessage = false, toastMessage = 'File Excel terpilih sudah dibersihkan.') {
    const input = document.getElementById('gradeFile');
    if (!input) {
        return false;
    }

    const hadFiles = getSelectedGradeFiles().length > 0;
    if (!hadFiles) {
        updateClearGradeFileButtonState();
        return false;
    }

    input.value = '';
    updateSelectedFileInfo();
    updateClearGradeFileButtonState();

    if (showToastMessage) {
        showToast(toastMessage);
    }

    return true;
}

function removeSelectedGradeFiles() {
    clearSelectedGradeFiles(true, 'File Excel terpilih berhasil dihapus.');
}

function updateSelectedFileInfo() {
    const input = document.getElementById('gradeFile');
    const info = document.getElementById('gradeFileInfo');
    const files = getSelectedGradeFiles();

    if (!files.length) {
        info.textContent = getDefaultGradeFileInfoText();
        updateClearGradeFileButtonState();
        return;
    }

    const invalidFile = files.find((file) => !String(file.name || '').toLowerCase().endsWith('.xlsx'));
    if (invalidFile) {
        showToast('File harus berformat .xlsx', 'error');
        input.value = '';
        info.textContent = getDefaultGradeFileInfoText();
        updateClearGradeFileButtonState();
        return;
    }

    if (files.length === 1) {
        info.textContent = files[0].name;
        updateClearGradeFileButtonState();
        return;
    }

    const previewNames = files.slice(0, 3).map((file) => file.name);
    const remaining = files.length - previewNames.length;
    info.textContent = `${files.length} file dipilih: ${previewNames.join(', ')}${remaining > 0 ? `, +${remaining} file lain` : ''}`;
    updateClearGradeFileButtonState();
}

function uploadGradeRecap() {
    const files = getSelectedGradeFiles();
    if (!files.length) {
        showToast('Pilih file Excel nilai terlebih dahulu', 'error');
        return;
    }

    const subject = syncSelectedSubjectFromDom();
    const examType = syncSelectedExamTypeFromDom();
    const academicYear = syncSelectedAcademicYearFromDom();
    const term = syncSelectedTermFromDom();

    const formData = new FormData();
    files.forEach((file) => {
        formData.append('grades_file[]', file);
    });
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
            const uploadedCount = Number(res?.meta?.uploaded_file_count || files.length || 1);
            const primaryFileName = res?.meta?.file_name || (files[0]?.name || 'nilai.xlsx');
            const summaryFileText = uploadedCount > 1 ? `${uploadedCount} file (${primaryFileName} + lainnya)` : primaryFileName;
            document.getElementById('summaryInfo').textContent = `${summaryFileText} - ${subjectName} (${resolvedExamType}, ${resolvedTerm}, ${resolvedAcademicYear}) - Sheet ${res.meta.sheet_name} (valid: ${res.summary.total_rows || 0}, unik NIM: ${res.summary.unique_nim_rows || 0})`;
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
