function renderPagedTable() {
  const body = document.getElementById("gradeBody");
  const pageInfo = document.getElementById("gradePageInfo");
  const prevBtn = document.getElementById("gradePrevBtn");
  const nextBtn = document.getElementById("gradeNextBtn");

  if (!allGradeRows.length) {
    body.innerHTML =
      '<tr><td colspan="10" style="text-align:center; color:#64748b;">Belum ada data rekap nilai.</td></tr>';
    pageInfo.textContent = "Page 1 / 1";
    prevBtn.disabled = true;
    nextBtn.disabled = true;
    return;
  }

  const totalPages = Math.max(1, Math.ceil(allGradeRows.length / pageSize));
  gradePage = Math.min(Math.max(gradePage, 1), totalPages);
  const startIndex = (gradePage - 1) * pageSize;
  const visibleRows = allGradeRows.slice(startIndex, startIndex + pageSize);

  body.innerHTML = visibleRows
    .map((item, index) => {
      const classLabel = item.kelas
        ? escapeHtml(item.kelas)
        : `<span class="muted">${escapeHtml(item.master_class || "Tidak ada")}</span>`;
      let masterBadge = '<span class="badge warn">Belum ada</span>';
      if (String(item.master_match_type || "") === "active") {
        masterBadge = '<span class="badge success">Cocok aktif</span>';
      } else if (String(item.master_match_type || "") === "inactive") {
        masterBadge = `<span class="badge info" title="${escapeHtml(item.recap_block_reason || "")}">Nonaktif</span>`;
      }

      const normalCell = `${escapeHtml(item.normal_bs || "-")} · ${formatNumber(item.normal_score)} · ${escapeHtml(item.normal_letter || "-")}`;
      const remedialCell = `${escapeHtml(item.remedial_bs || "-")} · ${formatNumber(item.remedial_score)} · ${escapeHtml(item.remedial_letter || "-")}`;
      const susulanCell = `${escapeHtml(item.susulan_bs || "-")} · ${formatNumber(item.susulan_score)} · ${escapeHtml(item.susulan_letter || "-")}`;
      const finalCell = `${escapeHtml(item.final_bs || "-")} · ${formatNumber(item.final_score)} · ${escapeHtml(item.final_letter || "-")}`;

      return `<tr><td>${startIndex + index + 1}</td><td>${escapeHtml(item.nim)}</td><td>${escapeHtml(item.nama)}</td><td>${classLabel}</td><td>${normalCell}</td><td>${remedialCell}</td><td>${susulanCell}</td><td>${finalCell}</td><td>${escapeHtml(item.duplicate_nim_count || 0)}</td><td>${masterBadge}</td></tr>`;
    })
    .join("");

  pageInfo.textContent = `Page ${gradePage} / ${totalPages}`;
  prevBtn.disabled = gradePage <= 1;
  nextBtn.disabled = gradePage >= totalPages;
}

function changeGradePage(direction) {
  gradePage += direction;
  renderPagedTable();
}

function renderDistribution(items) {
  const container = document.getElementById("classDistribution");
  if (!items || !items.length) {
    container.innerHTML = '<span class="muted">Belum ada data.</span>';
    return;
  }

  container.innerHTML = items
    .map(
      (item) =>
        `<span class="pill"><i class="fa-solid fa-users"></i> ${escapeHtml(item.name)} <strong>${escapeHtml(item.count)}</strong></span>`,
    )
    .join("");
}

function renderFixedColumns(columns) {
  const container = document.getElementById("fixedColumns");
  if (!columns || !Object.keys(columns).length) {
    container.innerHTML =
      '<span class="muted">Belum ada konfigurasi ditampilkan.</span>';
    return;
  }

  container.innerHTML = Object.entries(columns)
    .map(
      ([key, value]) =>
        `<span class="pill"><i class="fa-solid fa-table-columns"></i> ${escapeHtml(key)}: <strong>${escapeHtml(value)}</strong></span>`,
    )
    .join("");
}

function resetGradeRecap() {
  document.getElementById("gradeFile").value = "";
  allGradeRows = [];
  gradePage = 1;
  currentImportId = null;
  currentRecapSummary = null;
  selectedSubject = null;
  selectedExamType = null;
  selectedAcademicYear = null;
  selectedTerm = null;
  document.getElementById("subjectSelect").value = "";
  const subjectSearchInput = document.getElementById("subjectSearchInput");
  if (subjectSearchInput) {
    subjectSearchInput.value = "";
    delete subjectSearchInput.dataset.confirmedSubjectId;
  }
  document.getElementById("examTypeSelect").value = "";
  renderUploadAcademicYearOptions("");
  renderUploadTermOptions("", "");
  updateSelectedFileInfo();
  document.getElementById("summaryInfo").textContent =
    "Upload file nilai untuk melihat ringkasan nilai murni, SP, dan susulan.";
  document.getElementById("detectedInfo").textContent =
    "Belum ada file diproses.";
  document.getElementById("totalRows").textContent = "0";
  document.getElementById("avgNormal").textContent = "-";
  document.getElementById("avgRemedial").textContent = "-";
  document.getElementById("avgSusulan").textContent = "-";
  document.getElementById("avgFinal").textContent = "-";
  document.getElementById("matchedStudents").textContent = "0";
  document.getElementById("unmatchedStudents").textContent = "0";
  document.getElementById("inactiveStudents").textContent = "0";
  document.getElementById("duplicateRows").textContent = "0";
  assignSelectedNims = new Set();
  assignFailedByNim = {};
  renderFixedColumns({});
  renderDistribution([]);
  renderPagedTable();
  closeInactiveStudentsModal();
  updateAssignButtonState();
  updateOpenDetailButtonState();
  updateUploadButtonState();
  showDefaultContent();
  loadStoredClassRecaps(false);
}
