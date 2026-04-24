(function () {
    window.schedulePageConfig = window.schedulePageConfig || {};
    window.scheduleState = window.scheduleState || {
        currentSessionId: null,
        allSessions: [],
        allFolders: [],
        filteredSessions: null,
        masterClasses: [],
        masterSubjects: [],
        previewStudents: [],
        studentPreviewPage: 1,
        studentPreviewPageSize: 5
    };

    window.escapeHtml = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    window.showToast = function (message, type = 'success') {
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
    };

    window.preventDefaults = function (e) {
        e.preventDefault();
        e.stopPropagation();
    };

    window.handleDrop = function (e, input, callback) {
        const files = e.dataTransfer.files;
        if (files && files.length > 0) {
            try {
                const dataTransfer = new DataTransfer();
                for (const file of files) {
                    dataTransfer.items.add(file);
                }
                input.files = dataTransfer.files;
            } catch (error) {
            }
            callback(input, files);
        }
    };

    window.setupDragAndDrop = function (dropZoneId, inputId, selectHandler) {
        const dropZone = document.getElementById(dropZoneId);
        const input = document.getElementById(inputId);
        dropZone.addEventListener('click', () => input.click());
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => dropZone.addEventListener(eventName, preventDefaults, false));
        ['dragenter', 'dragover'].forEach((eventName) => dropZone.addEventListener(eventName, () => {
            dropZone.style.borderColor = 'var(--primary)';
        }, false));
        ['dragleave', 'drop'].forEach((eventName) => dropZone.addEventListener(eventName, () => {
            dropZone.style.borderColor = '#d8e1ea';
        }, false));
        dropZone.addEventListener('drop', (e) => handleDrop(e, input, selectHandler), false);
    };

    window.toggleSessionSection = function () {
        const content = document.getElementById('sessionContent');
        const icon = document.getElementById('sessionToggleIcon');
        const isHidden = content.style.display === 'none';
        content.style.display = isHidden ? 'block' : 'none';
        icon.className = isHidden ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
    };
})();
