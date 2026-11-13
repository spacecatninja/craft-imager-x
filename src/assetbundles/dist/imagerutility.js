(function($) {
    // On Craft 6 the utility is mounted by the Vue CP after the document is ready,
    // so wait for Craft.whenReady when it exists.
    function whenReady(cb) {
        if (window.Craft && typeof Craft.whenReady === 'function') {
            Craft.whenReady(cb);
        } else {
            $(cb);
        }
    }

    var ICONS = { success: 'circle-check', danger: 'triangle-exclamation', info: 'circle-info' };

    function escapeHtml(str) {
        return $('<div>').text(String(str)).html();
    }

    function showStatus($status, variant, html) {
        var callout = document.createElement('craft-callout');
        callout.setAttribute('variant', variant);
        callout.setAttribute('appearance', 'plain');
        callout.setAttribute('icon', ICONS[variant] || ICONS.info);
        callout.className = 'p-0';
        callout.innerHTML = html;
        $status.empty().append(callout);
    }

    function errorsHtml(data) {
        var errors = data && Array.isArray(data.errors) ? data.errors : [];
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

    function init() {
        var $utility = $('.imager-utility');

        if (!$utility.length || $utility.data('imager-x-initialized')) {
            return;
        }

        $utility.data('imager-x-initialized', true);

        var isPosting = false;

        // Cache
        var $cacheForm = $('#imager-x-utility-cache');
        var $cacheBtns = $cacheForm.find('[data-cache-clear-btn]');
        var $cacheStatus = $cacheForm.find('[data-imager-x-cache-status]');
        var $cacheTypeInput = $cacheForm.find('input[name="cacheClearType"]');

        // Generate
        var $generateForm = $('#imager-x-utility-generate');
        var $generateBtn = $generateForm.find('[data-imager-x-generate-btn]');
        var $generateStatus = $generateForm.find('[data-imager-x-generate-status]');
        var $transformsBlock = $('#imager-x-utility-generate-transforms');
        var useConfiguredSwitch = $generateForm.find('craft-switch').get(0);

        function setBusy($buttons, $activeBtn, busy) {
            $buttons.each(function() {
                this.disabled = busy && this !== $activeBtn.get(0);
            });
            $activeBtn.each(function() {
                this.loading = busy;
            });
        }

        function updateInfo(info) {
            if (!Array.isArray(info)) {
                return;
            }

            info.forEach(function(el) {
                $cacheForm.find('[data-cache-file-count="' + el.handle + '"]').text(el.fileCount);
                $cacheForm.find('[data-cache-file-size="' + el.handle + '"]').text(el.size);
            });
        }

        function clearCache(type) {
            if (isPosting) {
                return;
            }

            isPosting = true;
            $cacheTypeInput.val(type);

            var $activeBtn = $cacheBtns.filter('[data-cache-clear-btn="' + type + '"]');
            setBusy($cacheBtns, $activeBtn, true);
            $cacheStatus.empty();

            Craft.sendActionRequest('POST', $cacheForm.data('action-url'), { data: $cacheForm.serialize() })
                .then(function(response) {
                    var data = response.data;

                    if (response.status === 200 && data.success) {
                        showStatus($cacheStatus, 'success', 'Cache cleared successfully!');
                    } else {
                        showStatus($cacheStatus, 'danger', errorsHtml(data));
                    }

                    updateInfo(data.cacheInfo);
                })
                .catch(function(error) {
                    console.error(error);
                    showStatus($cacheStatus, 'danger', errorsHtml(error && error.response ? error.response.data : null));
                })
                .finally(function() {
                    setBusy($cacheBtns, $activeBtn, false);
                    isPosting = false;
                });
        }

        function createGenerateJobs() {
            if (isPosting) {
                return;
            }

            isPosting = true;
            setBusy($generateBtn, $generateBtn, true);
            $generateStatus.empty();

            // Read the switch state directly rather than trusting its hidden input
            var data = $generateForm.serializeArray().filter(function(field) {
                return field.name !== 'useConfiguredTransforms';
            });
            data.push({ name: 'useConfiguredTransforms', value: isSwitchOn(useConfiguredSwitch) ? '1' : '' });

            Craft.sendActionRequest('POST', $generateForm.data('action-url'), { data: $.param(data) })
                .then(function(response) {
                    var data = response.data;

                    if (response.status === 200 && data.success) {
                        var queueUrl = $generateForm.data('queue-url');
                        var html = 'Generate transforms jobs created.';

                        if (queueUrl) {
                            html += ' <a href="' + escapeHtml(queueUrl) + '">View the queue</a>.';
                        }

                        showStatus($generateStatus, 'success', html);
                    } else {
                        showStatus($generateStatus, 'danger', errorsHtml(data));
                    }
                })
                .catch(function(error) {
                    console.error(error);
                    showStatus($generateStatus, 'danger', errorsHtml(error && error.response ? error.response.data : null));
                })
                .finally(function() {
                    setBusy($generateBtn, $generateBtn, false);
                    isPosting = false;
                });
        }

        function syncTransformsBlock() {
            $transformsBlock.prop('hidden', isSwitchOn(useConfiguredSwitch));
        }

        $cacheBtns.on('click', function(e) {
            e.preventDefault();
            var type = $(e.currentTarget).data('cache-clear-btn');

            if (type !== undefined) {
                clearCache(type);
            }
        });

        $cacheForm.on('submit', function(e) {
            e.preventDefault();
        });

        $generateForm.on('submit', function(e) {
            e.preventDefault();
            createGenerateJobs();
        });

        if (useConfiguredSwitch) {
            $(useConfiguredSwitch).on('change', syncTransformsBlock);
            syncTransformsBlock();
        }
    }

    whenReady(init);
})(jQuery);
