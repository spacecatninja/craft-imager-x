$(document).ready(function () {
    var isPosting = false;
    var $form = $('#imager-x-generate-utility');
    var $btn = $('#imager-x-generate-utility [data-imager-x-btn]');
    var $spinner = $('#imager-x-generate-utility [data-imager-x-generate-spinner]');
    var $status = $('#imager-x-generate-utility [data-imager-x-generate-status]');
    var $useConfiguredToggle = $('#imager-x-generate-utility .lightswitch');
    var $useConfiguredInput = $('#imager-x-generate-utility .lightswitch input');
    var $transformsBlock = $('#imager-x-generate-utility-transforms');


    function updateErrorHolder() {
        $status.addClass('success');
        $status.text('Database updated just now!');
    }

    function createJobs(url) {
        if (!isPosting && url !== '') {
            isPosting = true;

            $btn.addClass('disabled');
            $spinner.removeClass('invisible');
            $status.text('Creating transform jobs...');
            
            var jqxhr = $.post($form.data('action-url'), $form.serialize())
                .done(function (result) {
                    console.log(result);
                    if (result && result.success) {
                        if ($form.data('queue-url') !== '') {
                            $status.html('Generate transforms jobs created. You can <a href="' + $form.data('queue-url') + '">view the queue here</a>.');
                        } else {
                            $status.html('Generate transforms jobs created.');
                        }
                        $spinner.addClass('invisible');
                        $btn.removeClass('disabled');
                    } else {
                        $status.html('An error occurred:<br>' + result.errors.join('<br>'));
                    }
                }).fail(function () {
                    $status.html('An error occurred, check you logs!');
                }).always(function () {
                    $spinner.addClass('invisible');
                    $btn.removeClass('disabled');
                    isPosting = false;
                });
        }
    }
    
    function onToggleUseConfigured() {
        var useConfigured = $useConfiguredInput.val() === '1';
        $transformsBlock.css({ display: useConfigured ? 'none' : 'block' });
    }

    $form.on('submit', function (e) {
        e.preventDefault();
        createJobs();
    });
    
    $useConfiguredToggle.on('click', function (e) {
        onToggleUseConfigured();
    });
});
