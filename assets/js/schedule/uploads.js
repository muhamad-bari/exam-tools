(function () {
    window.handleLogoSelect = function (input, overrideFiles = null) {
        const files = overrideFiles || input.files;
        if (!files || !files[0]) {
            return;
        }

        const file = files[0];
        const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!validTypes.includes(file.type)) {
            showToast('Hanya format JPG, JPEG, dan PNG yang diperbolehkan!', 'error');
            input.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showToast('Ukuran logo terlalu besar (max 5MB)', 'error');
            input.value = '';
            return;
        }

        document.getElementById('logoLoadingSpinner').style.display = 'block';
        document.getElementById('logoIcon').style.display = 'none';
        document.getElementById('logoText').style.display = 'none';

        const reader = new FileReader();
        reader.onload = function (e) {
            setTimeout(() => {
                document.getElementById('logoLoadingSpinner').style.display = 'none';
                document.getElementById('logoIcon').style.display = 'block';
                document.getElementById('logoText').style.display = 'block';
                document.getElementById('previewLogoImg').src = e.target.result;
                document.getElementById('previewLogoImg').style.display = 'block';
                document.getElementById('existing_logo_data').value = e.target.result;
                document.getElementById('logoFileName').style.display = 'block';
                document.getElementById('logoFileName').innerHTML = `<i class="fa-solid fa-image"></i> ${escapeHtml(file.name)}`;
                document.getElementById('deleteLogoBtn').style.display = 'inline-flex';
                updatePreview();
                showToast('Logo berhasil dimuat!');
            }, 250);
        };
        reader.onerror = function () {
            showToast('Gagal membaca file logo!', 'error');
        };
        reader.readAsDataURL(file);
    };

    window.deleteLogoFile = function () {
        document.getElementById('logo').value = '';
        document.getElementById('existing_logo_data').value = '';
        document.getElementById('logoFileName').style.display = 'none';
        document.getElementById('logoFileName').innerHTML = '';
        document.getElementById('deleteLogoBtn').style.display = 'none';
        document.getElementById('previewLogoImg').style.display = 'none';
        document.getElementById('previewLogoImg').src = '';
        updatePreview();
    };

    window.handleCsvSelect = function (input, overrideFiles = null) {
        const files = overrideFiles || input.files;
        if (!files || !files[0]) {
            return;
        }

        const file = files[0];
        if (!file.name.toLowerCase().endsWith('.csv')) {
            showToast('Hanya format CSV yang diperbolehkan!', 'error');
            input.value = '';
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            showToast('Ukuran file terlalu besar (max 10MB)', 'error');
            input.value = '';
            return;
        }

        document.getElementById('csvLoadingSpinner').style.display = 'block';
        document.getElementById('csvIcon').style.display = 'none';
        document.getElementById('csvText').style.display = 'none';
        setTimeout(() => {
            document.getElementById('csvLoadingSpinner').style.display = 'none';
            document.getElementById('csvIcon').style.display = 'block';
            document.getElementById('csvText').style.display = 'block';
            document.getElementById('csvFileName').style.display = 'block';
            document.getElementById('csvFileName').innerHTML = `<i class="fa-solid fa-file-csv"></i> ${escapeHtml(file.name)} (${(file.size / 1024).toFixed(2)} KB)`;
            document.getElementById('deleteCsvBtn').style.display = 'inline-flex';
            document.getElementById('fallbackCsvBadge').innerHTML = '<i class="fa-solid fa-file-csv"></i> CSV fallback siap dipakai';
            updatePreview();
        }, 250);
    };

    window.deleteCsvFile = function () {
        document.getElementById('student_csv').value = '';
        document.getElementById('csvFileName').style.display = 'none';
        document.getElementById('csvFileName').innerHTML = '';
        document.getElementById('deleteCsvBtn').style.display = 'none';
        document.getElementById('fallbackCsvBadge').innerHTML = '<i class="fa-solid fa-file-csv"></i> CSV fallback belum dipilih';
        updatePreview();
    };
})();
