function initGradeRecapPage() {
    document.getElementById('gradeFile').addEventListener('change', updateSelectedFileInfo);
    document.getElementById('subjectSelect').addEventListener('change', () => {
        syncSelectedSubjectFromDom();
        updateUploadButtonState();
    });
    document.getElementById('examTypeSelect').addEventListener('change', () => {
        syncSelectedExamTypeFromDom();
        updateUploadButtonState();
    });
    document.getElementById('academicYearInput').addEventListener('change', () => {
        syncSelectedAcademicYearFromDom();
        syncSelectedTermFromDom();
        updateUploadButtonState();
    });
    document.getElementById('termSelect').addEventListener('change', () => {
        syncSelectedTermFromDom();
        updateUploadButtonState();
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
    Promise.all([loadSubjectOptions(), loadAcademicPeriodOptions()]).catch((error) => showToast(error.message, 'error'));
    loadStoredClassRecaps(false);
}

initGradeRecapPage();
