function initGradeRecapPage() {
    document.getElementById('gradeFile').addEventListener('change', updateSelectedFileInfo);

    const subjectSearchInput = document.getElementById('subjectSearchInput');
    const subjectSuggestions = document.getElementById('subjectSearchSuggestions');
    let activeSubjectSuggestionIndex = -1;
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
    const closeSubjectSuggestions = () => {
        if (!subjectSuggestions || !subjectSearchInput) {
            return;
        }

        subjectSuggestions.classList.remove('open');
        subjectSuggestions.innerHTML = '';
        subjectSearchInput.setAttribute('aria-expanded', 'false');
        activeSubjectSuggestionIndex = -1;
    };
    const getSubjectSuggestionMatches = () => {
        const query = String(subjectSearchInput?.value || '').trim().toLowerCase();
        const source = Array.isArray(availableSubjects) ? availableSubjects : [];
        if (!query) {
            return source.slice(0, 10);
        }

        return source
            .filter((subject) => String(subject?.name || '').toLowerCase().includes(query))
            .slice(0, 10);
    };
    const setActiveSubjectSuggestion = (index) => {
        if (!subjectSuggestions) {
            return;
        }

        const items = Array.from(subjectSuggestions.querySelectorAll('.subject-suggestion'));
        if (!items.length) {
            activeSubjectSuggestionIndex = -1;
            return;
        }

        activeSubjectSuggestionIndex = Math.min(Math.max(index, 0), items.length - 1);
        items.forEach((item, itemIndex) => {
            item.classList.toggle('active', itemIndex === activeSubjectSuggestionIndex);
        });
        items[activeSubjectSuggestionIndex].scrollIntoView({ block: 'nearest' });
    };
    const chooseSubjectSuggestion = (subjectId) => {
        const subject = availableSubjectsById.get(String(subjectId)) || null;
        if (!subject || !subjectSearchInput) {
            return;
        }

        const previousSubjectId = getConfirmedSubjectId();
        subjectSearchInput.value = subject.name;
        syncSelectedSubjectFromDom();
        updateUploadButtonState();

        const nextSubjectId = selectedSubject ? Number(selectedSubject.id) : null;
        if (previousSubjectId && previousSubjectId !== nextSubjectId) {
            clearSelectedGradeFiles(true, 'File Excel dibersihkan karena mata kuliah berubah. Pilih file yang sesuai mata kuliah baru.');
        }

        setConfirmedSubjectId(nextSubjectId);
        closeSubjectSuggestions();
    };
    const renderSubjectSuggestions = () => {
        if (!subjectSuggestions || !subjectSearchInput) {
            return;
        }

        const matches = getSubjectSuggestionMatches();
        if (!matches.length) {
            closeSubjectSuggestions();
            return;
        }

        subjectSuggestions.innerHTML = matches.map((subject) => `
            <button type="button" class="subject-suggestion" role="option" data-subject-id="${Number(subject.id)}">${escapeHtml(subject.name)}</button>
        `).join('');
        subjectSuggestions.classList.add('open');
        subjectSearchInput.setAttribute('aria-expanded', 'true');
        activeSubjectSuggestionIndex = -1;
    };

    subjectSearchInput.addEventListener('input', () => {
        syncSelectedSubjectFromDom();
        updateUploadButtonState();
        renderSubjectSuggestions();
    });

    subjectSearchInput.addEventListener('focus', renderSubjectSuggestions);
    subjectSearchInput.addEventListener('click', renderSubjectSuggestions);

    subjectSearchInput.addEventListener('keydown', (event) => {
        if (!subjectSuggestions || !subjectSuggestions.classList.contains('open')) {
            return;
        }

        const items = Array.from(subjectSuggestions.querySelectorAll('.subject-suggestion'));
        if (!items.length) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveSubjectSuggestion(activeSubjectSuggestionIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveSubjectSuggestion(activeSubjectSuggestionIndex <= 0 ? items.length - 1 : activeSubjectSuggestionIndex - 1);
        } else if (event.key === 'Enter' && activeSubjectSuggestionIndex >= 0) {
            event.preventDefault();
            chooseSubjectSuggestion(items[activeSubjectSuggestionIndex].dataset.subjectId);
        } else if (event.key === 'Escape') {
            closeSubjectSuggestions();
        }
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

    if (subjectSuggestions) {
        subjectSuggestions.addEventListener('mousedown', (event) => {
            event.preventDefault();
            const option = event.target.closest('.subject-suggestion');
            if (option) {
                chooseSubjectSuggestion(option.dataset.subjectId);
            }
        });
    }

    document.addEventListener('pointerdown', (event) => {
        const wrap = document.getElementById('subjectSearchWrap');
        if (wrap && !wrap.contains(event.target)) {
            closeSubjectSuggestions();
        }
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

    setupClassSubjectModalInteractions();

    setupDragAndDrop();
    resetGradeRecap();
    setConfirmedSubjectId(null);

    Promise.all([loadSubjectOptions(), loadAcademicPeriodOptions()])
        .then(() => {
            syncSelectedSubjectFromDom();
            updateUploadButtonState();
            setConfirmedSubjectId(selectedSubject ? Number(selectedSubject.id) : null);
            if (document.activeElement === subjectSearchInput) {
                renderSubjectSuggestions();
            }
        })
        .catch((error) => showToast(error.message, 'error'));

    loadStoredClassRecaps(false);
}

initGradeRecapPage();
