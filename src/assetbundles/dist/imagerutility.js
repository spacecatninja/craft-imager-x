$(document).ready(function() {
    var isPosting = false;

    // Cache
    var $cacheForm = $('#imager-x-utility-cache');
    var $cacheBtns = $('#imager-x-utility-cache [data-cache-clear-btn]');
    var $cacheStatus = $('#imager-x-utility-cache [data-imager-x-cache-status]');
    var $cacheTypeInput = $('#imager-x-utility-cache input[name="cacheClearType"]');
    
    
    // Generate
    var $generateForm = $('#imager-x-utility-generate');
    var $generateBtn = $('#imager-x-utility-generate [data-imager-x-btn]');
    var $generateSpinner = $('#imager-x-utility-generate [data-imager-x-generate-spinner]');
    var $generateStatus = $('#imager-x-utility-generate [data-imager-x-generate-status]');
    var $useConfiguredToggle = $('#imager-x-utility-generate .lightswitch');
    var $useConfiguredInput = $('#imager-x-utility-generate .lightswitch input');
    var $transformsBlock = $('#imager-x-utility-generate-transforms');

    function createGenerateJobs() {
        if (!isPosting) {
            isPosting = true;

            $generateBtn.addClass('disabled');
            $generateSpinner.removeClass('invisible');
            $generateStatus.text('Creating transform jobs...');
            
            Craft.sendActionRequest('POST', $generateForm.data('action-url'), { data: $generateForm.serialize() })
                .then((response) => {
                    var data = response.data;

                    if (response && response.status === 200 && data.success) {
                        if ($generateForm.data('queue-url') !== '') {
                            $generateStatus.html('<strong>Generate transforms jobs created.</strong> You can <a href="' + $generateForm.data('queue-url') + '">view the queue here</a>.');
                        } else {
                            $generateStatus.html('<strong>Generate transforms jobs created.</strong>');
                        }
                    } else {
                        $generateStatus.html('An error occurred:<br>' + data.errors.join('<br>'));
                    }
                    
                    $generateSpinner.addClass('invisible');
                    $generateBtn.removeClass('disabled');
                    isPosting = false;
                })
                .catch((response) => {
                    console.error(response);
                    $generateStatus.html('An error occurred, check you logs!');
                    
                    $generateSpinner.addClass('invisible');
                    $generateBtn.removeClass('disabled');
                    isPosting = false;
                });
        }
    }

    function onToggleUseConfigured() {
        var useConfigured = $useConfiguredInput.val() === '1';
        $transformsBlock.css({ display: useConfigured ? 'none' : 'block' });
    }
    
    function clearCache() {
        if (!isPosting) {
            isPosting = true;
            var type = $cacheTypeInput.val();

            $cacheBtns.filter('[data-cache-clear-btn="' + type + '"]').addClass('disabled');
            $cacheForm.find('[data-imager-x-cache-spinner="' + type + '"]').removeClass('invisible');
            $cacheStatus.text('Cache clearing in progress');
            
            Craft.sendActionRequest('POST', $cacheForm.data('action-url'), { data: $cacheForm.serialize() })
                .then((response) => {
                    var data = response.data;

                    if (response && response.status === 200 && data.success) {
                        $cacheStatus.html('<strong>Cache cleared successfully!</strong>');
                    } else {
                        $cacheStatus.html('An error occurred:<br>' + data.errors.join('<br>'));
                    }
                    
                    updateInfo(data.cacheInfo);
                    
                    $cacheForm.find('[data-imager-x-cache-spinner]').addClass('invisible');
                    $cacheBtns.removeClass('disabled');
                    isPosting = false;
                })
                .catch((response) => {
                    console.error(response);
                    $cacheStatus.html('An error occurred, check you logs!');
                    
                    $cacheForm.find('[data-imager-x-cache-spinner]').addClass('invisible');
                    $cacheBtns.removeClass('disabled');
                    isPosting = false;
                });
        }
    }
    
    function updateInfo(info) {
        if (Array.isArray(info)) {
            info.forEach(function(el) {
                var $countElem = $cacheForm.find('[data-cache-file-count="' + el.handle + '"]');
                if ($countElem.length > 0) {
                    $countElem.text(el.fileCount);
                }
                
                var $sizeElem = $cacheForm.find('[data-cache-file-size="' + el.handle + '"]');
                if ($sizeElem.length > 0) {
                    $sizeElem.text(el.size);
                }
            });
        }
    }

    $cacheForm.on('submit', function(e) {
        e.preventDefault();
        clearCache();
    });
    
    $cacheBtns.on('click', function(e) {
        e.preventDefault();
        var type = $(e.currentTarget).data('cache-clear-btn');
        
        if (type !== undefined) {
            $cacheTypeInput.val(type);
            $cacheForm.submit();
        }
        
    });

    $generateForm.on('submit', function(e) {
        e.preventDefault();
        createGenerateJobs();
    });

    $useConfiguredToggle.on('click', function(e) {
        onToggleUseConfigured();
    });
});
