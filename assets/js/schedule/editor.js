(function () {
    const state = window.scheduleState;

    state.studentSearchQuery = state.studentSearchQuery || '';
    state.studentSearchResults = state.studentSearchResults || [];
    state.selectedPreviewStudentId = state.selectedPreviewStudentId || '';
    state.currentPreviewClass = state.currentPreviewClass || null;
    state.pendingSelectedPreviewStudentId = state.pendingSelectedPreviewStudentId || '';
    state.pendingSelectedPreviewStudentLabel = state.pendingSelectedPreviewStudentLabel || '';
    state.classRequestToken = state.classRequestToken || 0;

    function getStudentSearchInput() {
        return document.getElementById('studentSearchInput');
    }

    function getSelectedStudentField() {
        return document.getElementById('selectedStudentId');
    }

    function getStudentSearchDropdown() {
        return document.getElementById('studentSearchDropdown');
    }

    function getStudentSearchStatus() {
        return document.getElementById('studentSearchStatus');
    }

    function getSelectedPreviewStudent() {
        const hiddenField = getSelectedStudentField();
        const studentId = String(state.selectedPreviewStudentId || (hiddenField ? hiddenField.value : '') || '');
        if (!studentId) {
            return null;
        }
        return state.previewStudents.find((student) => String(student.id) === studentId) || null;
    }

    function getStudentLabel(student) {
        return `${student.nama || 'Mahasiswa'} (${student.nim || '-'})`;
    }

    function getStudentClassLabel(student) {
        return student.tingkat || (state.currentPreviewClass && state.currentPreviewClass.name) || '-';
    }

    function hideStudentSearchDropdown() {
        const dropdown = getStudentSearchDropdown();
        if (!dropdown) {
            return;
        }
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
    }

    function setStudentSearchStatus(student = null) {
        const status = getStudentSearchStatus();
        if (!status) {
            return;
        }

        if (student) {
            status.className = 'student-search-selected is-active';
            status.innerHTML = `<i class="fa-solid fa-circle-check"></i><span>${escapeHtml(student.nama || 'Mahasiswa')} • ${escapeHtml(student.nim || '-')} • ${escapeHtml(getStudentClassLabel(student))}</span>`;
            return;
        }

        status.className = 'student-search-selected';
        status.innerHTML = 'Kosongkan pencarian untuk tetap generate semua mahasiswa di kelas aktif.';
    }

    function updateStudentSearchAvailability() {
        const input = getStudentSearchInput();
        if (!input) {
            return;
        }

        const hasClass = !!document.getElementById('classSelect').value;
        input.disabled = !hasClass;
        input.placeholder = hasClass
            ? 'Ketik minimal 3 huruf nama atau 3 digit NIM...'
            : 'Pilih kelas master terlebih dahulu';
    }

    function getStudentSearchThresholdState(rawValue) {
        const query = String(rawValue || '').trim();
        const digitCount = query.replace(/\D/g, '').length;
        const letterCount = query.replace(/[^a-zA-Z]/g, '').length;

        return {
            query,
            meetsThreshold: digitCount >= 3 || letterCount >= 3
        };
    }

    function filterPreviewStudents(query) {
        const normalizedQuery = String(query || '').trim().toLowerCase();
        const digitQuery = normalizedQuery.replace(/\D/g, '');

        return state.previewStudents.filter((student) => {
            const studentName = String(student.nama || '').toLowerCase();
            const studentNim = String(student.nim || '');
            return studentName.includes(normalizedQuery) || (!!digitQuery && studentNim.includes(digitQuery));
        });
    }

    function clearSelectedPreviewStudent(keepInputValue = false) {
        const input = getStudentSearchInput();
        const hidden = getSelectedStudentField();

        state.selectedPreviewStudentId = '';
        if (hidden) {
            hidden.value = '';
        }
        if (input) {
            input.classList.remove('is-selected');
            if (!keepInputValue) {
                input.value = '';
            }
        }
        setStudentSearchStatus(null);
    }

    function setStudentPreviewPageForStudent(studentId) {
        const selectedIndex = state.previewStudents.findIndex((student) => String(student.id) === String(studentId));
        if (selectedIndex < 0) {
            return;
        }

        state.studentPreviewPage = Math.floor(selectedIndex / state.studentPreviewPageSize) + 1;
    }

    function applyPendingSelectedPreviewStudent() {
        const pendingStudentId = String(state.pendingSelectedPreviewStudentId || '');
        const input = getStudentSearchInput();
        const hidden = getSelectedStudentField();
        if (!pendingStudentId || !input || !hidden) {
            return;
        }

        const student = state.previewStudents.find((item) => String(item.id) === pendingStudentId);
        state.pendingSelectedPreviewStudentId = '';
        if (!student) {
            state.pendingSelectedPreviewStudentLabel = '';
            return;
        }

        state.selectedPreviewStudentId = String(student.id);
        hidden.value = student.id;
        input.value = state.pendingSelectedPreviewStudentLabel || getStudentLabel(student);
        input.classList.add('is-selected');
        setStudentSearchStatus(student);
        setStudentPreviewPageForStudent(student.id);
        state.pendingSelectedPreviewStudentLabel = '';
    }

    function syncSelectedPreviewStudent() {
        const selectedStudent = getSelectedPreviewStudent();
        if (selectedStudent) {
            state.selectedPreviewStudentId = String(selectedStudent.id);
            if (getSelectedStudentField()) {
                getSelectedStudentField().value = selectedStudent.id;
            }
            if (getStudentSearchInput()) {
                getStudentSearchInput().classList.add('is-selected');
            }
            setStudentSearchStatus(selectedStudent);
            return selectedStudent;
        }

        clearSelectedPreviewStudent(true);
        return null;
    }

    function renderStudentSearchDropdown() {
        const input = getStudentSearchInput();
        const dropdown = getStudentSearchDropdown();
        if (!input || !dropdown) {
            return;
        }

        const thresholdState = getStudentSearchThresholdState(input.value);
        state.studentSearchQuery = thresholdState.query;

        if (!document.getElementById('classSelect').value || !thresholdState.meetsThreshold) {
            state.studentSearchResults = [];
            hideStudentSearchDropdown();
            return;
        }

        const matches = filterPreviewStudents(thresholdState.query);
        state.studentSearchResults = matches;
        dropdown.innerHTML = matches.length
            ? matches.map((student) => {
                const isSelected = String(student.id) === String(state.selectedPreviewStudentId || '');
                return `<button type="button" class="student-search-item${isSelected ? ' is-active' : ''}" onmousedown="selectPreviewStudentById('${escapeHtml(String(student.id))}'); return false;">
                    <span class="student-search-name">${escapeHtml(student.nama || 'Mahasiswa')}</span>
                    <span class="student-search-meta">NIM: ${escapeHtml(student.nim || '-')} • ${escapeHtml(getStudentClassLabel(student))}</span>
                </button>`;
            }).join('')
            : '<div class="student-search-item empty">Tidak ada mahasiswa yang cocok. Pilih dari dropdown atau kosongkan pencarian untuk generate semua mahasiswa.</div>';
        dropdown.style.display = 'block';
    }

    window.renderStudentSearchDropdown = renderStudentSearchDropdown;

    window.selectPreviewStudentById = function (studentId) {
        const student = state.previewStudents.find((item) => String(item.id) === String(studentId));
        const input = getStudentSearchInput();
        const hidden = getSelectedStudentField();
        if (!student || !input || !hidden) {
            return;
        }

        state.selectedPreviewStudentId = String(student.id);
        hidden.value = student.id;
        input.value = getStudentLabel(student);
        input.classList.add('is-selected');
        setStudentPreviewPageForStudent(student.id);
        setStudentSearchStatus(student);
        hideStudentSearchDropdown();
        renderStudentPreview(state.currentPreviewClass, state.previewStudents);
        updatePreview();
    };

    window.handleStudentSearchInput = function () {
        const input = getStudentSearchInput();
        if (!input) {
            return;
        }

        const selectedStudent = getSelectedPreviewStudent();
        const currentValue = input.value.trim();
        if (selectedStudent && currentValue !== getStudentLabel(selectedStudent)) {
            clearSelectedPreviewStudent(true);
        }

        if (!currentValue) {
            hideStudentSearchDropdown();
            renderStudentPreview(state.currentPreviewClass, state.previewStudents);
            updatePreview();
            return;
        }

        window.renderStudentSearchDropdown();
        renderStudentPreview(state.currentPreviewClass, state.previewStudents);
        updatePreview();
    };

    window.handleStudentSearchFocus = function () {
        const input = getStudentSearchInput();
        const hiddenField = getSelectedStudentField();
        if (!input || !input.value.trim() || (hiddenField && hiddenField.value)) {
            return;
        }

        const thresholdState = getStudentSearchThresholdState(input.value);
        if (thresholdState.meetsThreshold) {
            window.renderStudentSearchDropdown();
        }
    };

    window.handleStudentSearchBlur = function () {
        window.setTimeout(() => {
            hideStudentSearchDropdown();
        }, 120);
    };

    window.createScheduleRow = function (item = {}) {
        const row = document.createElement('tr');
        row.className = 'schedule-row';
        row.innerHTML = `
            <td>
                <div class="schedule-cell-card"><input type="text" name="hari[]" class="form-control" placeholder="Senin, 10 Mar" value="${escapeHtml(item.hari || '')}" oninput="updatePreview()"></div>
            </td>
            <td>
                <div class="schedule-cell-card">
                    <div class="subject-select-row">
                        <select class="subject-select form-control" onchange="handleSubjectSelect(this)"></select>
                        <button type="button" class="btn-inline" style="background:var(--accent); white-space:nowrap;" onclick="quickAddSubject(this)"><i class="fa-solid fa-plus"></i></button>
                    </div>
                    <input type="hidden" name="subject_id[]" value="${escapeHtml(item.subject_id || '')}">
                    <input type="hidden" name="matkul[]" value="${escapeHtml(item.matkul || '')}">
                </div>
            </td>
            <td>
                <div class="schedule-cell-card"><input type="text" name="jam[]" class="form-control" placeholder="09.00-10.00" value="${escapeHtml(item.jam || '')}" oninput="updatePreview()"></div>
            </td>
            <td>
                <div class="schedule-cell-card"><input type="text" name="ruang[]" class="form-control" placeholder="R.206" value="${escapeHtml(item.ruang || '')}" oninput="updatePreview()"></div>
            </td>
            <td style="width:58px;">
                <button type="button" class="btn-action" style="background:var(--danger); height:100%;" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
            </td>`;
        hydrateSubjectSelect(row, item.subject_id || '', item.matkul || '');
        return row;
    };

    window.hydrateSubjectSelect = function (row, subjectId = '', matkul = '') {
        const select = row.querySelector('.subject-select');
        const hidden = row.querySelector('input[name="subject_id[]"]');
        const textInput = row.querySelector('input[name="matkul[]"]');
        const currentText = matkul || textInput.value || '';
        const currentId = String(subjectId || hidden.value || '');
        let html = '<option value="">-- Pilih mata kuliah master --</option>';
        state.masterSubjects.forEach((subject) => {
            html += `<option value="${subject.id}">${escapeHtml(subject.name)}</option>`;
        });
        select.innerHTML = html;

        if (currentId && state.masterSubjects.some((subject) => String(subject.id) === currentId)) {
            select.value = currentId;
            hidden.value = currentId;
            textInput.value = select.options[select.selectedIndex].text;
        } else if (currentText) {
            const match = state.masterSubjects.find((subject) => subject.name.toLowerCase() === currentText.toLowerCase());
            if (match) {
                select.value = String(match.id);
                hidden.value = match.id;
                textInput.value = match.name;
            } else {
                const customValue = `custom:${currentText}`;
                const customLabel = `${escapeHtml(currentText)} (Custom)`;
                select.innerHTML += `<option value="${escapeHtml(customValue)}">${customLabel}</option>`;
                select.value = customValue;
                hidden.value = '';
                textInput.value = currentText;
            }
        }

        if (select.value && !textInput.value) {
            textInput.value = select.options[select.selectedIndex].text.replace(/\s*\(Custom\)$/, '');
        }
    };

    window.addRow = function (item = {}) {
        const tbody = document.getElementById('scheduleBody');
        tbody.appendChild(createScheduleRow(item));
        updatePreview();
    };

    window.removeRow = function (button) {
        const tbody = document.getElementById('scheduleBody');
        if (tbody.children.length <= 1) {
            return;
        }
        button.closest('tr').remove();
        updatePreview();
    };

    window.handleSubjectSelect = function (select) {
        const row = select.closest('tr');
        const hidden = row.querySelector('input[name="subject_id[]"]');
        const textInput = row.querySelector('input[name="matkul[]"]');
        hidden.value = select.value || '';
        if (select.value) {
            textInput.value = select.options[select.selectedIndex].text.replace(/\s*\(Custom\)$/, '');
            if (String(select.value).startsWith('custom:')) {
                hidden.value = '';
            }
        } else {
            textInput.value = '';
        }
        updatePreview();
    };

    window.quickAddSubject = function (button) {
        const name = prompt('Nama mata kuliah baru:');
        if (!name || !name.trim()) {
            return;
        }
        fetch('index.php?api=master_data&action=create_subject', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name.trim() })
        })
            .then((r) => r.json())
            .then((res) => {
                if (!res.success) {
                    throw new Error(res.message || 'Gagal menyimpan mata kuliah');
                }
                return loadMasterData(true).then(() => {
                    const row = button.closest('tr');
                    row.querySelector('input[name="subject_id[]"]').value = res.data.id;
                    row.querySelector('input[name="matkul[]"]').value = res.data.name;
                    hydrateSubjectSelect(row, res.data.id, res.data.name);
                    updatePreview();
                    showToast('Mata kuliah master diperbarui');
                });
            })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.loadMasterData = function (silent = false) {
        return fetch('index.php?api=master_data&action=list_all')
            .then((r) => r.json())
            .then((res) => {
                if (!res.success) {
                    throw new Error(res.message || 'Gagal memuat master data');
                }
                state.masterClasses = res.classes || [];
                state.masterSubjects = res.subjects || [];
                populateClassSelect();
                document.querySelectorAll('#scheduleBody tr').forEach((row) => hydrateSubjectSelect(row));
                if (!silent) {
                    showToast('Master data berhasil dimuat');
                }
            })
            .catch((error) => {
                showToast(error.message, 'error');
            });
    };

    window.populateClassSelect = function (selectedValue = null) {
        const select = document.getElementById('classSelect');
        const current = selectedValue !== null ? String(selectedValue || '') : String(select.value || '');
        select.innerHTML = '<option value="">-- Pilih kelas dari master data --</option>';
        state.masterClasses.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.name} (${item.student_count} mahasiswa)`;
            select.appendChild(option);
        });
        select.value = current;
    };

    window.handleClassChange = function (preservePendingSelection = false) {
        const classId = document.getElementById('classSelect').value;
        const requestToken = ++state.classRequestToken;
        updateStudentSourceBadge();
        state.studentPreviewPage = 1;
        state.currentPreviewClass = null;
        if (!preservePendingSelection) {
            state.pendingSelectedPreviewStudentId = '';
            state.pendingSelectedPreviewStudentLabel = '';
        }
        clearSelectedPreviewStudent();
        hideStudentSearchDropdown();
        updateStudentSearchAvailability();
        if (!classId) {
            state.pendingSelectedPreviewStudentId = '';
            state.pendingSelectedPreviewStudentLabel = '';
            state.previewStudents = [];
            renderStudentPreview(null, []);
            updatePreview();
            return;
        }
        fetch('index.php?api=master_data&action=list_students_by_class&class_id=' + encodeURIComponent(classId))
            .then((r) => r.json())
            .then((res) => {
                if (requestToken !== state.classRequestToken || String(document.getElementById('classSelect').value) !== String(classId)) {
                    return;
                }
                if (!res.success) {
                    throw new Error(res.message || 'Gagal memuat mahasiswa kelas');
                }
                state.previewStudents = res.data || [];
                state.currentPreviewClass = res.class || null;
                applyPendingSelectedPreviewStudent();
                renderStudentPreview(state.currentPreviewClass, state.previewStudents);
                updatePreview();
            })
            .catch((error) => {
                if (requestToken !== state.classRequestToken || String(document.getElementById('classSelect').value) !== String(classId)) {
                    return;
                }
                showToast(error.message, 'error');
            });
    };

    window.renderStudentPreview = function (classInfo, students) {
        const body = document.getElementById('studentPreviewBody');
        const prevButton = document.getElementById('studentPreviewPrev');
        const nextButton = document.getElementById('studentPreviewNext');
        const pageInfo = document.getElementById('studentPreviewPageInfo');
        state.currentPreviewClass = classInfo || null;
        updateStudentSearchAvailability();
        document.getElementById('selectedClassLabel').textContent = classInfo ? classInfo.name : 'Belum dipilih';
        document.getElementById('selectedClassCount').textContent = String(students.length || 0);
        if (!students.length) {
            clearSelectedPreviewStudent();
            hideStudentSearchDropdown();
            body.innerHTML = '<tr><td colspan="2" style="text-align:center; color:#88939e;">Belum ada mahasiswa untuk ditampilkan.</td></tr>';
            prevButton.disabled = true;
            nextButton.disabled = true;
            pageInfo.textContent = 'Page 1 / 1';
            return;
        }

        syncSelectedPreviewStudent();

        const totalPages = Math.max(1, Math.ceil(students.length / state.studentPreviewPageSize));
        if (state.studentPreviewPage > totalPages) {
            state.studentPreviewPage = totalPages;
        }
        if (state.studentPreviewPage < 1) {
            state.studentPreviewPage = 1;
        }

        const startIndex = (state.studentPreviewPage - 1) * state.studentPreviewPageSize;
        const visibleStudents = students.slice(startIndex, startIndex + state.studentPreviewPageSize);
        const selectedStudentId = String(state.selectedPreviewStudentId || '');

        body.innerHTML = visibleStudents.map((student) => `<tr class="${String(student.id) === selectedStudentId ? 'is-active' : ''}"><td>${escapeHtml(student.nim)}</td><td>${escapeHtml(student.nama)}</td></tr>`).join('');
        prevButton.disabled = state.studentPreviewPage <= 1;
        nextButton.disabled = state.studentPreviewPage >= totalPages;
        pageInfo.textContent = `Page ${state.studentPreviewPage} / ${totalPages}`;
    };

    window.changeStudentPreviewPage = function (direction) {
        const totalPages = Math.max(1, Math.ceil(state.previewStudents.length / state.studentPreviewPageSize));
        const nextPage = state.studentPreviewPage + direction;
        if (nextPage < 1 || nextPage > totalPages) {
            return;
        }

        state.studentPreviewPage = nextPage;
        const selected = state.masterClasses.find((item) => String(item.id) === String(document.getElementById('classSelect').value));
        renderStudentPreview(selected || null, state.previewStudents);
    };

    window.updateStudentSourceBadge = function () {
        const classId = document.getElementById('classSelect').value;
        const badge = document.getElementById('studentSourceBadge');
        if (classId) {
            const selected = state.masterClasses.find((item) => String(item.id) === String(classId));
            badge.className = 'badge';
            badge.innerHTML = `<i class="fa-solid fa-database"></i> Master class aktif: ${escapeHtml(selected ? selected.name : classId)}`;
        } else {
            badge.className = 'badge warn';
            badge.innerHTML = '<i class="fa-solid fa-file-csv"></i> Menggunakan CSV fallback bila diunggah';
        }
    };

    window.updatePreview = function () {
        document.getElementById('prev_h1').innerText = document.getElementsByName('header_line1')[0].value;
        document.getElementById('prev_h2').innerText = document.getElementsByName('header_line2')[0].value;
        document.getElementById('prev_sub').innerText = document.getElementsByName('sub_title')[0].value;
        document.getElementById('prev_name').innerText = document.getElementsByName('signer_name')[0].value;
        document.getElementById('prev_inst_signer').innerText = document.getElementsByName('signer_institution')[0].value;
        document.getElementById('prev_title').innerText = document.getElementsByName('signer_title')[0].value;
        document.getElementById('prev_date').innerText = 'Bogor, ' + document.getElementsByName('signer_date')[0].value;

        const selectedClassId = document.getElementById('classSelect').value;
        const previewSource = document.getElementById('previewStudentSource');
        const previewClassName = document.getElementById('previewClassName');
        const previewStudentName = document.getElementById('previewStudentName');
        const previewStudentNim = document.getElementById('previewStudentNim');
        const sampleStudent = getSelectedPreviewStudent() || state.previewStudents[0];

        if (selectedClassId && sampleStudent) {
            previewSource.textContent = getSelectedPreviewStudent()
                ? '[Preview mahasiswa terpilih dari master data]'
                : '[Preview mahasiswa dari master data]';
            previewClassName.textContent = getStudentClassLabel(sampleStudent);
            previewStudentName.textContent = sampleStudent.nama || 'CONTOH MAHASISWA';
            previewStudentNim.textContent = sampleStudent.nim || '12345678';
        } else if (document.getElementById('student_csv').files.length > 0) {
            previewSource.textContent = '[Preview mahasiswa dari CSV fallback]';
            previewClassName.textContent = 'CSV';
            previewStudentName.textContent = 'Mahasiswa dari file unggahan';
            previewStudentNim.textContent = '...';
        } else {
            previewSource.textContent = '[Preview data mahasiswa]';
            previewClassName.textContent = '-';
            previewStudentName.textContent = 'CONTOH MAHASISWA';
            previewStudentNim.textContent = '12345678';
        }

        const tbody = document.getElementById('previewScheduleBody');
        tbody.innerHTML = '';
        const inputsHari = document.getElementsByName('hari[]');
        const inputsMatkul = document.getElementsByName('matkul[]');
        const inputsJam = document.getElementsByName('jam[]');
        const inputsRuang = document.getElementsByName('ruang[]');
        for (let i = 0; i < inputsMatkul.length; i++) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${i + 1}</td>
                <td style="border:1px solid #000; padding:4px; font-size:11px;">${escapeHtml(inputsMatkul[i].value)}</td>
                <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${escapeHtml(inputsHari[i].value)}</td>
                <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${escapeHtml(inputsJam[i].value)}</td>
                <td style="border:1px solid #000; padding:4px; text-align:center; font-size:11px;">${escapeHtml(inputsRuang[i].value)}</td>
                <td style="border:1px solid #000; padding:4px; font-size:11px;"></td>`;
            tbody.appendChild(tr);
        }
    };

    updateStudentSearchAvailability();
    setStudentSearchStatus(null);
})();
