(function () {
    window.generatePDF = function () {
        const hasClass = !!document.getElementById('classSelect').value;
        const csvInput = document.getElementById('student_csv');
        const studentSearchInput = document.getElementById('studentSearchInput');
        const selectedStudentId = document.getElementById('selectedStudentId').value;
        if (!hasClass && (!csvInput.files || csvInput.files.length === 0)) {
            showToast('Pilih kelas master atau upload CSV mahasiswa terlebih dahulu!', 'error');
            return;
        }

        if (hasClass && studentSearchInput && studentSearchInput.value.trim() && !selectedStudentId) {
            showToast('Pilih mahasiswa dari dropdown hasil pencarian atau kosongkan pencarian untuk generate seluruh kelas.', 'error');
            studentSearchInput.focus();
            return;
        }

        const formData = new FormData(document.getElementById('scheduleForm'));
        formData.set('selected_student_id', selectedStudentId || '');
        const btn = document.getElementById('generatePdfBtn');
        const btnText = document.getElementById('btnText');
        btn.disabled = true;
        const originalText = btnText.innerHTML;
        btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        fetch('index.php?api=generate_pdf', { method: 'POST', body: formData })
            .then(async (response) => {
                const contentType = response.headers.get('content-type') || '';
                const payload = contentType.includes('application/json') ? await response.json() : null;
                if (!response.ok || !payload || !payload.success) {
                    throw new Error(payload && payload.message ? payload.message : 'Gagal membuat PDF');
                }
                return payload;
            })
            .then((data) => {
                const binaryString = atob(data.pdf_data);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                const blob = new Blob([bytes], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                showToast(data.message);
            })
            .catch((error) => showToast(error.message, 'error'))
            .finally(() => {
                btn.disabled = false;
                btnText.innerHTML = originalText;
            });
    };

    setupDragAndDrop('csvDropZone', 'student_csv', handleCsvSelect);
    setupDragAndDrop('logoDropZone', 'logo', handleLogoSelect);
    addRow();
    updateStudentSourceBadge();
    updatePreview();
    fetchSessions();
    loadMasterData(true);
})();
