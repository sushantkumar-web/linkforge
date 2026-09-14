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
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('✓ Link copied to clipboard');
    });
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