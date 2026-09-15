(function () {
    'use strict';

    function init() {
        var input    = document.getElementById('globalSearch');
        var dropdown = document.getElementById('searchDropdown');
        var baseURL  = window.LINKFORGE_BASE_URL || '';

        console.log('[LinkForge Search] Init. Input:', !!input, 'Dropdown:', !!dropdown, 'baseURL:', baseURL);

        if (!input || !dropdown) {
            console.warn('[LinkForge Search] Aborted — missing input or dropdown.');
            return;
        }

        var debounceTimer = null;
        var lastQuery = '';

        // Reposition dropdown as `fixed` right under the input, so no
        // ancestor with overflow:hidden can clip it.
        function positionDropdown() {
            var rect = input.getBoundingClientRect();
            dropdown.style.top   = (rect.bottom + 8) + 'px';
            dropdown.style.left  = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
        }

        function openDropdown() {
            positionDropdown();
            dropdown.style.display = 'block';
        }

        function closeDropdown() {
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
        }

        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function fetchResults(q) {
            var url = baseURL + '/search/quick?q=' + encodeURIComponent(q);
            console.log('[LinkForge Search] Fetching:', url);

            fetch(url, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) {
                var ct = r.headers.get('content-type') || '';
                console.log('[LinkForge Search] Status:', r.status, 'Content-Type:', ct);

                if (ct.indexOf('application/json') === -1) {
                    return r.text().then(function (t) {
                        console.error('[LinkForge Search] Non-JSON response (first 400 chars):', t.substring(0, 400));
                        throw new Error('Non-JSON response');
                    });
                }
                return r.json();
            })
            .then(function (data) {
                console.log('[LinkForge Search] Data:', data);
                renderResults(data.results || [], q);
            })
            .catch(function (err) {
                console.error('[LinkForge Search] Fetch failed:', err);
                closeDropdown();
            });
        }

        function renderResults(results, q) {
            if (!results.length) {
                dropdown.innerHTML =
                    '<div style="padding: 20px; text-align: center; color: #9CA3AF; font-size: 13px;">' +
                    'No links match <strong style="color:#F5F5F5;">' + escapeHtml(q) + '</strong>' +
                    '</div>';
                openDropdown();
                return;
            }

            var html = '';
            results.forEach(function (r) {
                var title = r.title ? escapeHtml(r.title) : 'Untitled';
                var dest  = escapeHtml(r.destination_url || '').substring(0, 60);
                var short = escapeHtml(r.short_code);

                html +=
                    '<a href="' + baseURL + '/analytics?id=' + r.id + '" style="' +
                    'display: flex; padding: 12px 16px; text-decoration: none; color: inherit; ' +
                    'border-bottom: 1px solid #1F2228;">' +
                    '<div style="flex: 1; min-width: 0;">' +
                        '<div style="font-family: monospace; color: #8B8DF8; font-weight: 600; font-size: 13px;">/' + short + '</div>' +
                        '<div style="font-size: 12px; color: #F5F5F5; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + title + '</div>' +
                        '<div style="font-size: 11px; color: #6B7280; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + dest + '</div>' +
                    '</div>' +
                    '<div style="text-align: right; margin-left: 12px; flex-shrink: 0;">' +
                        '<div style="font-family: monospace; font-size: 12px; color: #9CA3AF;">' + Number(r.clicks || 0).toLocaleString() + '</div>' +
                        '<div style="font-size: 10px; color: #6B7280;">clicks</div>' +
                    '</div>' +
                    '</a>';
            });

            html +=
                '<a href="' + baseURL + '/search?q=' + encodeURIComponent(q) + '" style="' +
                'display: block; padding: 12px 16px; text-align: center; font-size: 12px; ' +
                'color: #8B8DF8; text-decoration: none; font-weight: 600; background: #10131A;' +
                '">See all results →</a>';

            dropdown.innerHTML = html;
            openDropdown();
        }

        // Keyboard shortcut: `/` focuses search
        document.addEventListener('keydown', function (e) {
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            var isTyping = tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable;

            if (e.key === '/' && !isTyping) {
                e.preventDefault();
                input.focus();
                input.select();
            }
            if (e.key === 'Escape') {
                closeDropdown();
                input.blur();
            }
        });

        input.addEventListener('input', function () {
            var q = input.value.trim();
            console.log('[LinkForge Search] Typed:', q);

            if (q === lastQuery) return;
            lastQuery = q;

            clearTimeout(debounceTimer);

            if (q.length < 2) {
                closeDropdown();
                return;
            }

            debounceTimer = setTimeout(function () { fetchResults(q); }, 180);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && input.value.trim().length >= 2) {
                e.preventDefault();
                window.location.href = baseURL + '/search?q=' + encodeURIComponent(input.value.trim());
            }
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && e.target !== input) {
                closeDropdown();
            }
        });

        window.addEventListener('scroll', function () {
            if (dropdown.style.display === 'block') positionDropdown();
        }, true);

        window.addEventListener('resize', function () {
            if (dropdown.style.display === 'block') positionDropdown();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();