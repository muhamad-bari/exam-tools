const pageSize = 10;
let allGradeRows = [];
let gradePage = 1;
let assignSelectedNims = new Set();
let assignFailedByNim = {};
let currentImportId = null;
let selectedSubject = null;
let availableSubjects = [];
let availableSubjectsById = new Map();
let availableSubjectsByName = new Map();
let selectedExamType = null;
let selectedAcademicYear = null;
let selectedTerm = null;
let masterAcademicPeriods = [];
let storedFilters = {
    examType: null,
    academicYear: null,
    term: null,
};

function syncSelectedSubjectFromDom() {
    const searchInput = document.getElementById('subjectSearchInput');
    const select = document.getElementById('subjectSelect');
    if (!select) {
        selectedSubject = null;
        return selectedSubject;
    }

    if (searchInput) {
        const normalizedName = String(searchInput.value || '').trim().toLowerCase();
        if (!normalizedName) {
            select.value = '';
            selectedSubject = null;
            return selectedSubject;
        }

        const subjectFromName = availableSubjectsByName.get(normalizedName) || null;
        if (!subjectFromName) {
            select.value = '';
            selectedSubject = null;
            return selectedSubject;
        }

        select.value = String(subjectFromName.id);
        selectedSubject = {
            id: Number(subjectFromName.id),
            name: subjectFromName.name,
        };
        return selectedSubject;
    }

    const id = parseInt(select.value || '0', 10);
    if (!id) {
        selectedSubject = null;
        return selectedSubject;
    }

    selectedSubject = {
        id,
        name: select.options[select.selectedIndex]?.textContent || '',
    };

    return selectedSubject;
}

function setAvailableSubjects(subjects) {
    availableSubjects = Array.isArray(subjects) ? subjects.slice() : [];
    availableSubjectsById = new Map();
    availableSubjectsByName = new Map();

    availableSubjects.forEach((subject) => {
        const id = Number(subject?.id || 0);
        const name = String(subject?.name || '').trim();
        if (!id || !name) {
            return;
        }

        const normalized = {
            id,
            name,
        };
        availableSubjectsById.set(String(id), normalized);
        availableSubjectsByName.set(name.toLowerCase(), normalized);
    });
}

function syncSelectedExamTypeFromDom() {
    const select = document.getElementById('examTypeSelect');
    if (!select) {
        selectedExamType = null;
        return selectedExamType;
    }

    const value = String(select.value || '').trim().toUpperCase();
    selectedExamType = value || null;
    return selectedExamType;
}

function normalizeAcademicYearValue(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})\s*[\/\-]\s*(\d{4})$/);
    if (!match) {
        return null;
    }

    const startYear = Number(match[1]);
    const endYear = Number(match[2]);
    if (endYear !== startYear + 1) {
        return null;
    }

    return `${startYear}/${endYear}`;
}

function normalizeAcademicPeriodRows(items) {
    const normalized = [];
    const seen = new Set();

    (items || []).forEach((item) => {
        const academicYear = normalizeAcademicYearValue(item?.academic_year || '');
        const term = String(item?.term || '').trim().toUpperCase();
        if (!academicYear || !term) {
            return;
        }

        const key = `${academicYear}::${term}`;
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        normalized.push({
            id: item?.id || null,
            academic_year: academicYear,
            term,
        });
    });

    normalized.sort((a, b) => {
        if (a.academic_year === b.academic_year) {
            const order = { GANJIL: 1, GENAP: 2 };
            return (order[a.term] || 99) - (order[b.term] || 99);
        }
        return b.academic_year.localeCompare(a.academic_year);
    });

    return normalized;
}

function getTermsForAcademicYear(academicYear) {
    const year = normalizeAcademicYearValue(academicYear);
    if (!year) {
        return [];
    }

    return masterAcademicPeriods
        .filter((item) => item.academic_year === year)
        .map((item) => item.term);
}

function renderUploadAcademicYearOptions(preferredValue = '') {
    const select = document.getElementById('academicYearInput');
    if (!select) {
        return;
    }

    const uniqueYears = Array.from(new Set(masterAcademicPeriods.map((item) => item.academic_year)));
    const preservedValue = normalizeAcademicYearValue(preferredValue || select.value || selectedAcademicYear || '');
    select.innerHTML = '<option value="">-- Pilih tahun ajaran --</option>';

    uniqueYears.forEach((value) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        select.appendChild(option);
    });

    if (preservedValue && uniqueYears.includes(preservedValue)) {
        select.value = preservedValue;
    }
}

function renderUploadTermOptions(academicYear, preferredValue = '') {
    const select = document.getElementById('termSelect');
    if (!select) {
        return;
    }

    const terms = getTermsForAcademicYear(academicYear);
    const preservedValue = String(preferredValue || select.value || selectedTerm || '').trim().toUpperCase();
    select.innerHTML = '<option value="">-- Pilih periode --</option>';

    terms.forEach((value) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value.charAt(0) + value.slice(1).toLowerCase();
        select.appendChild(option);
    });

    if (preservedValue && terms.includes(preservedValue)) {
        select.value = preservedValue;
    }
}

function setMasterAcademicPeriods(items) {
    masterAcademicPeriods = normalizeAcademicPeriodRows(items);
    renderUploadAcademicYearOptions();
    renderUploadTermOptions(document.getElementById('academicYearInput')?.value || '');
    syncSelectedAcademicYearFromDom();
    syncSelectedTermFromDom();
    updateUploadButtonState();
}

function syncSelectedAcademicYearFromDom() {
    const select = document.getElementById('academicYearInput');
    if (!select) {
        selectedAcademicYear = null;
        return selectedAcademicYear;
    }

    const normalized = normalizeAcademicYearValue(select.value);
    if (normalized !== selectedAcademicYear) {
        renderUploadTermOptions(normalized, '');
    }
    selectedAcademicYear = normalized;
    return selectedAcademicYear;
}

function syncSelectedTermFromDom() {
    const select = document.getElementById('termSelect');
    if (!select) {
        selectedTerm = null;
        return selectedTerm;
    }

    const allowedTerms = getTermsForAcademicYear(selectedAcademicYear);
    const value = String(select.value || '').trim().toUpperCase();
    selectedTerm = value && allowedTerms.includes(value) ? value : null;
    if (selectedTerm === null && value) {
        select.value = '';
    }
    return selectedTerm;
}

function syncStoredFiltersFromDom() {
    const examTypeSelect = document.getElementById('storedExamTypeFilter');
    const academicYearSelect = document.getElementById('storedAcademicYearFilter');
    const termSelect = document.getElementById('storedTermFilter');

    storedFilters = {
        examType: examTypeSelect ? (String(examTypeSelect.value || '').trim().toUpperCase() || null) : null,
        academicYear: academicYearSelect ? (normalizeAcademicYearValue(academicYearSelect.value) || null) : null,
        term: termSelect ? (String(termSelect.value || '').trim().toUpperCase() || null) : null,
    };

    return storedFilters;
}

function buildStoredFilterParams() {
    syncStoredFiltersFromDom();
    const params = new URLSearchParams();
    if (storedFilters.examType) {
        params.set('exam_type', storedFilters.examType);
    }
    if (storedFilters.academicYear) {
        params.set('academic_year', storedFilters.academicYear);
    }
    if (storedFilters.term) {
        params.set('term', storedFilters.term);
    }
    return params;
}

function setSelectOptions(selectId, values, defaultLabel) {
    const select = document.getElementById(selectId);
    if (!select) {
        return;
    }

    const previousValue = select.value;
    const uniqueValues = Array.from(new Set((values || []).filter(Boolean)));
    select.innerHTML = `<option value="">${defaultLabel}</option>`;
    uniqueValues.forEach((value) => {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(value);
        select.appendChild(option);
    });

    if (previousValue && uniqueValues.includes(previousValue)) {
        select.value = previousValue;
    }
}

function applyStoredFilterMeta(meta = {}) {
    const options = meta.filter_options || {};
    setSelectOptions('storedExamTypeFilter', options.exam_types || ['UTS', 'UAS'], 'Semua Jenis Ujian');
    setSelectOptions('storedAcademicYearFilter', options.academic_years || [], 'Semua Tahun Ajaran');
    setSelectOptions('storedTermFilter', options.terms || ['GANJIL', 'GENAP'], 'Semua Periode');

    const activeFilters = meta.active_filters || {};
    if (document.activeElement?.id !== 'storedExamTypeFilter') {
        document.getElementById('storedExamTypeFilter').value = activeFilters.exam_type || '';
    }
    if (document.activeElement?.id !== 'storedAcademicYearFilter') {
        document.getElementById('storedAcademicYearFilter').value = activeFilters.academic_year || '';
    }
    if (document.activeElement?.id !== 'storedTermFilter') {
        document.getElementById('storedTermFilter').value = activeFilters.term || '';
    }

    syncStoredFiltersFromDom();
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

function formatNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(2).replace(/\.00$/, '') : '-';
}

function updateUploadButtonState() {
    syncSelectedSubjectFromDom();
    syncSelectedExamTypeFromDom();
    syncSelectedAcademicYearFromDom();
    syncSelectedTermFromDom();
    const btn = document.getElementById('uploadBtn');
    btn.disabled = !selectedSubject || !selectedExamType || !selectedAcademicYear || !selectedTerm;
}

function getUnmatchedRows() {
    return allGradeRows.filter(item => !item.matched_master);
}

function updateAssignButtonState() {
    const button = document.getElementById('assignClassBtn');
    const unmatchedCount = getUnmatchedRows().length;
    button.disabled = unmatchedCount <= 0;
    button.innerHTML = `<i class="fa-solid fa-user-plus"></i> Masukkan ke Kelas (${unmatchedCount})`;
}

function refreshMasterStats() {
    const matched = allGradeRows.filter(item => !!item.matched_master).length;
    const unmatched = Math.max(0, allGradeRows.length - matched);
    document.getElementById('matchedStudents').textContent = String(matched);
    document.getElementById('unmatchedStudents').textContent = String(unmatched);
    updateAssignButtonState();
}

function buildDistributionFromRows() {
    if (!allGradeRows.length) {
        return [];
    }

    const map = new Map();
    allGradeRows.forEach(item => {
        const name = (item.kelas || item.master_class || 'Tanpa kelas').trim() || 'Tanpa kelas';
        map.set(name, (map.get(name) || 0) + 1);
    });

    return Array.from(map.entries())
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
}

function showDefaultContent() {
    document.getElementById('defaultContent').classList.add('active');
    document.getElementById('uploadDetailContent').classList.remove('active');
    updateOpenDetailButtonState();
}

function showUploadDetailContent() {
    document.getElementById('defaultContent').classList.remove('active');
    document.getElementById('uploadDetailContent').classList.add('active');
}

function updateOpenDetailButtonState() {
    const btn = document.getElementById('openDetailBtn');
    btn.style.display = allGradeRows.length > 0 ? 'inline-flex' : 'none';
}
