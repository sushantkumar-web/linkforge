// public/assets/js/app.js

// 1. Toast Notification
function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2200);
}

// 2. Clipboard Copy Utility
window.copyToClipboard = function(text) {
    // Modern API — only works on HTTPS or localhost
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showCopyToast('Copied to clipboard');
        }).catch(function() {
            fallbackCopy(text);
        });
        return;
    }

    // Fallback for HTTP — works everywhere
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

    try {
        var ok = document.execCommand('copy');
        showCopyToast(ok ? 'Copied to clipboard' : 'Copy failed — select manually');
    } catch (e) {
        showCopyToast('Copy failed — select manually');
    }

    document.body.removeChild(ta);
}

function showCopyToast(msg) {
    var t = document.createElement('div');
    t.innerText = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#10B981;color:white;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 2500);
}

// 3. Modal Helpers
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

// 4. Keyboard Shortcuts
document.addEventListener('keydown', (e) => {
    // Press '/' to search
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        const searchInput = document.getElementById('globalSearch');
        if (searchInput) searchInput.focus();
    }
    // Press 'c' to create link
    if (e.key === 'c' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        openModal('createModal');
    }
    // Press 'Escape' to close modals
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop').forEach(m => m.style.display = 'none');
    }
});