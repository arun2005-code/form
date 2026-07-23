/**
 * Smart Customer Registration System — Frontend logic
 * Handles: theme toggle, live validation, image previews,
 * progress bar, local-storage draft autosave, and the
 * submit -> backend -> success flow.
 */

const API_BASE = '../backend'; // adjust if backend is hosted elsewhere

document.addEventListener('DOMContentLoaded', () => {
  initLoadingScreen();
  initThemeToggle();
  initForm();
});

/* ---------------- Loading screen ---------------- */
function initLoadingScreen() {
  const screen = document.getElementById('loadingScreen');
  window.addEventListener('load', () => {
    setTimeout(() => screen.classList.add('hide'), 400);
  });
  // Fallback in case 'load' already fired
  setTimeout(() => screen.classList.add('hide'), 1200);
}

/* ---------------- Dark mode ---------------- */
function initThemeToggle() {
  const toggle = document.getElementById('themeToggle');
  const icon = toggle.querySelector('i');
  const saved = localStorage.getItem('scrs_theme');

  if (saved === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    icon.className = 'fa-solid fa-sun';
  }

  toggle.addEventListener('click', () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('scrs_theme', 'light');
      icon.className = 'fa-solid fa-moon';
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('scrs_theme', 'dark');
      icon.className = 'fa-solid fa-sun';
    }
  });
}

/* ---------------- Main form logic ---------------- */
function initForm() {
  const form = document.getElementById('registrationForm');
  const DRAFT_KEY = 'scrs_draft_v1';
  const TEXT_FIELDS = [
    'full_name', 'father_name', 'gender', 'dob', 'mobile_number', 'whatsapp_number',
    'email', 'address', 'city', 'district', 'state', 'pincode', 'occupation',
    'company_name', 'annual_income', 'preferred_language', 'customer_category',
    'aadhar_number', 'pan_number', 'gst_number', 'reference_name', 'reference_mobile',
    'source', 'remarks',
  ];

  fetchCsrfToken();
  restoreDraft();
  updateProgress();

  /* ---- CSRF token from backend ---- */
  function fetchCsrfToken() {
    fetch(`${API_BASE}/api/csrf.php`)
      .then((r) => r.json())
      .then((data) => {
        if (data.success) document.getElementById('csrfToken').value = data.csrf_token;
      })
      .catch(() => { /* backend may not be running in preview; ignore */ });
  }

  /* ---- Same as mobile checkbox ---- */
  document.getElementById('sameAsMobile').addEventListener('change', (e) => {
    if (e.target.checked) {
      document.getElementById('whatsapp_number').value = document.getElementById('mobile_number').value;
      validateField(document.getElementById('whatsapp_number'));
    }
  });

  /* ---- Auto-calculate age from DOB ---- */
  const dobInput = document.getElementById('dob');
  dobInput.addEventListener('change', () => {
    const ageField = document.getElementById('age');
    const dob = new Date(dobInput.value);
    if (isNaN(dob.getTime())) { ageField.value = ''; return; }
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    ageField.value = age >= 0 ? age + ' years' : '';
    validateField(dobInput);
  });

  /* ---- PAN / GST uppercase ---- */
  ['pan_number', 'gst_number'].forEach((id) => {
    document.getElementById(id).addEventListener('input', (e) => {
      e.target.value = e.target.value.toUpperCase();
    });
  });

  /* ---- Live validation on every input ---- */
  form.querySelectorAll('input, select, textarea').forEach((el) => {
    el.addEventListener('input', () => { validateField(el); saveDraft(); updateProgress(); });
    el.addEventListener('blur', () => validateField(el));
    el.addEventListener('change', () => { updateProgress(); saveDraft(); });
  });

  /* ---- File uploads ---- */
  ['photo', 'id_proof', 'signature'].forEach(setupUpload);

  function setupUpload(fieldName) {
    const input = document.getElementById(fieldName);
    const box = document.querySelector(`.upload-box[data-target="${fieldName}"]`);
    const preview = document.getElementById(fieldName + 'Preview');
    const errorMsg = box.closest('.upload-field').querySelector('.error-msg');

    box.addEventListener('click', () => input.click());

    ['dragover', 'dragenter'].forEach((evt) =>
      box.addEventListener(evt, (e) => { e.preventDefault(); box.classList.add('dragover'); })
    );
    ['dragleave', 'drop'].forEach((evt) =>
      box.addEventListener(evt, (e) => { e.preventDefault(); box.classList.remove('dragover'); })
    );
    box.addEventListener('drop', (e) => {
      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        handleFile();
      }
    });

    input.addEventListener('change', handleFile);

    function handleFile() {
      const file = input.files[0];
      errorMsg.textContent = '';
      if (!file) return;

      const allowed = ['image/jpeg', 'image/png', 'image/jpg'];
      if (!allowed.includes(file.type)) {
        errorMsg.textContent = 'Only JPG, JPEG or PNG allowed.';
        input.value = '';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        errorMsg.textContent = 'File must be under 2MB.';
        input.value = '';
        return;
      }

      // Simulated upload progress for UX feedback
      box.classList.add('uploading');
      const fill = box.querySelector('.upload-progress-fill');
      let pct = 0;
      const timer = setInterval(() => {
        pct += 20;
        fill.style.width = pct + '%';
        if (pct >= 100) {
          clearInterval(timer);
          box.classList.remove('uploading');
          fill.style.width = '0%';
        }
      }, 60);

      const reader = new FileReader();
      reader.onload = (e) => {
        preview.src = e.target.result;
        box.classList.add('filled');
      };
      reader.readAsDataURL(file);
      updateProgress();
    }
  }

  /* ---- Field-level validation ---- */
  function validateField(el) {
    const field = el.closest('.field');
    if (!field) return true;
    const errorEl = field.querySelector('.error-msg');
    let message = '';

    if (el.required && !el.value.trim()) {
      message = 'This field is required.';
    } else if (el.value.trim()) {
      switch (el.id) {
        case 'email':
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value)) message = 'Enter a valid email address.';
          break;
        case 'mobile_number':
        case 'whatsapp_number':
        case 'reference_mobile':
          if (!/^[6-9]\d{9}$/.test(el.value)) message = 'Enter a valid 10-digit number.';
          break;
        case 'aadhar_number':
          if (!/^\d{12}$/.test(el.value)) message = 'Aadhar must be exactly 12 digits.';
          break;
        case 'pan_number':
          if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(el.value)) message = 'Format: ABCDE1234F';
          break;
        case 'gst_number':
          if (el.value && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(el.value)) message = 'Enter a valid 15-character GSTIN.';
          break;
        case 'pincode':
          if (!/^\d{6}$/.test(el.value)) message = 'Pincode must be 6 digits.';
          break;
        case 'dob': {
          const dob = new Date(el.value);
          const age = new Date().getFullYear() - dob.getFullYear();
          if (age < 18 || age > 120) message = 'Customer must be above 18 years old.';
          break;
        }
      }
    }

    field.classList.toggle('invalid', !!message);
    field.classList.toggle('valid', !message && !!el.value.trim());
    if (errorEl) errorEl.textContent = message;
    return !message;
  }

  /* ---- Progress bar ---- */
  function updateProgress() {
    const sections = form.querySelectorAll('.form-section');
    const bar = document.getElementById('progressBar');
    const percentLabel = document.getElementById('progressPercent');
    const stepEls = document.querySelectorAll('.progress-steps .step');

    const requiredEls = Array.from(form.querySelectorAll('[required]'));
    const filled = requiredEls.filter((el) => el.type === 'checkbox' ? el.checked : el.value.trim() !== '');
    const percent = requiredEls.length ? Math.round((filled.length / requiredEls.length) * 100) : 0;

    bar.style.width = percent + '%';
    percentLabel.textContent = percent + '%';

    sections.forEach((section) => {
      const stepName = section.dataset.step;
      const stepEl = document.querySelector(`.progress-steps .step[data-step="${stepName}"]`);
      if (!stepEl) return;
      const reqInSection = section.querySelectorAll('[required]');
      const filledInSection = Array.from(reqInSection).filter((el) => el.type === 'checkbox' ? el.checked : el.value.trim() !== '');
      stepEl.classList.toggle('done', reqInSection.length > 0 && filledInSection.length === reqInSection.length);
    });

    // highlight active step by scroll position
    let activeSet = false;
    sections.forEach((section) => {
      const rect = section.getBoundingClientRect();
      if (!activeSet && rect.top < window.innerHeight * 0.5 && rect.bottom > 100) {
        stepEls.forEach((s) => s.classList.remove('active'));
        const stepEl = document.querySelector(`.progress-steps .step[data-step="${section.dataset.step}"]`);
        if (stepEl) stepEl.classList.add('active');
        activeSet = true;
      }
    });
  }
  window.addEventListener('scroll', updateProgress);

  /* ---- Auto-save draft (text fields only — never files) ---- */
  function saveDraft() {
    const draft = {};
    TEXT_FIELDS.forEach((name) => {
      const el = form.elements[name];
      if (el) draft[name] = el.value;
    });
    draft.terms = form.elements['terms'].checked;
    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
  }

  function restoreDraft() {
    const saved = localStorage.getItem(DRAFT_KEY);
    if (!saved) return;
    try {
      const draft = JSON.parse(saved);
      TEXT_FIELDS.forEach((name) => {
        const el = form.elements[name];
        if (el && draft[name] !== undefined) el.value = draft[name];
      });
      if (draft.terms) form.elements['terms'].checked = true;
      if (form.elements['dob'].value) dobInput.dispatchEvent(new Event('change'));
      showToast('Draft restored from your last visit.', 'info');
    } catch (e) { /* ignore corrupt draft */ }
  }

  function clearDraft() {
    localStorage.removeItem(DRAFT_KEY);
  }

  /* ---- Reset confirmation ---- */
  const resetOverlay = document.getElementById('resetOverlay');
  document.getElementById('resetBtn').addEventListener('click', (e) => {
    e.preventDefault();
    resetOverlay.classList.add('show');
  });
  document.getElementById('cancelResetBtn').addEventListener('click', () => resetOverlay.classList.remove('show'));
  document.getElementById('confirmResetBtn').addEventListener('click', () => {
    form.reset();
    document.querySelectorAll('.upload-box').forEach((b) => b.classList.remove('filled'));
    document.getElementById('age').value = '';
    clearDraft();
    form.querySelectorAll('.field').forEach((f) => { f.classList.remove('valid', 'invalid'); });
    resetOverlay.classList.remove('show');
    updateProgress();
    showToast('Form cleared.', 'info');
  });

  /* ---- Submit ---- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    let valid = true;
    form.querySelectorAll('[required]').forEach((el) => {
      if (el.type === 'file') {
        if (!el.files.length) {
          valid = false;
          const field = el.closest('.upload-field');
          if (field) field.querySelector('.error-msg').textContent = 'This file is required.';
        }
      } else if (el.type === 'checkbox') {
        if (!el.checked) {
          valid = false;
          document.getElementById('termsError').textContent = 'Please accept the Terms & Conditions.';
        }
      } else if (!validateField(el)) {
        valid = false;
      }
    });

    if (!valid) {
      showToast('Please fix the highlighted fields.', 'error');
      document.querySelector('.field.invalid, .upload-field .error-msg:not(:empty)')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.classList.add('loading');

    const formData = new FormData(form);

    try {
      const res = await fetch(`${API_BASE}/insert.php`, { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        clearDraft();
        document.getElementById('resultCustomerId').textContent = data.customer_id;
        document.getElementById('resultRegNo').textContent = data.registration_no;
        document.getElementById('viewReceiptBtn').href = `${API_BASE}/api/receipt.php?id=${encodeURIComponent(data.customer_id)}`;
        document.getElementById('successOverlay').classList.add('show');
      } else {
        if (data.errors) {
          Object.entries(data.errors).forEach(([field, msg]) => {
            const el = form.elements[field];
            if (el) {
              const container = el.closest('.field') || el.closest('.upload-field');
              if (container) {
                container.classList.add('invalid');
                const em = container.querySelector('.error-msg');
                if (em) em.textContent = msg;
              }
            }
          });
        }
        showToast(data.message || 'Submission failed. Please check the form.', 'error');
      }
    } catch (err) {
      showToast('Could not reach the server. Please try again.', 'error');
    } finally {
      submitBtn.classList.remove('loading');
    }
  });

  document.getElementById('closeSuccessBtn').addEventListener('click', () => {
    document.getElementById('successOverlay').classList.remove('show');
    form.reset();
    document.querySelectorAll('.upload-box').forEach((b) => b.classList.remove('filled'));
    document.getElementById('age').value = '';
    form.querySelectorAll('.field').forEach((f) => f.classList.remove('valid', 'invalid'));
    updateProgress();
  });
}

/* ---------------- Toast helper ---------------- */
function showToast(message, type = 'info') {
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info'}"></i> ${message}`;
  stack.appendChild(toast);
  setTimeout(() => toast.remove(), 3800);
}
