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

// ── Auto-dismiss alerts after 4s ────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert.auto-dismiss').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });
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
