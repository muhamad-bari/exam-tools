(function () {
    const STUDENTS_PER_PAGE = 10;

    const state = {
        data: { by_class: [], scopes: [] },
        meta: {},
        currentItem: null,
        itemMap: new Map(),
        classPages: {},
        collapsedClasses: {},
        expandedStudents: {},
        loading: false,
        saving: false,
    };

    const els = {};

    document.addEventListener('DOMContentLoaded', () => {
        bindElements();
        bindEvents();
        loadOverview();
    });

    function bindElements() {
        els.examTypeFilter = document.getElementById('examTypeFilter');
        els.academicYearFilter = document.getElementById('academicYearFilter');
        els.termFilter = document.getElementById('termFilter');
        els.classFilter = document.getElementById('classFilter');
        els.studentFilter = document.getElementById('studentFilter');
        els.refreshBtn = document.getElementById('refreshBtn');
        els.resetBtn = document.getElementById('resetBtn');
        els.followUpContent = document.getElementById('followUpContent');
        els.activeFilterSummary = document.getElementById('activeFilterSummary');
        els.studentNameSearch = document.getElementById('studentNameSearch');
        els.overviewMessage = document.getElementById('overviewMessage');
        els.totalItemsStat = document.getElementById('totalItemsStat');
        els.totalClassesStat = document.getElementById('totalClassesStat');
        els.totalStudentsStat = document.getElementById('totalStudentsStat');
        els.remedialStat = document.getElementById('remedialStat');
        els.susulanStat = document.getElementById('susulanStat');
        els.scopeStat = document.getElementById('scopeStat');
        els.modal = document.getElementById('followUpModal');
        els.modalTitle = document.getElementById('modalTitle');
        els.modalSubtitle = document.getElementById('modalSubtitle');
        els.modalContext = document.getElementById('modalContext');
        els.followUpForm = document.getElementById('followUpForm');
        els.statusInput = document.getElementById('statusInput');
        els.dateInput = document.getElementById('dateInput');
        els.scoreInput = document.getElementById('scoreInput');
        els.typeInput = document.getElementById('typeInput');
        els.notesInput = document.getElementById('notesInput');
        els.saveStatusBtn = document.getElementById('saveStatusBtn');
        els.closeModalBtn = document.getElementById('closeModalBtn');
        els.cancelModalBtn = document.getElementById('cancelModalBtn');
    }

    function bindEvents() {
        [els.examTypeFilter, els.academicYearFilter, els.termFilter].forEach((element) => {
            element.addEventListener('change', () => loadOverview());
        });

        els.classFilter.addEventListener('change', () => {
            syncStudentOptions();
            loadOverview();
        });

        els.studentFilter.addEventListener('change', () => loadOverview());
        els.refreshBtn.addEventListener('click', () => loadOverview(true));
        els.resetBtn.addEventListener('click', resetFilters);
        els.studentNameSearch.addEventListener('input', () => {
            renderSummary();
            renderGroups();
        });

        els.followUpContent.addEventListener('click', (event) => {
            const editButton = event.target.closest('[data-action="edit-item"]');
            if (editButton) {
                const key = editButton.getAttribute('data-item-key') || '';
                openModal(key);
                return;
            }

            const toggleButton = event.target.closest('[data-action="toggle-student"]');
            if (toggleButton) {
                const studentKey = toggleButton.getAttribute('data-student-key') || '';
                toggleStudent(studentKey);
                return;
            }

            const pageButton = event.target.closest('[data-action="change-class-page"]');
            if (pageButton) {
                const classKey = pageButton.getAttribute('data-class-key') || '';
                const direction = Number(pageButton.getAttribute('data-direction') || 0);
                changeClassPage(classKey, direction);
                return;
            }

            const classToggleButton = event.target.closest('[data-action="toggle-class"]');
            if (classToggleButton) {
                const classKey = classToggleButton.getAttribute('data-class-key') || '';
                toggleClassCollapse(classKey);
                return;
            }

            const exportRemedialButton = event.target.closest('[data-action="export-class-remedial"]');
            if (exportRemedialButton) {
                const classId = exportRemedialButton.getAttribute('data-class-id') || '';
                exportClassRemedial(classId);
                return;
            }

            const exportSusulanButton = event.target.closest('[data-action="export-class-susulan"]');
            if (exportSusulanButton) {
                const classId = exportSusulanButton.getAttribute('data-class-id') || '';
                exportClassSusulan(classId);
            }
        });

        els.closeModalBtn.addEventListener('click', closeModal);
        els.cancelModalBtn.addEventListener('click', closeModal);
        els.modal.addEventListener('click', (event) => {
            if (event.target === els.modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && els.modal.classList.contains('open')) {
                closeModal();
            }
        });

        els.followUpForm.addEventListener('submit', submitStatusUpdate);
    }

    function getFilters() {
        return {
            exam_type: els.examTypeFilter.value.trim(),
            academic_year: els.academicYearFilter.value.trim(),
            term: els.termFilter.value.trim(),
            class_id: els.classFilter.value.trim(),
            student_id: els.studentFilter.value.trim(),
        };
    }

    function resetFilters() {
        els.examTypeFilter.value = '';
        els.academicYearFilter.value = '';
        els.termFilter.value = '';
        els.classFilter.value = '';
        els.studentFilter.value = '';
        els.studentNameSearch.value = '';
        syncStudentOptions();
        loadOverview();
    }

    function loadOverview(showToastOnSuccess = false) {
        if (state.loading) {
            return;
        }

        state.loading = true;
        setLoadingState(true);

        fetch(AppUtils.apiUrl('follow_up', 'list_overview', getFilters()), {
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload.success) {
                    throw new Error(payload.message || 'Gagal memuat data tindak lanjut');
                }

                state.data = payload.data || { by_class: [], scopes: [] };
                state.meta = payload.meta || {};
                applyFilterOptions();
                normalizeViewState();
                renderStats();
                renderSummary();
                renderGroups();

                if (showToastOnSuccess) {
                    showToast(payload.message || 'Data tindak lanjut berhasil diperbarui');
                }
            })
            .catch((error) => {
                els.overviewMessage.textContent = 'Terjadi kendala saat memuat data tindak lanjut.';
                els.followUpContent.innerHTML = buildEmptyState('circle-exclamation', error.message || 'Data gagal dimuat.');
                showToast(error.message || 'Data tindak lanjut gagal dimuat', 'error');
            })
            .finally(() => {
                state.loading = false;
                setLoadingState(false);
            });
    }

    function applyFilterOptions() {
        const filterOptions = state.meta.filter_options || {};
        setSelectOptions(els.examTypeFilter, filterOptions.exam_types || [], 'Semua jenis ujian');
        setSelectOptions(els.academicYearFilter, filterOptions.academic_years || [], 'Semua tahun ajaran');
        setSelectOptions(els.termFilter, filterOptions.terms || [], 'Semua semester');

        const classOptions = (state.data.by_class || []).map((row) => ({
            value: String(row.class?.id || ''),
            label: row.class?.name || 'Tanpa kelas',
        }));
        setSelectOptions(els.classFilter, classOptions, 'Semua kelas');
        syncStudentOptions();
    }

    function syncStudentOptions() {
        const selectedClassId = els.classFilter.value;
        const classes = state.data.by_class || [];
        const students = [];

        classes.forEach((classRow) => {
            const classId = String(classRow.class?.id || '');
            if (selectedClassId && classId !== selectedClassId) {
                return;
            }

            (classRow.students || []).forEach((studentRow) => {
                students.push({
                    value: String(studentRow.student?.id || ''),
                    label: `${studentRow.student?.name || '-'}${studentRow.student?.nim ? ` (${studentRow.student.nim})` : ''}`,
                });
            });
        });

        students.sort((left, right) => left.label.localeCompare(right.label, 'id'));
        setSelectOptions(els.studentFilter, students, 'Semua mahasiswa');
    }

    function setSelectOptions(select, options, placeholder) {
        const previousValue = select.value;
        const normalized = [];
        const seen = new Set();

        (options || []).forEach((option) => {
            const item = typeof option === 'object'
                ? { value: String(option.value ?? ''), label: String(option.label ?? option.value ?? '') }
                : { value: String(option ?? ''), label: String(option ?? '') };

            if (!item.value || seen.has(item.value)) {
                return;
            }

            seen.add(item.value);
            normalized.push(item);
        });

        select.innerHTML = [`<option value="">${escapeHtml(placeholder)}</option>`]
            .concat(normalized.map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`))
            .join('');

        if (previousValue && normalized.some((item) => item.value === previousValue)) {
            select.value = previousValue;
        }
    }

    function renderStats() {
        const itemCounts = state.meta.item_counts || {};
        els.totalItemsStat.textContent = String(state.meta.total_items || 0);
        els.totalClassesStat.textContent = String(state.meta.total_classes || 0);
        els.totalStudentsStat.textContent = String(state.meta.total_students || 0);
        els.remedialStat.textContent = String(itemCounts.remedial || 0);
        els.susulanStat.textContent = String(itemCounts.susulan || 0);
        els.scopeStat.textContent = String(state.meta.total_scopes || 0);
    }

    function renderSummary() {
        const filters = getFilters();
        const studentSearch = getStudentSearchKeyword();
        const selectedStudentLabel = els.studentFilter.selectedOptions[0]?.textContent || '';
        const selectedClassLabel = els.classFilter.selectedOptions[0]?.textContent || '';
        const summaryItems = [];

        if (filters.exam_type) {
            summaryItems.push(`<span class="pill"><i class="fa-solid fa-file-signature"></i> ${escapeHtml(filters.exam_type)}</span>`);
        }
        if (filters.academic_year) {
            summaryItems.push(`<span class="pill"><i class="fa-solid fa-calendar"></i> ${escapeHtml(filters.academic_year)}</span>`);
        }
        if (filters.term) {
            summaryItems.push(`<span class="pill"><i class="fa-solid fa-calendar-week"></i> ${escapeHtml(filters.term)}</span>`);
        }
        if (filters.class_id) {
            summaryItems.push(`<span class="pill"><i class="fa-solid fa-users-line"></i> ${escapeHtml(selectedClassLabel)}</span>`);
        }
        if (filters.student_id) {
            summaryItems.push(`<span class="pill"><i class="fa-solid fa-user"></i> ${escapeHtml(selectedStudentLabel)}</span>`);
        }
        if (studentSearch) {
            summaryItems.push(`<span class="pill"><i class="fa-solid fa-magnifying-glass"></i> Nama: ${escapeHtml(els.studentNameSearch.value.trim())}</span>`);
        }

        els.activeFilterSummary.innerHTML = summaryItems.length ? summaryItems.join('') : '<span class="pill"><i class="fa-solid fa-filter"></i> Tanpa filter khusus</span>';
        els.overviewMessage.textContent = `Menampilkan ${state.meta.total_items || 0} item follow-up dari ${state.meta.total_classes || 0} kelas dan ${state.meta.total_students || 0} mahasiswa, dengan maksimal ${STUDENTS_PER_PAGE} mahasiswa per halaman kelas.`;
    }

    function renderGroups() {
        const classes = state.data.by_class || [];
        const studentSearch = getStudentSearchKeyword();
        const filteredClasses = classes
            .map((classRow) => {
                const students = classRow.students || [];
                return {
                    ...classRow,
                    students: filterStudentsBySearch(students, studentSearch),
                };
            })
            .filter((classRow) => (classRow.students || []).length > 0);

        state.itemMap = new Map();

        if (!classes.length) {
            els.followUpContent.innerHTML = buildEmptyState('folder-open', 'Tidak ada item remedial/SP atau susulan untuk filter yang dipilih.');
            return;
        }

        if (!filteredClasses.length) {
            els.followUpContent.innerHTML = buildEmptyState('magnifying-glass', `Tidak ada mahasiswa yang cocok dengan pencarian nama "${els.studentNameSearch.value.trim()}".`);
            return;
        }

        els.followUpContent.innerHTML = filteredClasses.map((classRow) => renderClassCard(classRow)).join('');
    }

    function renderClassCard(classRow) {
        const classInfo = classRow.class || {};
        const students = classRow.students || [];
        const classKey = buildClassKey(classInfo);
        const totals = computeClassDisplayTotals(classRow, students);
        const pagination = paginateStudents(students, classKey);
        const isCollapsed = Boolean(state.collapsedClasses[classKey]);
        const classContentId = `class-content-${buildDomId(classKey)}`;

        return `
            <section class="class-card" data-class-key="${escapeHtml(classKey)}">
                <div class="class-header">
                    <div class="class-header-main stack" style="gap:8px;">
                        <h3>${escapeHtml(classInfo.name || 'Tanpa kelas')}</h3>
                        <div class="class-meta">
                            <span class="pill"><i class="fa-solid fa-user-group"></i> ${escapeHtml(totals.students || 0)} mahasiswa</span>
                            <span class="pill"><i class="fa-solid fa-list-check"></i> ${escapeHtml(totals.items || 0)} item</span>
                            <span class="pill"><i class="fa-solid fa-notes-medical"></i> ${escapeHtml(totals.remedial || 0)} remedial/SP</span>
                            <span class="pill"><i class="fa-solid fa-clock-rotate-left"></i> ${escapeHtml(totals.susulan || 0)} susulan</span>
                        </div>
                    </div>
                    <div class="class-header-actions">
                        <button
                            type="button"
                            class="class-collapse-btn"
                            data-action="toggle-class"
                            data-class-key="${escapeHtml(classKey)}"
                            aria-expanded="${isCollapsed ? 'false' : 'true'}"
                            aria-controls="${escapeHtml(classContentId)}"
                        >
                            <i class="fa-solid fa-chevron-${isCollapsed ? 'down' : 'up'}"></i>
                            ${isCollapsed ? 'Tampilkan Mahasiswa' : 'Sembunyikan Mahasiswa'}
                        </button>
                        <button type="button" class="btn gray class-export-btn" data-action="export-class-remedial" data-class-id="${escapeHtml(classInfo.id || '')}">
                            <i class="fa-solid fa-file-export"></i> Export XLSX Remedial
                        </button>
                        <button type="button" class="btn gray class-export-btn" data-action="export-class-susulan" data-class-id="${escapeHtml(classInfo.id || '')}">
                            <i class="fa-solid fa-file-export"></i> Export XLSX Susulan
                        </button>
                    </div>
                </div>
                <div class="class-content" id="${escapeHtml(classContentId)}" ${isCollapsed ? 'hidden' : ''}>
                    <div class="student-list">
                        ${pagination.students.map((studentRow) => renderStudentCard(classInfo, studentRow)).join('')}
                    </div>
                    <div class="table-pagination">
                        <span class="table-pagination-info">Mahasiswa ${escapeHtml(pagination.start)}-${escapeHtml(pagination.end)} dari ${escapeHtml(pagination.total)}</span>
                        <div class="table-pagination-actions">
                            <button type="button" class="pager-btn" data-action="change-class-page" data-class-key="${escapeHtml(classKey)}" data-direction="-1" ${pagination.page <= 1 ? 'disabled' : ''}>Prev</button>
                            <span class="table-pagination-info">Halaman ${escapeHtml(pagination.page)} / ${escapeHtml(pagination.totalPages)}</span>
                            <button type="button" class="pager-btn" data-action="change-class-page" data-class-key="${escapeHtml(classKey)}" data-direction="1" ${pagination.page >= pagination.totalPages ? 'disabled' : ''}>Next</button>
                        </div>
                    </div>
                </div>
            </section>
        `;
    }

    function renderStudentCard(classInfo, studentRow) {
        const student = studentRow.student || {};
        const totals = studentRow.totals || {};
        const items = studentRow.items || [];
        const studentKey = buildStudentKey(classInfo, student);
        const detailId = `student-detail-${buildDomId(studentKey)}`;
        const isExpanded = Boolean(state.expandedStudents[studentKey]);

        return `
            <article class="student-card ${isExpanded ? 'expanded' : ''}">
                <div class="student-summary">
                    <button
                        type="button"
                        class="student-toggle"
                        data-action="toggle-student"
                        data-student-key="${escapeHtml(studentKey)}"
                        aria-expanded="${isExpanded ? 'true' : 'false'}"
                        aria-controls="${escapeHtml(detailId)}"
                    >
                        <span class="student-toggle-indicator"><i class="fa-solid fa-chevron-${isExpanded ? 'up' : 'down'}"></i></span>
                        <span class="student-title">
                            <span class="student-name-row">
                                <strong>${escapeHtml(student.name || 'Mahasiswa')}</strong>
                                <span class="muted">${escapeHtml(student.nim || '-')}</span>
                            </span>
                            <span class="muted">Klik untuk ${isExpanded ? 'menyembunyikan' : 'melihat'} item tindak lanjut mahasiswa ini.</span>
                        </span>
                    </button>
                    <div class="student-meta">
                        <span class="pill"><i class="fa-solid fa-list"></i> ${escapeHtml(totals.items || 0)} item</span>
                        <span class="pill"><i class="fa-solid fa-notes-medical"></i> ${escapeHtml(totals.remedial || 0)} remedial/SP</span>
                        <span class="pill"><i class="fa-solid fa-clock-rotate-left"></i> ${escapeHtml(totals.susulan || 0)} susulan</span>
                    </div>
                </div>
                <div class="student-detail" id="${escapeHtml(detailId)}" ${isExpanded ? '' : 'hidden'}>
                    <div class="muted">${escapeHtml(classInfo.name || 'Tanpa kelas')} &middot; ${escapeHtml(items.length || 0)} item tindak lanjut</div>
                    <div class="item-list">
                        ${items.map((item) => renderItemCard(item)).join('')}
                    </div>
                </div>
            </article>
        `;
    }

    function renderItemCard(item) {
        const itemKey = buildItemKey(item);
        const followUpTypeLabel = item.follow_up_type === 'susulan' ? 'Susulan' : 'Remedial/SP';
        const statusValue = String(item.status?.status || 'pending');
        state.itemMap.set(itemKey, item);

        return `
            <div class="item-card">
                <div class="item-top">
                    <div class="item-title">
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <span class="type-badge ${escapeHtml(item.follow_up_type || '')}">${escapeHtml(followUpTypeLabel)}</span>
                            ${renderStatusBadge(statusValue)}
                        </div>
                        <h3>${escapeHtml(item.subject_name || '-')}</h3>
                        <div class="item-meta">
                            <span class="pill"><i class="fa-solid fa-file-signature"></i> ${escapeHtml(item.exam_type || '-')}</span>
                            <span class="pill"><i class="fa-solid fa-calendar"></i> ${escapeHtml(item.academic_year || '-')}</span>
                            <span class="pill"><i class="fa-solid fa-calendar-week"></i> ${escapeHtml(item.term || '-')}</span>
                            <span class="pill"><i class="fa-solid fa-circle-info"></i> ${escapeHtml(item.reason_label || '-')}</span>
                        </div>
                    </div>
                </div>

                <div class="item-status-grid">
                    <div class="status-box"><span>Status saat ini</span><strong>${escapeHtml(toDisplayStatus(statusValue))}</strong></div>
                    <div class="status-box"><span>Tanggal</span><strong>${escapeHtml(formatDate(item.status?.follow_up_date))}</strong></div>
                    <div class="status-box"><span>Nilai</span><strong>${escapeHtml(formatNumber(item.status?.follow_up_score))}</strong></div>
                    <div class="status-box"><span>Diperbarui</span><strong>${escapeHtml(formatDateTime(item.status?.updated_at))}</strong></div>
                </div>

                <div class="notes-block">
                    <div class="muted" style="margin-bottom:4px; font-weight:700;">Catatan</div>
                    <div>${escapeHtml(item.status?.notes || 'Belum ada catatan tindak lanjut.')}</div>
                </div>

                <div class="item-actions">
                    <button type="button" class="btn primary" data-action="edit-item" data-item-key="${escapeHtml(itemKey)}">
                        <i class="fa-solid fa-pen-to-square"></i> Update Status
                    </button>
                </div>
            </div>
        `;
    }

    function openModal(itemKey) {
        const item = state.itemMap.get(itemKey);
        if (!item) {
            showToast('Data item tidak ditemukan. Silakan muat ulang halaman.', 'error');
            return;
        }

        state.currentItem = item;
        const typeLabel = item.follow_up_type === 'susulan' ? 'Susulan' : 'Remedial/SP';

        els.modalTitle.textContent = `Update ${typeLabel}`;
        els.modalSubtitle.textContent = `${item.student_name || '-'} • ${item.subject_name || '-'} • ${item.exam_type || '-'}`;
        els.modalContext.innerHTML = [
            `<div><strong>Mahasiswa:</strong> ${escapeHtml(item.student_name || '-')} (${escapeHtml(item.nim || '-')})</div>`,
            `<div><strong>Kelas:</strong> ${escapeHtml(item.class_name || '-')}</div>`,
            `<div><strong>Mata kuliah:</strong> ${escapeHtml(item.subject_name || '-')}</div>`,
            `<div><strong>Scope:</strong> ${escapeHtml(item.exam_type || '-')} • ${escapeHtml(item.academic_year || '-')} • ${escapeHtml(item.term || '-')}</div>`,
        ].join('');

        els.statusInput.value = item.status?.status || 'pending';
        els.dateInput.value = item.status?.follow_up_date || '';
        els.scoreInput.value = item.status?.follow_up_score ?? '';
        els.typeInput.value = typeLabel;
        els.notesInput.value = item.status?.notes || '';
        els.modal.classList.add('open');
        els.modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        state.currentItem = null;
        els.followUpForm.reset();
        els.modal.classList.remove('open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    function submitStatusUpdate(event) {
        event.preventDefault();
        if (!state.currentItem || state.saving) {
            return;
        }

        const item = state.currentItem;
        const payload = {
            student_id: item.student_id,
            subject_id: item.subject_id,
            exam_type: item.exam_type,
            academic_year: item.academic_year,
            term: item.term,
            follow_up_type: item.follow_up_type,
            class_id: item.class_id,
            class_name_snapshot: item.class_name,
            source_import_id: item.source_import_id,
            status: els.statusInput.value.trim(),
            follow_up_date: els.dateInput.value.trim(),
            follow_up_score: els.scoreInput.value.trim(),
            notes: els.notesInput.value.trim(),
        };

        state.saving = true;
        els.saveStatusBtn.disabled = true;

        fetch(AppUtils.apiUrl('follow_up', 'save_status'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then((response) => response.json())
            .then((result) => {
                if (!result.success) {
                    throw new Error(result.message || 'Status tindak lanjut gagal disimpan');
                }

                showToast(result.message || 'Status tindak lanjut berhasil disimpan');
                closeModal();
                loadOverview();
            })
            .catch((error) => {
                showToast(error.message || 'Status tindak lanjut gagal disimpan', 'error');
            })
            .finally(() => {
                state.saving = false;
                els.saveStatusBtn.disabled = false;
            });
    }

    function setLoadingState(isLoading) {
        els.refreshBtn.disabled = isLoading;
        els.resetBtn.disabled = isLoading;
        if (isLoading) {
            els.followUpContent.innerHTML = buildEmptyState('spinner fa-spin', 'Sedang memuat data tindak lanjut...');
        }
    }

    function buildItemKey(item) {
        return [
            item.class_id || '',
            item.student_id || '',
            item.subject_id || '',
            item.exam_type || '',
            item.academic_year || '',
            item.term || '',
            item.follow_up_type || '',
            item.source_import_id || '',
        ].join('::');
    }

    function normalizeViewState() {
        const classes = state.data.by_class || [];
        const filters = getFilters();
        const nextClassPages = {};
        const nextCollapsedClasses = {};
        const nextExpandedStudents = {};

        classes.forEach((classRow) => {
            const classInfo = classRow.class || {};
            const classKey = buildClassKey(classInfo);
            const students = classRow.students || [];
            const totalPages = Math.max(1, Math.ceil(students.length / STUDENTS_PER_PAGE));
            nextClassPages[classKey] = clampPage(state.classPages[classKey] || 1, totalPages);
            if (state.collapsedClasses[classKey]) {
                nextCollapsedClasses[classKey] = true;
            }

            students.forEach((studentRow) => {
                const student = studentRow.student || {};
                const studentKey = buildStudentKey(classInfo, student);
                if (state.expandedStudents[studentKey]) {
                    nextExpandedStudents[studentKey] = true;
                }
                if (filters.student_id && String(student.id || '') === filters.student_id) {
                    nextExpandedStudents[studentKey] = true;
                }
            });
        });

        state.classPages = nextClassPages;
        state.collapsedClasses = nextCollapsedClasses;
        state.expandedStudents = nextExpandedStudents;
    }

    function toggleClassCollapse(classKey) {
        if (!classKey) {
            return;
        }

        if (state.collapsedClasses[classKey]) {
            delete state.collapsedClasses[classKey];
        } else {
            state.collapsedClasses[classKey] = true;
        }

        renderGroups();
    }

    function toggleStudent(studentKey) {
        if (!studentKey) {
            return;
        }

        if (state.expandedStudents[studentKey]) {
            delete state.expandedStudents[studentKey];
        } else {
            state.expandedStudents[studentKey] = true;
        }

        renderGroups();
    }

    function changeClassPage(classKey, direction) {
        if (!classKey || !direction) {
            return;
        }

        const classRow = (state.data.by_class || []).find((row) => buildClassKey(row.class || {}) === classKey);
        if (!classRow) {
            return;
        }

        const studentSearch = getStudentSearchKeyword();
        const filteredStudents = filterStudentsBySearch(classRow.students || [], studentSearch);
        const totalPages = Math.max(1, Math.ceil(filteredStudents.length / STUDENTS_PER_PAGE));
        const nextPage = clampPage((state.classPages[classKey] || 1) + direction, totalPages);

        if (nextPage === state.classPages[classKey]) {
            return;
        }

        state.classPages[classKey] = nextPage;
        renderGroups();
    }

    function exportClassRemedial(classId) {
        if (!classId) {
            showToast('Kelas tidak valid untuk export remedial.', 'error');
            return;
        }

        window.location.assign(buildClassRemedialExportUrl(classId));
    }

    function exportClassSusulan(classId) {
        if (!classId) {
            showToast('Kelas tidak valid untuk export susulan.', 'error');
            return;
        }

        window.location.assign(buildClassSusulanExportUrl(classId));
    }

    function buildClassRemedialExportUrl(classId) {
        const filters = getFilters();
        return AppUtils.apiUrl('follow_up', 'export_remedial_class_xlsx', {
            class_id: classId,
            exam_type: filters.exam_type,
            academic_year: filters.academic_year,
            term: filters.term,
        });
    }

    function buildClassSusulanExportUrl(classId) {
        const filters = getFilters();
        return AppUtils.apiUrl('follow_up', 'export_xlsx_susulan', {
            class_id: classId,
            exam_type: filters.exam_type,
            academic_year: filters.academic_year,
            term: filters.term,
        });
    }

    function paginateStudents(students, classKey) {
        const total = students.length;
        const totalPages = Math.max(1, Math.ceil(total / STUDENTS_PER_PAGE));
        const page = clampPage(state.classPages[classKey] || 1, totalPages);
        const startOffset = (page - 1) * STUDENTS_PER_PAGE;
        const pagedStudents = students.slice(startOffset, startOffset + STUDENTS_PER_PAGE);

        state.classPages[classKey] = page;

        return {
            students: pagedStudents,
            page,
            totalPages,
            start: total ? startOffset + 1 : 0,
            end: total ? Math.min(startOffset + STUDENTS_PER_PAGE, total) : 0,
            total,
        };
    }

    function clampPage(page, totalPages) {
        return Math.min(Math.max(Number(page) || 1, 1), Math.max(totalPages, 1));
    }

    function getStudentSearchKeyword() {
        return normalizeSearchText(els.studentNameSearch?.value || '');
    }

    function normalizeSearchText(value) {
        return String(value || '').trim().toLocaleLowerCase('id-ID');
    }

    function filterStudentsBySearch(students, studentSearch) {
        if (!studentSearch) {
            return students;
        }

        return (students || []).filter((studentRow) => normalizeSearchText(studentRow.student?.name || '').includes(studentSearch));
    }

    function computeClassDisplayTotals(classRow, students) {
        const fallbackTotals = classRow.totals || {};
        const summary = {
            students: students.length,
            items: 0,
            remedial: 0,
            susulan: 0,
        };

        students.forEach((studentRow) => {
            const items = studentRow.items || [];
            summary.items += items.length;
            items.forEach((item) => {
                if (item.follow_up_type === 'susulan') {
                    summary.susulan += 1;
                } else {
                    summary.remedial += 1;
                }
            });
        });

        return {
            students: summary.students,
            items: summary.items,
            remedial: summary.remedial,
            susulan: summary.susulan,
            ...fallbackTotals,
            ...(students.length ? summary : {}),
        };
    }

    function buildClassKey(classInfo) {
        return [classInfo.id || '', classInfo.name || ''].join('::');
    }

    function buildStudentKey(classInfo, student) {
        return [classInfo.id || '', student.id || '', student.nim || ''].join('::');
    }

    function buildDomId(value) {
        return String(value || 'row').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'row';
    }

    function renderStatusBadge(status) {
        const normalized = normalizeStatusClass(status);
        return `<span class="status-badge ${normalized}">${escapeHtml(toDisplayStatus(status))}</span>`;
    }

    function normalizeStatusClass(status) {
        const safe = String(status || 'pending').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return `status-${safe || 'pending'}`;
    }

    function toDisplayStatus(status) {
        const safe = String(status || 'pending').trim();
        return safe ? safe.charAt(0).toUpperCase() + safe.slice(1) : 'Pending';
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        const number = Number(value);
        return Number.isFinite(number) ? number.toFixed(2).replace(/\.00$/, '') : '-';
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(date);
    }

    function formatDateTime(value) {
        if (!value) {
            return '-';
        }

        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function buildEmptyState(iconClass, message) {
        return `
            <div class="empty-state">
                <i class="fa-solid fa-${iconClass}"></i>
                <div>${escapeHtml(message)}</div>
            </div>
        `;
    }

    function escapeHtml(value) {
        const safe = value === null || value === undefined ? '' : String(value);
        return safe.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'circle-exclamation'}"></i> <span>${escapeHtml(message)}</span>`;
        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 250);
            }, 2800);
        });
    }
})();
