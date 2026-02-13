// admin/admin.js

const tbody = document.querySelector("#scoresTbody");

// CSRF
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";

// Modal photo
const photoModal = document.querySelector("#photoModal");
const photoOverlay = document.querySelector("#photoOverlay");
const photoClose = document.querySelector("#photoClose");
const photoOk = document.querySelector("#photoOk");
const photoImg = document.querySelector("#photoImg");
const photoMeta = document.querySelector("#photoMeta");
const photoSize = document.querySelector("#photoSize");
const photoOpen = document.querySelector("#photoOpen");

// Theme
const themeToggle = document.querySelector("#adminThemeToggle");

// Filters
const onlyPendingBtn = document.querySelector("#onlyPendingBtn");
const statusSelect = document.querySelector("#statusSelect");
const filterForm = document.querySelector("#filtersForm");

// Party code
const newCodeBtn = document.querySelector("#newCodeBtn");
const codeBox = document.querySelector("#codeBox");

function openPhotoModal({ src, meta, size }) {
  photoImg.src = src;
  photoOpen.href = src;
  photoMeta.textContent = meta || "";
  if (photoSize) photoSize.textContent = size ? `Taille : ${size}` : "";

  photoModal.hidden = false;
  requestAnimationFrame(() => photoModal.classList.add("is-open"));
  document.body.style.overflow = "hidden";
}

function closePhotoModal() {
  photoModal.classList.remove("is-open");
  document.body.style.overflow = "";
  setTimeout(() => {
    photoModal.hidden = true;
    photoImg.src = "";
  }, 220);
}

photoOverlay?.addEventListener("click", closePhotoModal);
photoClose?.addEventListener("click", closePhotoModal);
photoOk?.addEventListener("click", closePhotoModal);

document.addEventListener("keydown", (e) => {
  if (photoModal && !photoModal.hidden && e.key === "Escape") closePhotoModal();
});

tbody?.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-view-photo");
  if (!btn) return;
  openPhotoModal({
    src: btn.dataset.src,
    meta: btn.dataset.meta,
    size: btn.dataset.size,
  });
});

// Dark mode
if (themeToggle) {
  const saved = localStorage.getItem("admin_theme");
  if (saved === "dark") {
    document.body.classList.add("dark");
    themeToggle.textContent = "☀️";
  } else {
    themeToggle.textContent = "🌙";
  }

  themeToggle.addEventListener("click", () => {
    document.body.classList.toggle("dark");
    const isDark = document.body.classList.contains("dark");
    localStorage.setItem("admin_theme", isDark ? "dark" : "light");
    themeToggle.textContent = isDark ? "☀️" : "🌙";
  });
}

// Filtre "À vérifier" = status=0
if (onlyPendingBtn && statusSelect && filterForm) {
  onlyPendingBtn.addEventListener("click", () => {
    statusSelect.value = "0";
    filterForm.submit();
  });
}

// Helper: mettre à jour le badge/pill selon status
function applyStatusToPill(pill, status) {
  // 0 pending, 1 approved, 2 rejected
  pill.classList.remove("pill--wait", "pill--ok", "pill--no");

  if (status === 0) {
    pill.textContent = "En attente";
    pill.classList.add("pill--wait");
  } else if (status === 1) {
    pill.textContent = "Accepté";
    pill.classList.add("pill--ok");
  } else {
    pill.textContent = "Rejeté";
    pill.classList.add("pill--no");
  }
}

// Valider / Refuser (AJAX + CSRF) => status = 1 ou 2
document.addEventListener("click", async (e) => {
  const btn = e.target.closest(".js-set-status");
  if (!btn) return;

  const id = btn.dataset.id;
  const statusStr = btn.dataset.status; // "1" ou "2"
  const status = Number(statusStr);

  if (status === 2) {
    const ok = confirm("Refuser ce score ?\n\nIl ne pourra plus être gagnant.");
    if (!ok) return;
  }

  const row = btn.closest("tr");
  const buttons = row ? row.querySelectorAll(".js-set-status") : [btn];
  buttons.forEach((b) => (b.disabled = true));

  try {
    const fd = new FormData();
    fd.append("id", id);
    fd.append("status", String(status));
    fd.append("csrf", csrf);

    const res = await fetch("/lasergame/api/set_status.php", {
      method: "POST",
      body: fd,
    });

    const data = await res.json();

    if (!res.ok || !data.ok) {
      alert(data.error || "Erreur serveur");
      buttons.forEach((b) => (b.disabled = false));
      return;
    }

    // UI update
    const pill = row?.querySelector("[data-pill]");
    if (pill) applyStatusToPill(pill, status);

    // Désactiver le bouton correspondant à l'état
    const btnApprove = row?.querySelector('.js-set-status[data-status="1"]');
    const btnReject = row?.querySelector('.js-set-status[data-status="2"]');
    if (btnApprove && btnReject) {
      btnApprove.disabled = status === 1;
      btnReject.disabled = status === 2;
    }
  } catch (err) {
    console.error(err);
    alert("Erreur réseau");
    buttons.forEach((b) => (b.disabled = false));
  }
});

// Nouveau code (30 min) + CSRF
async function generateCode() {
  if (!newCodeBtn) return;
  newCodeBtn.disabled = true;

  try {
    const fd = new FormData();
    fd.append("csrf", csrf);

    const res = await fetch("/lasergame/api/generate_party_code.php", {
      method: "POST",
      body: fd,
    });

    const data = await res.json();
    if (!res.ok || !data.ok) {
      alert(data.error || "Erreur génération code");
      return;
    }

    if (codeBox) {
      codeBox.textContent = `Code : ${data.code} (30 min)`;
      codeBox.hidden = false;
    }
  } catch (e2) {
    console.error(e2);
    alert("Erreur réseau");
  } finally {
    newCodeBtn.disabled = false;
  }
}

newCodeBtn?.addEventListener("click", generateCode);
