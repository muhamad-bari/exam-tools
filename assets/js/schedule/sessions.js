(function () {
    const state = window.scheduleState;
    const defaults = window.schedulePageConfig || {};

    window.saveSession = function () {
        const name = document.getElementById('sessionNameInput').value.trim();
        if (!name) {
            showToast('Please enter a session name!', 'error');
            return;
        }

        const data = {
            header_line1: document.getElementsByName('header_line1')[0].value,
            header_line2: document.getElementsByName('header_line2')[0].value,
            sub_title: document.getElementsByName('sub_title')[0].value,
            signer_name: document.getElementsByName('signer_name')[0].value,
            signer_institution: document.getElementsByName('signer_institution')[0].value,
            signer_title: document.getElementsByName('signer_title')[0].value,
            signer_date: document.getElementsByName('signer_date')[0].value,
            logo_data: document.getElementById('existing_logo_data').value,
            class_id: document.getElementById('classSelect').value || '',
            schedule: []
        };

        const inputsHari = document.getElementsByName('hari[]');
        const inputsMatkul = document.getElementsByName('matkul[]');
        const inputsSubjectId = document.getElementsByName('subject_id[]');
        const inputsJam = document.getElementsByName('jam[]');
        const inputsRuang = document.getElementsByName('ruang[]');
        for (let i = 0; i < inputsMatkul.length; i++) {
            data.schedule.push({
                hari: inputsHari[i].value,
                matkul: inputsMatkul[i].value,
                subject_id: inputsSubjectId[i].value,
                jam: inputsJam[i].value,
                ruang: inputsRuang[i].value
            });
        }

        fetch('index.php?api=sessions&action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                data: JSON.stringify(data),
                id: state.currentSessionId,
                folder_id: document.getElementById('saveFolderSelect').value
            })
        })
            .then((r) => r.json())
            .then((res) => {
                if (!res.success) {
                    throw new Error(res.message || 'Gagal menyimpan session');
                }
                state.currentSessionId = res.id;
                fetchSessions();
                showToast('Session saved!');
            })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.restoreForm = function (data, sessionName = '', folderId = null) {
        document.getElementById('sessionNameInput').value = sessionName || '';
        document.getElementById('saveFolderSelect').value = folderId || '';
        document.getElementsByName('header_line1')[0].value = data.header_line1 || defaults.defaultHeaderLine1 || 'AKADEMI KEBIDANAN WIJAYA HUSADA';
        document.getElementsByName('header_line2')[0].value = data.header_line2 || '';
        document.getElementsByName('sub_title')[0].value = data.sub_title || defaults.defaultSubTitle || '';
        document.getElementsByName('signer_name')[0].value = data.signer_name || defaults.defaultSignerName || '';
        document.getElementsByName('signer_institution')[0].value = data.signer_institution || defaults.defaultSignerInstitution || '';
        document.getElementsByName('signer_title')[0].value = data.signer_title || defaults.defaultSignerTitle || '';
        document.getElementsByName('signer_date')[0].value = data.signer_date || defaults.defaultSignerDate || '';

        if (data.logo_data) {
            document.getElementById('existing_logo_data').value = data.logo_data;
            document.getElementById('previewLogoImg').src = data.logo_data;
            document.getElementById('previewLogoImg').style.display = 'block';
            document.getElementById('deleteLogoBtn').style.display = 'inline-flex';
            document.getElementById('logoFileName').style.display = 'block';
            document.getElementById('logoFileName').innerHTML = '<i class="fa-solid fa-image"></i> (Restored from Session)';
        } else {
            deleteLogoFile();
        }

        populateClassSelect(data.class_id || '');
        document.getElementById('classSelect').value = data.class_id || '';
        const tbody = document.getElementById('scheduleBody');
        tbody.innerHTML = '';
        if (Array.isArray(data.schedule) && data.schedule.length) {
            data.schedule.forEach((item) => addRow(item));
        } else {
            addRow();
        }
        handleClassChange();
        updatePreview();
    };

    window.newSession = function () {
        if (!confirm('Are you sure you want to start a new session? This will reset all fields.')) {
            return;
        }

        state.currentSessionId = null;
        document.getElementById('sessionNameInput').value = '';
        document.getElementById('saveFolderSelect').value = '';
        document.getElementsByName('header_line1')[0].value = defaults.defaultHeaderLine1 || 'AKADEMI KEBIDANAN WIJAYA HUSADA';
        document.getElementsByName('header_line2')[0].value = '';
        document.getElementsByName('sub_title')[0].value = defaults.defaultSubTitle || '';
        document.getElementsByName('signer_name')[0].value = defaults.defaultSignerName || '';
        document.getElementsByName('signer_institution')[0].value = defaults.defaultSignerInstitution || '';
        document.getElementsByName('signer_title')[0].value = defaults.defaultSignerTitle || '';
        document.getElementsByName('signer_date')[0].value = defaults.defaultSignerDate || '';
        document.getElementById('classSelect').value = '';
        deleteLogoFile();
        deleteCsvFile();
        state.previewStudents = [];
        renderStudentPreview(null, []);
        document.getElementById('scheduleBody').innerHTML = '';
        addRow();
        updateStudentSourceBadge();
        updatePreview();
        fetchSessions();
        showToast('New session started');
    };

    window.fetchSessions = function () {
        fetch('index.php?api=sessions&action=list')
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load sessions');
                }
                state.allSessions = data.data || [];
                state.allFolders = data.folders || [];
                updateFolderSelect();
                renderSessionTree();
            })
            .catch(() => {
                document.getElementById('sessionList').innerHTML = '<p style="text-align:center; color:#d64545; font-size:0.85rem; margin:12px 0;">Failed to load sessions.</p>';
            });
    };

    window.updateFolderSelect = function () {
        const select = document.getElementById('saveFolderSelect');
        const current = select.value;
        select.innerHTML = '<option value="">(No Folder)</option>';
        state.allFolders.forEach((folder) => {
            const option = document.createElement('option');
            option.value = folder.id;
            option.textContent = folder.name;
            select.appendChild(option);
        });
        select.value = current;
    };

    window.createFolder = function () {
        const name = prompt('Enter new folder name:');
        if (!name || !name.trim()) return;
        fetch('index.php?api=sessions&action=create_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: name.trim() }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder created'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.createSubfolder = function (parentId) {
        const name = prompt('Enter new subfolder name:');
        if (!name || !name.trim()) return;
        fetch('index.php?api=sessions&action=create_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: name.trim(), parent_id: parentId }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Subfolder created'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.renameFolder = function (id, currentName) {
        const name = prompt('Rename folder:', currentName);
        if (name === null || !name.trim() || name === currentName) return;
        fetch('index.php?api=sessions&action=rename_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name: name.trim() }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder renamed'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.deleteFolder = function (id) {
        if (!confirm('Delete this folder? Sessions inside will be moved to No Folder.')) return;
        fetch('index.php?api=sessions&action=delete_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder deleted'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.duplicateFolder = function (id, currentName) {
        const name = prompt('Enter name for the duplicate folder:', currentName + ' (Copy)');
        if (name === null || !name.trim()) return;
        fetch('index.php?api=sessions&action=duplicate_folder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name: name.trim() }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Folder duplicated'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.renderSessionTree = function () {
        const list = document.getElementById('sessionList');
        list.innerHTML = '';
        list.ondragover = handleFolderDragOver;
        list.ondragleave = handleFolderDragLeave;
        list.ondrop = (e) => handleFolderDrop(e, null);
        const sessionsToRender = state.filteredSessions ? state.filteredSessions : state.allSessions;
        if (!sessionsToRender.length && !state.allFolders.length) {
            list.innerHTML = '<p style="text-align:center; color:#88939e; font-size:0.85rem; margin:12px 0;">No sessions found.</p>';
            return;
        }

        const foldersByParent = {};
        state.allFolders.forEach((folder) => {
            const key = folder.parent_id === null ? 'root' : String(folder.parent_id);
            if (!foldersByParent[key]) {
                foldersByParent[key] = [];
            }
            foldersByParent[key].push(folder);
        });

        const sessionsByFolder = {};
        sessionsToRender.forEach((session) => {
            const key = session.folder_id === null ? 'root' : String(session.folder_id);
            if (!sessionsByFolder[key]) {
                sessionsByFolder[key] = [];
            }
            sessionsByFolder[key].push(session);
        });

        function buildBranch(parentKey, container) {
            let hasAnyContent = false;
            (foldersByParent[parentKey] || []).forEach((folder) => {
                const folderDiv = createFolderElement(folder);
                const contentDiv = folderDiv.querySelector('.folder-content');
                const hasChildContent = buildBranch(String(folder.id), contentDiv);
                if (state.filteredSessions && !hasChildContent) {
                    return;
                }
                if (state.filteredSessions) {
                    contentDiv.style.display = 'block';
                    folderDiv.querySelector('.toggle-icon').className = 'fa-solid fa-folder-open toggle-icon';
                }
                container.appendChild(folderDiv);
                hasAnyContent = true;
            });
            (sessionsByFolder[parentKey] || []).forEach((session) => {
                container.appendChild(createSessionCard(session));
                hasAnyContent = true;
            });
            return hasAnyContent;
        }

        buildBranch('root', list);
    };

    window.createFolderElement = function (folder) {
        const div = document.createElement('div');
        const safeName = String(folder.name).replace(/'/g, "\\'");
        div.className = 'folder-item';
        div.setAttribute('draggable', 'true');
        div.setAttribute('data-id', folder.id);
        div.setAttribute('data-type', 'folder');
        div.ondragstart = handleFolderDragStart;
        div.ondragend = handleFolderDragEnd;
        div.ondragover = handleFolderDragOver;
        div.ondragleave = handleFolderDragLeave;
        div.ondrop = (e) => handleFolderDrop(e, folder.id);
        div.innerHTML = `
            <div class="folder-header" style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:8px 10px; margin-top:6px; border:1px solid transparent; border-radius:8px; background:#f8fafc;">
                <div onclick="toggleFolderContent(this)" style="display:flex; align-items:center; gap:8px; min-width:0; flex:1; cursor:pointer;">
                    <i class="fa-solid fa-folder toggle-icon" style="color:#d97706;"></i>
                    <strong style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(folder.name)}</strong>
                </div>
                <div style="display:flex; gap:4px;">
                    <button type="button" style="background:var(--success); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); createSubfolder(${folder.id})"><i class="fa-solid fa-folder-plus"></i></button>
                    <button type="button" style="background:#f39c12; color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); renameFolder(${folder.id}, '${safeName}')"><i class="fa-solid fa-pen-to-square"></i></button>
                    <button type="button" style="background:var(--primary); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); duplicateFolder(${folder.id}, '${safeName}')"><i class="fa-solid fa-copy"></i></button>
                    <button type="button" style="background:var(--danger); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="event.stopPropagation(); deleteFolder(${folder.id})"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
            <div class="folder-content" style="display:none; margin-left:14px;"></div>`;
        return div;
    };

    window.toggleFolderContent = function (trigger) {
        const folderItem = trigger.closest('.folder-item');
        const content = folderItem.querySelector('.folder-content');
        const icon = folderItem.querySelector('.toggle-icon');
        const isOpen = content.style.display === 'block';
        content.style.display = isOpen ? 'none' : 'block';
        icon.className = isOpen ? 'fa-solid fa-folder toggle-icon' : 'fa-solid fa-folder-open toggle-icon';
    };

    window.createSessionCard = function (session) {
        const div = document.createElement('div');
        const isActive = String(session.id) === String(state.currentSessionId);
        div.setAttribute('draggable', 'true');
        div.setAttribute('data-id', session.id);
        div.setAttribute('data-type', 'session');
        div.ondragstart = handleFolderDragStart;
        div.ondragend = handleFolderDragEnd;
        const safeName = String(session.name).replace(/'/g, "\\'");
        div.style.cssText = `display:flex; justify-content:space-between; align-items:center; padding:6px 4px 6px 10px; margin-left:10px; border-bottom:1px solid #edf2f6; ${isActive ? 'background:#eaf5ff; border-left:4px solid var(--primary);' : ''}`;
        div.innerHTML = `<div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%;"><i class="fa-solid fa-grip-vertical" style="color:#bcc7d2; margin-right:5px;"></i><strong>${escapeHtml(session.name)}</strong><br><span style="font-size:0.72rem; color:#8a97a5; margin-left:15px;">${new Date(session.created_at).toLocaleDateString()}</span>${isActive ? ' <span style="font-size:0.7rem; color:var(--primary); font-weight:700;">(Active)</span>' : ''}</div><div><button type="button" style="background:var(--success); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="loadSession(${session.id})"><i class="fa-solid fa-folder-open"></i></button> <button type="button" style="background:#f39c12; color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="renameSession(${session.id}, '${safeName}')"><i class="fa-solid fa-pen-to-square"></i></button> <button type="button" style="background:var(--primary); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="duplicateSession(${session.id}, '${safeName}')"><i class="fa-solid fa-copy"></i></button> <button type="button" style="background:var(--danger); color:#fff; border:none; padding:4px 7px; border-radius:4px; cursor:pointer;" onclick="deleteSession(${session.id})"><i class="fa-solid fa-trash"></i></button></div>`;
        return div;
    };

    window.handleFolderDragStart = function (e) {
        e.stopPropagation();
        const item = e.currentTarget;
        e.dataTransfer.setData('type', item.getAttribute('data-type'));
        e.dataTransfer.setData('id', item.getAttribute('data-id'));
        item.style.opacity = '0.4';
    };

    window.handleFolderDragEnd = function (e) {
        e.currentTarget.style.opacity = '1';
    };

    window.handleFolderDragOver = function (e) {
        e.preventDefault();
        e.stopPropagation();
        const target = e.currentTarget.classList.contains('folder-item') ? e.currentTarget.querySelector('.folder-header') : e.currentTarget;
        if (target) target.style.border = '2px dashed var(--primary)';
    };

    window.handleFolderDragLeave = function (e) {
        e.stopPropagation();
        const target = e.currentTarget.classList.contains('folder-item') ? e.currentTarget.querySelector('.folder-header') : e.currentTarget;
        if (target) target.style.border = '1px solid transparent';
        if (e.currentTarget.id === 'sessionList') e.currentTarget.style.border = '1px solid var(--soft-border)';
    };

    window.handleFolderDrop = function (e, targetFolderId) {
        e.preventDefault();
        e.stopPropagation();
        const target = e.currentTarget.classList.contains('folder-item') ? e.currentTarget.querySelector('.folder-header') : e.currentTarget;
        if (target) target.style.border = '1px solid transparent';
        if (e.currentTarget.id === 'sessionList') e.currentTarget.style.border = '1px solid var(--soft-border)';
        const type = e.dataTransfer.getData('type');
        const id = e.dataTransfer.getData('id');
        const draggedEl = document.querySelector(`[data-type="${type}"][data-id="${id}"]`);
        if (draggedEl) draggedEl.style.opacity = '1';
        if (!type || !id) return;
        fetch('index.php?api=sessions&action=move_item', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type, id: parseInt(id, 10), target_id: targetFolderId === null ? null : targetFolderId }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.filterSessions = function () {
        const query = document.getElementById('sessionSearchInput').value.toLowerCase();
        state.filteredSessions = query ? state.allSessions.filter((session) => session.name.toLowerCase().includes(query)) : null;
        renderSessionTree();
    };

    window.loadSession = function (id) {
        fetch('index.php?api=sessions&action=load&id=' + id)
            .then((r) => r.json())
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat session');
                state.currentSessionId = id;
                restoreForm(res.data || {}, res.name || '', res.folder_id || '');
                fetchSessions();
                showToast('Session loaded!');
            })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.renameSession = function (id, currentName) {
        const name = prompt('Rename session:', currentName);
        if (name === null || !name.trim() || name === currentName) return;
        fetch('index.php?api=sessions&action=rename', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Session renamed'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.duplicateSession = function (id, currentName) {
        const name = prompt('Enter name for the duplicate session:', currentName + ' (Copy)');
        if (name === null || !name.trim()) return;
        fetch('index.php?api=sessions&action=duplicate', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); fetchSessions(); showToast('Session duplicated'); })
            .catch((error) => showToast(error.message, 'error'));
    };

    window.deleteSession = function (id) {
        if (!confirm('Are you sure you want to delete this session?')) return;
        fetch('index.php?api=sessions&action=delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
            .then((r) => r.json()).then((res) => { if (!res.success) throw new Error(res.message); if (String(state.currentSessionId) === String(id)) state.currentSessionId = null; fetchSessions(); showToast('Session deleted'); })
            .catch((error) => showToast(error.message, 'error'));
    };
})();
