jQuery(document).ready(function($) {
    $('#wpbp-start-backup-button').on('click', function() {
        const $button = $(this);
        const $progressArea = $('#wpbp-progress-area');
        const $downloadLinkArea = $('#wpbp-download-link-area');
        const nonce = $('#wpbp_backup_nonce').val();

        // Clear previous messages and disable button
        $progressArea.html('Processing backup... Please wait. This might take a while.').show();
        $downloadLinkArea.empty();
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl, // WordPress AJAX URL (localized or global)
            type: 'POST',
            data: {
                action: 'wpbp_run_backup', // Matches the wp_ajax_ hook
                nonce: nonce // Security nonce from hidden field
            },
            dataType: 'json' // Expect a JSON response
        })
        .done(function(response) {
            if (response.success) {
                $progressArea.html(response.data.message || 'Backup completed successfully!');
                const $downloadLink = $('<a></a>')
                    .attr('href', response.data.download_url)
                    .attr('target', '_blank')
                    .text('Download Backup (wpbackup.zip)');
                $downloadLinkArea.append($downloadLink);
            } else {
                let errorMessage = 'An unknown error occurred.';
                if (response.data && typeof response.data === 'string') {
                    errorMessage = response.data;
                } else if (response.data && response.data.message) {
                    errorMessage = response.data.message;
                } else if (response.data && Array.isArray(response.data) && response.data[0] && response.data[0].message) {
                    // Handle cases where error might be wrapped, e.g. by wp_send_json_error structure
                    errorMessage = response.data[0].message;
                }
                $progressArea.html('Error: ' + errorMessage);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            $progressArea.html('AJAX Error: Failed to perform backup. Status: ' + textStatus + ', Error: ' + errorThrown);
            console.error("Backup AJAX failed:", textStatus, errorThrown, jqXHR.responseText);
        })
        .always(function() {
            $button.prop('disabled', false); // Re-enable button
        });
    });
});
