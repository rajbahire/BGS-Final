// ============================================================
//  assets/js/app.js — Global JavaScript Utilities
//  College Bill Generation System — GCEA
// ============================================================

// ── Confirm dialog ───────────────────────────────────────────
function confirmAction(msg) {
    return confirm(msg || 'Are you sure?');
}

// ── Format as Indian Rupees ──────────────────────────────────
function formatINR(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ── Copy to clipboard utility ──────────────────────────────────
function copyToClipboard(text, successMsg) {
    navigator.clipboard.writeText(text).then(function() {
        showToast(successMsg || 'Copied to clipboard!', 'success');
    }).catch(function(err) {
        console.error('Copy failed:', err);
    });
}

// ── Show toast notification ───────────────────────────────────
function showToast(message, type = 'info', duration = 4000) {
    const container = document.querySelector('.toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        ${getToastIcon(type)}
        ${message}
        <button class="toast-close" aria-label="Close">&times;</button>
        <div class="toast-progress"></div>
    `;

    container.appendChild(toast);

    // Auto-dismiss with hover-pause
    let remaining = duration;
    let startTime = Date.now();
    let timer = setTimeout(() => dismissToast(toast), remaining);

    toast.addEventListener('mouseenter', () => {
        clearTimeout(timer);
        remaining -= Date.now() - startTime;
        toast.classList.add('toast-paused');
    });
    toast.addEventListener('mouseleave', () => {
        toast.classList.remove('toast-paused');
        startTime = Date.now();
        timer = setTimeout(() => dismissToast(toast), remaining);
    });

    // Close button
    toast.querySelector('.toast-close').addEventListener('click', () => dismissToast(toast));
}

function getToastIcon(type) {
    const icons = {
        success: '<span class="check-icon">✓</span>',
        error: '<span class="error-icon">✗</span>',
        warning: '<span class="warning-icon">⚠</span>',
        info: '<span class="info-icon">ℹ</span>'
    };
    return icons[type] || icons.info;
}

function dismissToast(toast) {
    toast.classList.add('toast-exit');
    setTimeout(() => toast.remove(), 350);
}

// ── Form validation enhancements ───────────────────────────────
function validateField(field) {
    const value = field.value.trim();
    const isValid = field.checkValidity();

    if (!isValid) {
        field.classList.add('is-invalid');
    } else {
        field.classList.remove('is-invalid');
    }

    return isValid;
}

function setupFormValidation(form) {
    const fields = form.querySelectorAll('input[required], textarea[required], select[required]');

    fields.forEach(field => {
        field.addEventListener('blur', () => validateField(field));
        field.addEventListener('input', () => {
            if (field.classList.contains('is-invalid') && validateField(field)) {
                field.classList.remove('is-invalid');
            }
        });
    });

    form.addEventListener('submit', (e) => {
        let valid = true;
        fields.forEach(field => {
            if (!validateField(field)) valid = false;
        });

        if (!valid) {
            e.preventDefault();
            showToast('Please fill all required fields', 'error');
        }
    });
}

// ── Table sorting utility ─────────────────────────────────────
function makeSortable(table) {
    const headers = table.querySelectorAll('th[data-sortable="true"]');

    headers.forEach((header, index) => {
        header.innerHTML += '<span class="sort-indicator">⇅</span>';
        header.style.cursor = 'pointer';
        header.addEventListener('click', () => {
            const ascending = !header.classList.contains('asc');
            sortTableByColumn(table, index, ascending);
        });
    });
}

function sortTableByColumn(table, colIndex, ascending) {
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);

    rows.sort((a, b) => {
        const aText = a.cells[colIndex].textContent.trim();
        const bText = b.cells[colIndex].textContent.trim();

        if (/^\d/.test(aText)) {
            return ascending ? parseFloat(aText) - parseFloat(bText) : parseFloat(bText) - parseFloat(aText);
        }

        return ascending ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });

    rows.forEach(row => tbody.appendChild(row));

    // Update indicators
    table.querySelectorAll('.sort-indicator').forEach(i => i.classList.remove('asc'));
    table.querySelector('th').classList.toggle('asc', ascending);
}

// ── Convert auto-dismiss alerts into floating toasts ─────────
document.addEventListener('DOMContentLoaded', function () {
    var alerts = document.querySelectorAll('.alert.auto-dismiss');
    if (!alerts.length) return;

    // Create toast container if it doesn't exist
    var container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    alerts.forEach(function (el) {
        // Determine the type from existing alert class
        var type = 'info';
        if (el.classList.contains('alert-success')) type = 'success';
        else if (el.classList.contains('alert-error')) type = 'error';
        else if (el.classList.contains('alert-warning')) type = 'warning';

        // Build the toast element
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = el.innerHTML +
            '<button class="toast-close" aria-label="Close">&times;</button>' +
            '<div class="toast-progress"></div>';

        container.appendChild(toast);

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', function () {
            dismissToast(toast);
        });

        // Auto dismiss after 4s with hover-pause
        var duration = 4000;
        var remaining = duration;
        var startTime = Date.now();
        var timer = setTimeout(function () { dismissToast(toast); }, remaining);

        toast.addEventListener('mouseenter', function () {
            clearTimeout(timer);
            remaining -= Date.now() - startTime;
            toast.classList.add('toast-paused');
        });
        toast.addEventListener('mouseleave', function () {
            toast.classList.remove('toast-paused');
            startTime = Date.now();
            timer = setTimeout(function () { dismissToast(toast); }, remaining);
        });

        // Remove the original inline alert
        el.remove();
    });

    function dismissToast(toast) {
        if (toast.classList.contains('toast-exit')) return;
        toast.classList.add('toast-exit');
        setTimeout(function () { toast.remove(); }, 350);
    }
});

// ── Set today as default in date inputs ─────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Use local date (IST) — toISOString() would give UTC which can be a day behind
    const now   = new Date();
    const today = now.getFullYear() + '-'
        + String(now.getMonth() + 1).padStart(2, '0') + '-'
        + String(now.getDate()).padStart(2, '0');
    document.querySelectorAll('input[type="date"][data-today]').forEach(function (inp) {
        if (!inp.value) inp.value = today;
    });
});

// ── Password show / hide ─────────────────────────────────────
function togglePw(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        if (eye) eye.innerHTML = eye.dataset.off || eye.dataset.on || '';
    } else {
        inp.type = 'password';
        if (eye) eye.innerHTML = eye.dataset.on || eye.dataset.off || '';
    }
}

// ── Fill demo credentials ────────────────────────────────────
function fillDemo(email, password) {
    const e = document.getElementById('email');
    const p = document.getElementById('password');
    if (e) e.value = email;
    if (p) p.value = password;
}

// ── Modal open / close ───────────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);

    if (!el) return;

    el.classList.add('open');
    document.body.classList.add('modal-open');
}

function closeModal(id) {
    const el = document.getElementById(id);

    if (!el) return;

    el.classList.remove('open');

    if (!document.querySelector('.modal-backdrop.open')) {
        document.body.classList.remove('modal-open');
    }
}

// Close modal on backdrop click
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {

        backdrop.addEventListener('click', function (e) {

            if (e.target === backdrop) {

                backdrop.classList.remove('open');

                if (!document.querySelector('.modal-backdrop.open')) {
                    document.body.classList.remove('modal-open');
                }

            }

        });

    });

    // ESC key closes modal
    document.addEventListener('keydown', function (e) {

        if (e.key === 'Escape') {

            const modal = document.querySelector('.modal-backdrop.open');

            if (modal) {
                modal.classList.remove('open');
                document.body.classList.remove('modal-open');
            }

        }

    });

});

// ── Live bill total calculator ───────────────────────────────
function calcBillTotal() {
    const theory     = parseFloat(document.getElementById('theory_hrs')?.value    || 0);
    const practical  = parseFloat(document.getElementById('practical_hrs')?.value || 0);
    const other      = parseFloat(document.getElementById('other_hrs')?.value     || 0);
    const rateT      = parseFloat(document.getElementById('rate_theory')?.value   || 0);
    const rateP      = parseFloat(document.getElementById('rate_practical')?.value|| 0);
    const rateO      = parseFloat(document.getElementById('rate_other')?.value    || 0);

    const tAmt = theory    * rateT;
    const pAmt = practical * rateP;
    const oAmt = other     * rateO;
    const total= tAmt + pAmt + oAmt;

    const elTA = document.getElementById('theory_amount');
    const elPA = document.getElementById('practical_amount');
    const elOA = document.getElementById('other_amount');
    const elTT = document.getElementById('total_amount');

    if (elTA) elTA.textContent = formatINR(tAmt);
    if (elPA) elPA.textContent = formatINR(pAmt);
    if (elOA) elOA.textContent = formatINR(oAmt);
    if (elTT) elTT.textContent = formatINR(total);
}

// ── Cascade selectors (dept → class → subject) ──────────────
function cascadeSelect(triggerEl, targetId, fetchUrl) {
    const val = triggerEl.value;
    const target = document.getElementById(targetId);
    if (!target || !val) {
        target.innerHTML = '<option value="">— select —</option>';
        target.disabled = true;
        return;
    }
    target.disabled = true;
    target.innerHTML = '<option>Loading...</option>';
    fetch(fetchUrl + '?id=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
            target.innerHTML = '<option value="">— select —</option>';
            data.forEach(function (item) {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.label || item.name;
                target.appendChild(opt);
            });
            target.disabled = false;
        })
        .catch(function () {
            target.innerHTML = '<option value="">Error loading</option>';
            target.disabled = false;
        });
}

// ── Table filter (client-side search) ───────────────────────
function tableSearch(inputId, tableId) {
    const input  = document.getElementById(inputId);
    const tbody  = document.querySelector('#' + tableId + ' tbody');
    if (!input || !tbody) return;
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        tbody.querySelectorAll('tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ── Initialise all table searches on page load ───────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-search-table]').forEach(function (inp) {
        const tableId = inp.dataset.searchTable;
        inp.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            const tbody = document.querySelector('#' + tableId + ' tbody');
            if (!tbody) return;
            tbody.querySelectorAll('tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });
});

// ── Profile page tab switcher ────────────────────────────────
// One .profile-card holds a .profile-tabs strip and several
// .profile-panel[data-panel] sections. Clicking a .prof-tab
// reveals its matching panel and hides the rest.
document.addEventListener('DOMContentLoaded', function () {
    var card = document.querySelector('.profile-card');
    if (!card) return;
    var tabBar = card.querySelector('.profile-tabs');
    if (!tabBar) return;

    tabBar.addEventListener('click', function (e) {
        var btn = e.target.closest('.prof-tab');
        if (!btn || !btn.dataset.tab) return;

        card.querySelectorAll('.prof-tab').forEach(function (t) {
            t.classList.toggle('active', t === btn);
        });

        var name = btn.dataset.tab;
        card.querySelectorAll('.profile-panel').forEach(function (p) {
            p.hidden = p.dataset.panel !== name;
        });
    });
});
