/*!
 * LinkForge core utilities.
 * This file must load BEFORE any other script so that inline onclick
 * handlers (copyToClipboard, toggleSidebar) always find their targets.
 */

// -------- Clipboard copy (works on HTTP + HTTPS) --------
window.copyToClipboard = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () {
            showCopyToast('Copied to clipboard');
        }).catch(function () {
            fallbackCopy(text);
        });
        return;
    }
    fallbackCopy(text);
};

function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.top = '-9999px';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    showCopyToast(ok ? 'Copied to clipboard' : 'Copy failed — select manually');
}

function showCopyToast(msg) {
    var t = document.createElement('div');
    t.innerText = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#10B981;color:white;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 2500);
}

// -------- Mobile sidebar toggle --------
window.toggleSidebar = function () {
    var sidebar  = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar) return;
    var isOpen = sidebar.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
};

// -------- Generic modal helpers (used by links, api-keys, users, webhooks) --------
window.openModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.add('active');
};
window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('active');
};

// Close modals on backdrop click
document.addEventListener('click', function (e) {
    if (e.target && e.target.classList.contains('lf-modal-backdrop')) {
        window.closeModal(e.target.id);
    }
});

// Auto-close mobile sidebar when a nav link is clicked
(function () {
    function bind() {
        document.querySelectorAll('#appSidebar .nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    var sidebar  = document.getElementById('appSidebar');
                    var backdrop = document.getElementById('sidebarBackdrop');
                    if (sidebar)  sidebar.classList.remove('open');
                    if (backdrop) backdrop.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                var sidebar  = document.getElementById('appSidebar');
                var backdrop = document.getElementById('sidebarBackdrop');
                if (sidebar)  sidebar.classList.remove('open');
                if (backdrop) backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();