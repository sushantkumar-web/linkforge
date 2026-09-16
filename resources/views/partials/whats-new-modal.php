<?php
/**
 * What's New modal.
 * Expects $whatsNew = ['title' => ..., 'tagline' => ..., 'sections' => [...]]
 * Set by DashboardController::index() when a version bump is detected.
 */
if (empty($whatsNew)) return;
$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
?>

<style>
.whats-new-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 11, 14, 0.85);
    backdrop-filter: blur(6px);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: wnFadeIn 0.2s ease;
}
.whats-new-backdrop.active { display: flex; }

@keyframes wnFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.whats-new-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-xl);
    animation: wnSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes wnSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}

.wn-header {
    padding: 28px 28px 20px;
    border-bottom: 1px solid var(--border-subtle);
    background: linear-gradient(180deg, var(--accent-light) 0%, transparent 100%);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.wn-header .label {
    display: inline-block;
    padding: 3px 10px;
    background: var(--accent);
    color: #FFF;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    border-radius: var(--radius-pill);
    margin-bottom: 12px;
}
.wn-header h2 {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-bottom: 6px;
    color: var(--text-primary);
}
.wn-header .tagline {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}
.wn-body {
    padding: 24px 28px;
}
.wn-section {
    display: flex;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid var(--border-subtle);
}
.wn-section:last-child { border-bottom: none; padding-bottom: 0; }
.wn-section:first-child { padding-top: 0; }
.wn-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    background: var(--accent-light);
    border: 1px solid var(--accent-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-text);
    font-size: 14px;
    flex-shrink: 0;
}
.wn-content { min-width: 0; flex: 1; }
.wn-content h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--text-primary);
}
.wn-content p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 6px;
}
.wn-content .cta {
    font-size: 12px;
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.wn-content .cta:hover { text-decoration: underline; }

.wn-footer {
    padding: 20px 28px 24px;
    border-top: 1px solid var(--border-subtle);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.wn-footer .hint {
    font-size: 12px;
    color: var(--text-muted);
}
.wn-footer .actions {
    display: flex;
    gap: 8px;
}

@media (max-width: 480px) {
    .wn-header, .wn-body, .wn-footer { padding-left: 20px; padding-right: 20px; }
    .wn-header h2 { font-size: 18px; }
    .wn-footer { flex-direction: column-reverse; align-items: stretch; }
    .wn-footer .actions { width: 100%; }
    .wn-footer .actions .btn { flex: 1; }
}
</style>

<div id="whatsNewModal" class="whats-new-backdrop" onclick="if(event.target===this) dismissWhatsNew();">
    <div class="whats-new-card">
        <div class="wn-header">
            <span class="label">What's New</span>
            <h2><?= htmlspecialchars($whatsNew['title']) ?></h2>
            <?php if (!empty($whatsNew['tagline'])): ?>
                <div class="tagline"><?= htmlspecialchars($whatsNew['tagline']) ?></div>
            <?php endif; ?>
        </div>

        <div class="wn-body">
            <?php foreach ($whatsNew['sections'] as $section): ?>
                <div class="wn-section">
                    <div class="wn-icon">
                        <i class="<?= htmlspecialchars($section['icon'] ?? 'fa-solid fa-star') ?>"></i>
                    </div>
                    <div class="wn-content">
                        <h3><?= htmlspecialchars($section['title']) ?></h3>
                        <p><?= htmlspecialchars($section['body']) ?></p>
                        <?php if (!empty($section['link']) && !empty($section['cta'])): ?>
                            <a href="<?= $baseURL . htmlspecialchars($section['link']) ?>" class="cta">
                                <?= htmlspecialchars($section['cta']) ?>
                                <i class="fa-solid fa-arrow-right" style="font-size: 10px;"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wn-footer">
            <span class="hint">You'll only see this once per release.</span>
            <div class="actions">
                <button type="button" onclick="dismissWhatsNew()" class="btn btn-primary">
                    <i class="fa-solid fa-check" style="font-size: 11px;"></i>
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.dismissWhatsNew = function () {
    var modal = document.getElementById('whatsNewModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(function () { modal.remove(); }, 200);
    }
    // Persist so it doesn't show again
    fetch(window.LINKFORGE_BASE_URL + '/release-notes/dismiss', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin',
        body: 'csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?>')
    }).catch(function () { /* silent — worst case it shows again next visit */ });
};

// Auto-open when the page loads
(function () {
    var modal = document.getElementById('whatsNewModal');
    if (!modal) return;
    // Small delay so the dashboard has a chance to render
    setTimeout(function () { modal.classList.add('active'); }, 300);
})();
</script>