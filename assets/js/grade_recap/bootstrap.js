function initGradeRecapPage() {
    document.getElementById('gradeFile').addEventListener('change', updateSelectedFileInfo);

    const subjectSearchInput = document.getElementById('subjectSearchInput');
    const getConfirmedSubjectId = () => {
        const raw = String(subjectSearchInput?.dataset?.confirmedSubjectId || '');
        const parsed = parseInt(raw, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    };
    const setConfirmedSubjectId = (id) => {
        if (!subjectSearchInput) {
            return;
        }

        if (id) {
            subjectSearchInput.dataset.confirmedSubjectId = String(id);
            return;
        }

        delete subjectSearchInput.dataset.confirmedSubjectId;
    };

    subjectSearchInput.addEventListener('input', () => {
        syncSelectedSubjectFromDom();
        updateUploadButtonState();
    });

    subjectSearchInput.addEventListener('change', () => {
        const previousSubjectId = getConfirmedSubjectId();
        syncSelectedSubjectFromDom();
        updateUploadButtonState();

        const nextSubjectId = selectedSubject ? Number(selectedSubject.id) : null;
        if (previousSubjectId && previousSubjectId !== nextSubjectId) {
            clearSelectedGradeFiles(true, 'File Excel dibersihkan karena mata kuliah berubah. Pilih file yang sesuai mata kuliah baru.');
        }

        setConfirmedSubjectId(nextSubjectId);
    });

    document.getElementById('examTypeSelect').addEventListener('change', () => {
        const previousExamType = selectedExamType;
        syncSelectedExamTypeFromDom();
        updateUploadButtonState();

        if (previousExamType && previousExamType !== selectedExamType) {
            clearSelectedGradeFiles(true, 'File Excel dibersihkan karena jenis ujian berubah.');
        }
    });

    document.getElementById('academicYearInput').addEventListener('change', () => {
        const previousAcademicYear = selectedAcademicYear;
        syncSelectedAcademicYearFromDom();
        syncSelectedTermFromDom();
        updateUploadButtonState();

        if (previousAcademicYear && previousAcademicYear !== selectedAcademicYear) {
            clearSelectedGradeFiles(true, 'File Excel dibersihkan karena tahun ajaran berubah.');
        }
    });

    document.getElementById('termSelect').addEventListener('change', () => {
        const previousTerm = selectedTerm;
        syncSelectedTermFromDom();
        updateUploadButtonState();

        if (previousTerm && previousTerm !== selectedTerm) {
            clearSelectedGradeFiles(true, 'File Excel dibersihkan karena periode semester berubah.');
        }
    });

    document.getElementById('storedExamTypeFilter').addEventListener('change', () => {
        syncStoredFiltersFromDom();
        loadStoredClassRecaps(false);
    });
    document.getElementById('storedAcademicYearFilter').addEventListener('change', () => {
        syncStoredFiltersFromDom();
        loadStoredClassRecaps(false);
    });
    document.getElementById('storedTermFilter').addEventListener('change', () => {
        syncStoredFiltersFromDom();
        loadStoredClassRecaps(false);
    });

    setupDragAndDrop();
    resetGradeRecap();
    setConfirmedSubjectId(null);

    Promise.all([loadSubjectOptions(), loadAcademicPeriodOptions()])
        .then(() => {
            syncSelectedSubjectFromDom();
            updateUploadButtonState();
            setConfirmedSubjectId(selectedSubject ? Number(selectedSubject.id) : null);
        })
        .catch((error) => showToast(error.message, 'error'));

    loadStoredClassRecaps(false);
}

initGradeRecapPage();
