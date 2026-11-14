/**
 * Imager X utility.
 *
 * Plain JavaScript without dependencies: Craft 6 doesn't load jQuery or the legacy `Craft` JavaScript API
 * in the control panel. Craft adds this script when the utility is shown, which can be before the utility's
 * HTML is mounted, so it waits for the HTML to appear.
 */
(function() {
    'use strict';

    var ICONS = { success: 'circle-check', danger: 'triangle-exclamation', info: 'circle-info' };

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function showStatus(statusEl, variant, html) {
        var callout = document.createElement('craft-callout');
        callout.setAttribute('variant', variant);
        callout.setAttribute('appearance', 'plain');
        callout.setAttribute('icon', ICONS[variant] || ICONS.info);
        callout.className = 'p-0';
        callout.innerHTML = html;
        statusEl.replaceChildren(callout);
    }

    function errorsHtml(data) {
        var errors = data && Array.isArray(data.errors) ? data.errors : [];

        if (!errors.length && data && typeof data.message === 'string' && data.message !== '') {
            errors = [data.message];
        }

        return 'An error occurred' + (errors.length ? ': ' + errors.map(escapeHtml).join(' ') : ', check your logs!');
    }

    function isSwitchOn(el) {
        if (!el) {
            return true;
        }
        if (typeof el.checked === 'boolean') {
            return el.checked;
        }
        if (typeof el.on === 'boolean') {
            return el.on;
        }
        return el.hasAttribute('checked');
    }

    /**
     * Posts form data to an action URL, and resolves with whether it succeeded and the JSON body.
     */
    function post(url, formData) {
        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function(response) {
            return response.json()
                .catch(function() {
                    return null;
                })
                .then(function(data) {
                    return { ok: response.ok, data: data };
                });
        });
    }

    function init(utility) {
        if (utility.dataset.imagerXInitialized) {
            return;
        }

        utility.dataset.imagerXInitialized = 'true';

        var isPosting = false;

        // Cache
        var cacheForm = utility.querySelector('#imager-x-utility-cache');
        var cacheBtns = cacheForm ? Array.from(cacheForm.querySelectorAll('[data-cache-clear-btn]')) : [];
        var cacheStatus = cacheForm ? cacheForm.querySelector('[data-imager-x-cache-status]') : null;

        // Generate
        var generateForm = utility.querySelector('#imager-x-utility-generate');
        var generateBtn = generateForm ? generateForm.querySelector('[data-imager-x-generate-btn]') : null;
        var generateStatus = generateForm ? generateForm.querySelector('[data-imager-x-generate-status]') : null;
        var transformsBlock = utility.querySelector('#imager-x-utility-generate-transforms');
        var useConfiguredSwitch = generateForm ? generateForm.querySelector('craft-switch') : null;

        function setBusy(buttons, activeBtn, busy) {
            buttons.forEach(function(btn) {
                btn.disabled = busy && btn !== activeBtn;
            });

            if (activeBtn) {
                activeBtn.loading = busy;
            }
        }

        function updateInfo(info) {
            if (!Array.isArray(info)) {
                return;
            }

            info.forEach(function(el) {
                var count = cacheForm.querySelector('[data-cache-file-count="' + el.handle + '"]');
                var size = cacheForm.querySelector('[data-cache-file-size="' + el.handle + '"]');

                if (count) {
                    count.textContent = el.fileCount;
                }

                if (size) {
                    size.textContent = el.size;
                }
            });
        }

        function clearCache(type) {
            if (isPosting) {
                return;
            }

            isPosting = true;

            var activeBtn = cacheBtns.find(function(btn) {
                return btn.dataset.cacheClearBtn === type;
            }) || null;

            setBusy(cacheBtns, activeBtn, true);
            cacheStatus.replaceChildren();

            var formData = new FormData(cacheForm);
            formData.set('cacheClearType', type);

            post(cacheForm.dataset.actionUrl, formData)
                .then(function(result) {
                    var data = result.data;

                    if (result.ok && data && data.success) {
                        showStatus(cacheStatus, 'success', 'Cache cleared successfully!');
                    } else {
                        showStatus(cacheStatus, 'danger', errorsHtml(data));
                    }

                    updateInfo(data && data.cacheInfo);
                })
                .catch(function(error) {
                    console.error(error);
                    showStatus(cacheStatus, 'danger', errorsHtml(null));
                })
                .finally(function() {
                    setBusy(cacheBtns, activeBtn, false);
                    isPosting = false;
                });
        }

        function createGenerateJobs() {
            if (isPosting) {
                return;
            }

            isPosting = true;
            setBusy([generateBtn], generateBtn, true);
            generateStatus.replaceChildren();

            // Read the switch state directly rather than trusting its hidden input
            var formData = new FormData(generateForm);
            formData.set('useConfiguredTransforms', isSwitchOn(useConfiguredSwitch) ? '1' : '');

            post(generateForm.dataset.actionUrl, formData)
                .then(function(result) {
                    var data = result.data;

                    if (result.ok && data && data.success) {
                        var queueUrl = generateForm.dataset.queueUrl;
                        var html = 'Generate transforms jobs created.';

                        if (queueUrl) {
                            html += ' <a href="' + escapeHtml(queueUrl) + '">View the queue</a>.';
                        }

                        showStatus(generateStatus, 'success', html);
                    } else {
                        showStatus(generateStatus, 'danger', errorsHtml(data));
                    }
                })
                .catch(function(error) {
                    console.error(error);
                    showStatus(generateStatus, 'danger', errorsHtml(null));
                })
                .finally(function() {
                    setBusy([generateBtn], generateBtn, false);
                    isPosting = false;
                });
        }

        function syncTransformsBlock() {
            if (transformsBlock) {
                transformsBlock.hidden = isSwitchOn(useConfiguredSwitch);
            }
        }

        cacheBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                if (btn.dataset.cacheClearBtn) {
                    clearCache(btn.dataset.cacheClearBtn);
                }
            });
        });

        if (cacheForm) {
            cacheForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });
        }

        if (generateForm) {
            generateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                createGenerateJobs();
            });
        }

        if (useConfiguredSwitch) {
            useConfiguredSwitch.addEventListener('change', syncTransformsBlock);
            syncTransformsBlock();
        }
    }

    function initAll() {
        var utilities = document.querySelectorAll('.imager-utility');
        utilities.forEach(init);

        return utilities.length > 0;
    }

    if (initAll()) {
        return;
    }

    // The utility's HTML isn't mounted yet. Wait for it, but not forever.
    var observer = new MutationObserver(function() {
        if (initAll()) {
            observer.disconnect();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
    setTimeout(function() {
        observer.disconnect();
    }, 10000);
})();
