(function () {
    'use strict';

    function init() {
        var selectAll   = document.getElementById('selectAllLinks');
        var bulkBar     = document.getElementById('bulkBar');
        var bulkCountEl = document.getElementById('bulkCount');
        var checkboxes  = document.querySelectorAll('.link-checkbox[name="link_ids[]"]');

        if (!checkboxes.length) return;

        function updateBar() {
            var checked = document.querySelectorAll('.link-checkbox[name="link_ids[]"]:checked');
            var n = checked.length;

            if (n > 0) {
                bulkCountEl.textContent = n;
                bulkBar.classList.add('active');
            } else {
                bulkBar.classList.remove('active');
            }

            document.querySelectorAll('tr[data-link-row]').forEach(function (row) {
                var cb = row.querySelector('.link-checkbox');
                if (cb && cb.checked) row.classList.add('row-selected');
                else row.classList.remove('row-selected');
            });

            if (selectAll) {
                selectAll.checked = (n === checkboxes.length && checkboxes.length > 0);
                selectAll.indeterminate = (n > 0 && n < checkboxes.length);
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
                updateBar();
            });
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', updateBar);
        });

        window.bulkClear = function () {
            checkboxes.forEach(function (cb) { cb.checked = false; });
            if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
            updateBar();
        };

        window.bulkSubmit = function (action) {
            var checked = document.querySelectorAll('.link-checkbox[name="link_ids[]"]:checked');
            if (checked.length === 0) { alert('No links selected.'); return; }

            if (action === 'delete') {
                var plural = checked.length === 1 ? 'link' : 'links';
                if (!confirm('Delete ' + checked.length + ' ' + plural + '? This cannot be undone.')) return;
            }
            if (action === 'disable') {
                if (!confirm('Disable ' + checked.length + ' selected link(s)?')) return;
            }

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = document.getElementById('bulkFormAction').value;
            form.style.display = 'none';

            function addField(name, value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }

            addField('csrf_token', document.getElementById('bulkCsrf').value);
            addField('bulk_action', action);
            checked.forEach(function (cb) { addField('link_ids[]', cb.value); });

            document.body.appendChild(form);
            form.submit();
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
// -------- UTM preset live preview --------
window.updateUtmPreview = function () {
    var urlInput  = document.getElementById('createUrl');
    var presetSel = document.getElementById('createUtmPreset');
    var preview   = document.getElementById('utmPreview');
    if (!urlInput || !presetSel || !preview) return;

    var presetId = presetSel.value;
    if (!presetId) {
        preview.style.display = 'none';
        preview.innerHTML = '';
        return;
    }

    var opt = presetSel.options[presetSel.selectedIndex];
    var params = [];
    ['source', 'medium', 'campaign', 'term', 'content'].forEach(function (k) {
        var v = (opt.dataset[k] || '').trim();
        if (v) {
            params.push('utm_' + k + '=' + encodeURIComponent(v));
        }
    });

    if (!params.length) {
        preview.style.display = 'none';
        return;
    }

    var base = urlInput.value.trim() || 'https://example.com/page';
    preview.style.display = 'block';

    var html = '<span style="color: var(--text-muted);">' + escapeHtmlJs(base.replace(/[?&]utm_[^&]*/g, '').replace(/[?&]$/, '')) + '</span><br>?' +
        params.map(function (p) {
            var kv = p.split('=');
            return '<span style="color: #8B8DF8;">' + kv[0] + '</span>=<span style="color: #F5F5F5;">' + kv[1] + '</span>';
        }).join('<br>&amp;');

    preview.innerHTML = html;
};

// Helper — avoid depending on other utility files
function escapeHtmlJs(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Initialize preview on page load (in case a default preset is pre-selected)
(function () {
    function init() {
        if (typeof window.updateUtmPreview === 'function') {
            window.updateUtmPreview();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();