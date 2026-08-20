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

        // Auto dismiss after 4s
        setTimeout(function () { dismissToast(toast); }, 4000);

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
    const today = new Date().toISOString().split('T')[0];
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
