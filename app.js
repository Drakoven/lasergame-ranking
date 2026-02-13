// ===============================
// 1) Récupération des éléments
// ===============================
const form = document.querySelector("#scoreForm");
const message = document.querySelector("#message");

const email = document.querySelector("#email");
const partyDateTime = document.querySelector("#partyDateTime");
const vestPseudo = document.querySelector("#vestPseudo");
const playerPseudo = document.querySelector("#playerPseudo");
const score = document.querySelector("#score");
const partyCode = document.querySelector("#partyCode");
const photoInput = document.querySelector("#photo");
const rules = document.querySelector("#rules");

const preview = document.querySelector("#photoPreview");
const photoInfo = document.querySelector("#photoInfo");

const themeToggle = document.querySelector("#themeToggle");
const submitBtn = document.querySelector("#submitBtn");

// MODAL récapitulatif (tes IDs HTML)
const summaryModal = document.querySelector("#summaryModal");
const modalOverlay = document.querySelector("#modalOverlay");
const modalClose = document.querySelector("#modalClose");
const modalOk = document.querySelector("#modalOk");
const summaryCard = document.querySelector("#summaryCard");

let isSubmitting = false;

// ===============================
// 2) Constantes & utilitaires
// ===============================
const MAX_MB = 5;
const MAX_BYTES = MAX_MB * 1024 * 1024;

let currentObjectURL = null;

function formatBytes(bytes) {
  const mb = bytes / (1024 * 1024);
  return `${mb.toFixed(2)} Mo`;
}

function setMessage(text, type = "info") {
  message.textContent = text;
  if (type === "error") message.style.color = "crimson";
  else if (type === "success") message.style.color = "green";
  else message.style.color = "#333";
}

function popMessage() {
  message.classList.remove("pop");
  void message.offsetWidth;
  message.classList.add("pop");
}

// ===============================
// 3) UI validation
// ===============================
function markValid(el) {
  el.classList.remove("is-invalid");
  el.classList.add("is-valid");
}

function markInvalid(el) {
  el.classList.remove("is-valid");
  el.classList.add("is-invalid");
}

// ===============================
// 4) Validation live (text + score)
// ===============================
function attachMinLengthValidation(input, minLength) {
  input.addEventListener("input", () => {
    const value = input.value.trim();

    if (value.length === 0) {
      input.classList.remove("is-valid", "is-invalid");
      updateSubmitButton();
      return;
    }

    if (value.length >= minLength) markValid(input);
    else markInvalid(input);

    updateSubmitButton();
  });
}

function isScoreValid(rawValue) {
  if (rawValue.trim().length === 0) return null;
  const n = Number(rawValue);
  if (!Number.isFinite(n)) return false;
  if (n < 0) return false;
  return true;
}

function attachScoreValidation(input) {
  input.addEventListener("input", () => {
    const verdict = isScoreValid(input.value);

    if (verdict === null) {
      input.classList.remove("is-valid", "is-invalid");
      updateSubmitButton();
      return;
    }

    if (verdict) markValid(input);
    else markInvalid(input);

    updateSubmitButton();
  });
}

attachMinLengthValidation(vestPseudo, 3);
attachMinLengthValidation(playerPseudo, 3);
attachMinLengthValidation(partyCode, 3);
attachScoreValidation(score);

// ===============================
// 5) Photo : validation + preview
// ===============================
function validatePhotoFile(file) {
  if (!file) return { ok: false, error: "Merci d’ajouter une photo du classement." };

  const allowedTypes = ["image/jpeg", "image/png"];
  if (!allowedTypes.includes(file.type)) {
    return { ok: false, error: "Photo invalide : seulement JPG ou PNG." };
  }

  if (file.size > MAX_BYTES) {
    return { ok: false, error: `Photo trop lourde : maximum ${MAX_MB} Mo.` };
  }

  return { ok: true, error: "" };
}

function resetPreview() {
  if (currentObjectURL) {
    URL.revokeObjectURL(currentObjectURL);
    currentObjectURL = null;
  }
  preview.classList.remove("show");
  preview.style.display = "none";
  preview.src = "";

  if (photoInfo) {
    photoInfo.textContent = "";
    photoInfo.style.color = "#555";
  }
}

photoInput.addEventListener("change", () => {
  const file = photoInput.files[0];

  if (!file) {
    photoInput.classList.remove("is-valid", "is-invalid");
    resetPreview();
    updateSubmitButton();
    return;
  }

  if (photoInfo) {
    photoInfo.textContent = `Fichier : ${file.name} — ${formatBytes(file.size)}`;
    photoInfo.style.color = "#555";
  }

  const check = validatePhotoFile(file);
  if (!check.ok) {
    if (photoInfo) {
      photoInfo.textContent = `❌ ${check.error} (${formatBytes(file.size)})`;
      photoInfo.style.color = "crimson";
    }

    photoInput.value = "";
    markInvalid(photoInput);
    resetPreview();
    updateSubmitButton();
    return;
  }

  markValid(photoInput);

  if (currentObjectURL) URL.revokeObjectURL(currentObjectURL);

  currentObjectURL = URL.createObjectURL(file);
  preview.src = currentObjectURL;
  preview.style.display = "block";
  requestAnimationFrame(() => preview.classList.add("show"));

  updateSubmitButton();
});

// ===============================
// 6) Bouton loading
// ===============================
function setLoadingButton(isLoading) {
  if (isLoading) {
    submitBtn.disabled = true;
    submitBtn.classList.add("is-loading");
    submitBtn.dataset.originalText = submitBtn.textContent;
    submitBtn.innerHTML = `<span class="spinner"></span>Envoi...`;
  } else {
    submitBtn.classList.remove("is-loading");
    submitBtn.textContent = submitBtn.dataset.originalText || "Envoyer";
    updateSubmitButton();
  }
}

// ===============================
// 7) Etat formulaire
// ===============================
function isFormValid() {
  if (vestPseudo.value.trim().length < 3) return false;
  if (playerPseudo.value.trim().length < 3) return false;
  if (partyCode.value.trim().length < 3) return false;

  const scoreValue = Number(score.value);
  if (!Number.isFinite(scoreValue) || scoreValue < 0) return false;

  const file = photoInput.files[0];
  if (!validatePhotoFile(file).ok) return false;

  if (!rules.checked) return false;

  return true;
}

function updateSubmitButton() {
  if (isSubmitting) {
    submitBtn.disabled = true;
    return;
  }
  submitBtn.disabled = !isFormValid();
}

[rules, partyDateTime].forEach((el) => {
  el.addEventListener("input", updateSubmitButton);
  el.addEventListener("change", updateSubmitButton);
});

// ===============================
// 8) Modal récapitulatif
// ===============================
function openSummaryModal() {
  if (!summaryModal) return;
  summaryModal.hidden = false;
  requestAnimationFrame(() => summaryModal.classList.add("is-open"));
  document.body.style.overflow = "hidden";
}

function closeSummaryModal() {
  if (!summaryModal) return;
  summaryModal.classList.remove("is-open");
  document.body.style.overflow = "";
  setTimeout(() => {
    summaryModal.hidden = true;
  }, 220);
}

if (summaryModal && modalOverlay && modalClose && modalOk) {
  modalOverlay.addEventListener("click", closeSummaryModal);
  modalClose.addEventListener("click", closeSummaryModal);
  modalOk.addEventListener("click", closeSummaryModal);

  document.addEventListener("keydown", (e) => {
    if (!summaryModal.hidden && e.key === "Escape") closeSummaryModal();
  });
}

function showSummaryModal() {
  if (!summaryCard) return;

  const file = photoInput.files[0];
  const when = partyDateTime.value || "—";

  summaryCard.innerHTML = `
    <ul>
      <li><span>Gilet</span><strong>${vestPseudo.value.trim() || "—"}</strong></li>
      <li><span>Joueur</span><strong>${playerPseudo.value.trim() || "—"}</strong></li>
      <li><span>Score</span><strong>${score.value || "—"}</strong></li>
      <li><span>Date</span><strong>${when}</strong></li>
      <li><span>Photo</span><strong>${file ? file.name : "—"}</strong></li>
    </ul>
  `;

  openSummaryModal();
}

// ===============================
// 9) ENVOI RÉEL (fetch + FormData)
// ===============================
async function submitToServer() {
  const res = await fetch("/lasergame/api/submit_score.php", {
    method: "POST",
    body: new FormData(form),
  });

  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error("Réponse serveur non-JSON : " + text);
  }

  if (!res.ok || !data.ok) {
    throw new Error(data.error || "Erreur serveur");
  }

  return data;
}

// ===============================
// 10) Submit
// ===============================
form.addEventListener("submit", (e) => {
  e.preventDefault();

  if (isSubmitting) return;

  if (!isFormValid()) {
    setMessage("❌ Formulaire incomplet : corrige les champs en rouge.", "error");
    popMessage();
    updateSubmitButton();
    return;
  }

  isSubmitting = true;
  setLoadingButton(true);
  setMessage("Envoi en cours...", "info");
  popMessage();

  submitToServer()
    .then(() => {
      setLoadingButton(false);
      setMessage("✅ Score enregistré ! Merci, on vérifiera la photo avant validation.", "success");
      popMessage();
      showSummaryModal();
    })
    .catch((err) => {
      setLoadingButton(false);
      setMessage("❌ " + err.message, "error");
      popMessage();
    })
    .finally(() => {
      isSubmitting = false;
      updateSubmitButton();
    });
});

// ===============================
// 11) Dark mode
// ===============================
if (themeToggle) {
  const savedTheme = localStorage.getItem("theme");

  if (savedTheme === "dark") {
    document.body.classList.add("dark");
    themeToggle.textContent = "☀️";
  } else {
    themeToggle.textContent = "🌙";
  }

  themeToggle.addEventListener("click", () => {
    document.body.classList.toggle("dark");
    const isDark = document.body.classList.contains("dark");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    themeToggle.textContent = isDark ? "☀️" : "🌙";
  });
}

// Init
updateSubmitButton();
