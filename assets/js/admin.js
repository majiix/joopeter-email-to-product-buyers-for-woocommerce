jQuery(document).ready(function($) {
    'use strict';

    var countdownTimer = null;
    var sendTimerActive = false;
    var batchSendingActive = false;
    var failedEmailsList = [];
    var isRetryActive = false;

    // Warning before leaving the page during active sending
    $(window).on('beforeunload', function(e) {
        if ( sendTimerActive || batchSendingActive ) {
            var message = 'An email dispatch is currently in progress. If you leave this page, some emails may not be sent.';
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });

    /* ==========================================================================
       Interactive Counter Animations (Delight)
       ========================================================================== */
    function animateStatsCounts() {
        $('.joopeterewb-stat-info h3').each(function() {
            var $this = $(this);
            var countTo = parseInt($this.text(), 10);
            if (isNaN(countTo) || countTo === 0) {
                return;
            }
            $({ countNum: 0 }).animate({
                countNum: countTo
            }, {
                duration: 1000,
                easing: 'swing',
                step: function() {
                    $this.text(Math.floor(this.countNum));
                },
                complete: function() {
                    $this.text(this.countNum);
                }
            });
        });
    }
    animateStatsCounts();

    /* ==========================================================================
       Role Permissions Card Styling
       ========================================================================== */
    // Initialize active role card states
    $('.joopeterewb-role-checkbox-label').each(function() {
        var $label = $(this);
        if ($label.find('input[type="checkbox"]').is(':checked')) {
            $label.addClass('active-card');
        }
    });

    // Handle role checkbox active card changes
    $('.joopeterewb-role-checkbox-label input[type="checkbox"]').on('change', function() {
        var $label = $(this).closest('.joopeterewb-role-checkbox-label');
        if (this.checked) {
            $label.addClass('active-card');
        } else {
            $label.removeClass('active-card');
        }
    });

    /* ==========================================================================
       Keyboard Accessibility (Escape to close modal)
       ========================================================================== */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            $('#joopeterewb-preview-modal').hide();
        }
    });

    /* ==========================================================================
       Product Edit Meta Box Logic
       ========================================================================== */

    // Helper to retrieve selected statuses from active chips
    function getSelectedStatuses() {
        var statuses = [];
        $('.joopeterewb-chip.active').each(function() {
            statuses.push($(this).data('status'));
        });
        return statuses;
    }

    // Handle Email Sending from Product Edit Sidebar
    $('#joopeterewb_send_email_btn').on('click', function(e) {
        e.preventDefault();

        var $btn = $(this);

        if ( sendTimerActive ) {
            // Cancel clicked!
            clearInterval(countdownTimer);
            sendTimerActive = false;
            
            // If cancel was clicked during a retry, restore the retry button state
            if ( isRetryActive ) {
                isRetryActive = false;
                if ( failedEmailsList.length > 0 ) {
                    $('#joopeterewb_retry_failed_meta_btn').show();
                }
            }
            
            $btn.removeClass('joopeterewb-countdown-active button-link-delete').addClass('button-primary').text('Send Email');
            $('#joopeterewb_email_subject').prop('disabled', false);
            if ( typeof tinyMCE !== 'undefined' && tinyMCE.get('joopeterewb_email_message') ) {
                tinyMCE.get('joopeterewb_email_message').setMode('design');
            }
            $('.joopeterewb-chip').css('pointer-events', 'auto');
            $('#joopeterewb_refresh_count_btn').prop('disabled', false);
            
            var $feedback = $('#joopeterewb-email-feedback');
            $feedback.show().removeClass('notice-warning notice-success notice-error').addClass('notice notice-info').html('<p>Dispatch paused. No emails were sent.</p>');
            return;
        }

        // Ensure TinyMCE content is synced to the textarea.
        if ( typeof tinyMCE !== 'undefined' ) {
            tinyMCE.triggerSave();
        }

        var subject   = $('#joopeterewb_email_subject').val();
        var message   = $('#joopeterewb_email_message').val();
        var post_id   = joopeterewb_params.product_id;
        var nonce     = joopeterewb_params.nonce;
        var $spinner  = $('#joopeterewb_email_spinner');
        var $feedback = $('#joopeterewb-email-feedback');
        var statuses  = getSelectedStatuses();

        if ( ! subject || ! message ) {
            alert( joopeterewb_params.strings.empty_fields );
            isRetryActive = false;
            if ( failedEmailsList.length > 0 ) {
                $('#joopeterewb_retry_failed_meta_btn').show();
            }
            return;
        }

        if ( statuses.length === 0 ) {
            alert('Please select at least one order status.');
            isRetryActive = false;
            if ( failedEmailsList.length > 0 ) {
                $('#joopeterewb_retry_failed_meta_btn').show();
            }
            return;
        }

        // If not retrying (new send), reset failed logs list and hide button
        if ( ! isRetryActive ) {
            failedEmailsList = [];
            $('#joopeterewb_retry_failed_meta_btn').hide();
        }

        var bypassConfirm = isRetryActive;
        if ( ! bypassConfirm && ! confirm( joopeterewb_params.strings.confirm_send ) ) {
            return;
        }

        // Start 10 seconds countdown
        sendTimerActive = true;
        var countdown = 10;

        $btn.addClass('joopeterewb-countdown-active button-link-delete').removeClass('button-primary').text('Cancel Send (' + countdown + 's)');
        $('#joopeterewb_email_subject').prop('disabled', true);
        if ( typeof tinyMCE !== 'undefined' && tinyMCE.get('joopeterewb_email_message') ) {
            tinyMCE.get('joopeterewb_email_message').setMode('readonly');
        }
        $('.joopeterewb-chip').css('pointer-events', 'none');
        $('#joopeterewb_refresh_count_btn').prop('disabled', true);
        
        // Render counting info and linear progress bar
        $feedback.show().removeClass('notice-error notice-success notice-info').addClass('notice notice-warning').html(
            '<p>Preparing dispatch... Launching in <strong id="joopeterewb-countdown-seconds">10</strong> seconds. Click "Cancel Send" to abort.</p>' +
            '<div class="joopeterewb-progress-bar-container" style="display: block;">' +
            '  <div class="joopeterewb-progress-bar" style="width: 100%;"></div>' +
            '</div>'
        );

        // Fire smooth CSS transition down to 0% width over the 10 seconds
        setTimeout(function() {
            $feedback.find('.joopeterewb-progress-bar').css({
                'transition': 'width 10s linear',
                'width': '0%'
            });
        }, 50);

        countdownTimer = setInterval(function() {
            countdown--;
            if (countdown > 0) {
                $btn.text('Cancel Send (' + countdown + 's)');
                $('#joopeterewb-countdown-seconds').text(countdown);
            } else {
                clearInterval(countdownTimer);
                sendTimerActive = false;
                batchSendingActive = true;

                // Restore button state for active send
                $btn.removeClass('joopeterewb-countdown-active button-link-delete').addClass('button-primary').prop('disabled', true).text('Sending...');
                $spinner.addClass('is-active');
                $feedback.hide().removeClass('notice-warning');

                var totalSuccessCount = 0;
                var totalFailedCount = 0;
                
                var currentEmailsList = [];
                var usingRetryList = isRetryActive;
                if ( usingRetryList ) {
                    currentEmailsList = failedEmailsList.slice();
                    failedEmailsList = [];
                }

                // Recursive function to send in batches
                function sendBatch(offset) {
                    $feedback.show().removeClass('notice-error notice-success notice-warning').addClass('notice notice-info').html('<p>Sending in progress...</p>');

                    var ajaxData = {
                        action: 'joopeterewb_send_product_email',
                        product_id: post_id,
                        subject: subject,
                        message: message,
                        security: nonce,
                        statuses: statuses,
                        offset: offset,
                        limit: 10
                    };

                    if ( usingRetryList ) {
                        ajaxData.emails = currentEmailsList;
                    }

                    $.ajax({
                        url: joopeterewb_params.ajaxurl,
                        type: 'POST',
                        data: ajaxData,
                        success: function(response) {
                            if ( response.success ) {
                                totalSuccessCount += response.data.sent;
                                totalFailedCount += response.data.failed;

                                // Accumulate failed emails returned from batch
                                if ( response.data.failed_emails && response.data.failed_emails.length > 0 ) {
                                    failedEmailsList = failedEmailsList.concat( response.data.failed_emails );
                                }

                                if ( ! response.data.done ) {
                                    // Update feedback with progress
                                    $feedback.html('<p>' + response.data.message + '</p>');
                                    // Proceed to next batch
                                    sendBatch( response.data.next_offset );
                                } else {
                                    // Finished sending all batches
                                    $spinner.removeClass('is-active');
                                    $btn.prop('disabled', false).text('Send Email');
                                    $('#joopeterewb_email_subject').prop('disabled', false);
                                    if ( typeof tinyMCE !== 'undefined' && tinyMCE.get('joopeterewb_email_message') ) {
                                        tinyMCE.get('joopeterewb_email_message').setMode('design');
                                    }
                                    $('.joopeterewb-chip').css('pointer-events', 'auto');
                                    $('#joopeterewb_refresh_count_btn').prop('disabled', false);
                                    
                                    batchSendingActive = false;
                                    isRetryActive = false;

                                    // Display final summary with checkmark draw success celebration (Option C)
                                    $feedback.removeClass('notice-info notice-error').addClass('notice notice-success').html(
                                        '<div style="text-align: center; padding: 12px 0;">' +
                                        '  <svg class="joopeterewb-checkmark-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">' +
                                        '    <circle class="joopeterewb-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>' +
                                        '    <path class="joopeterewb-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>' +
                                        '  </svg>' +
                                        '  <strong style="font-size: 14px; display: block; margin-top: 10px; color: var(--joopeterewb-text);">' + response.data.message + '</strong>' +
                                        '  <p style="margin: 6px 0 0; font-size: 12px; color: var(--joopeterewb-text-secondary);">' +
                                        '    Success: ' + totalSuccessCount + ' | Failed: ' + totalFailedCount +
                                        '  </p>' +
                                        '</div>'
                                    );

                                    // Toggle retry button if there are failures
                                    if ( failedEmailsList.length > 0 ) {
                                        $('#joopeterewb_retry_failed_meta_btn').show();
                                    } else {
                                        $('#joopeterewb_retry_failed_meta_btn').hide();
                                    }
                                }
                            } else {
                                $spinner.removeClass('is-active');
                                $btn.prop('disabled', false).text('Send Email');
                                $('#joopeterewb_email_subject').prop('disabled', false);
                                if ( typeof tinyMCE !== 'undefined' && tinyMCE.get('joopeterewb_email_message') ) {
                                    tinyMCE.get('joopeterewb_email_message').setMode('design');
                                }
                                $('.joopeterewb-chip').css('pointer-events', 'auto');
                                $('#joopeterewb_refresh_count_btn').prop('disabled', false);
                                
                                batchSendingActive = false;
                                isRetryActive = false;

                                $feedback.removeClass('notice-info').addClass('notice notice-error').html('<p>' + ( response.data || 'Error occurred' ) + '</p>');
                            }
                        },
                        error: function() {
                            $spinner.removeClass('is-active');
                            $btn.prop('disabled', false).text('Send Email');
                            $('#joopeterewb_email_subject').prop('disabled', false);
                            if ( typeof tinyMCE !== 'undefined' && tinyMCE.get('joopeterewb_email_message') ) {
                                tinyMCE.get('joopeterewb_email_message').setMode('design');
                            }
                            $('.joopeterewb-chip').css('pointer-events', 'auto');
                            $('#joopeterewb_refresh_count_btn').prop('disabled', false);
                            
                            batchSendingActive = false;
                            isRetryActive = false;

                            $feedback.removeClass('notice-info').addClass('notice notice-error').html('<p>' + joopeterewb_params.strings.server_error + '</p>');
                        }
                    });
                }

                // Start batch sending from offset 0
                sendBatch(0);
            }
        }, 1000);
    });

    // Handle Click on meta box Retry Failed sends button
    $('#joopeterewb_retry_failed_meta_btn').on('click', function(e) {
        e.preventDefault();

        if ( failedEmailsList.length === 0 ) {
            return;
        }

        if ( ! confirm( 'Are you sure you want to retry sending emails to the failed recipients?' ) ) {
            return;
        }

        isRetryActive = true;
        $(this).hide();

        // Trigger countdown/sending flow on the main button
        $('#joopeterewb_send_email_btn').trigger('click');
    });

    // Helper to trigger customer count refresh
    function triggerRefreshCount(isManualClick) {
        var $btn    = $('#joopeterewb_refresh_count_btn');
        var $icon   = $btn.find('.dashicons');
        var post_id = joopeterewb_params.product_id;
        var nonce   = joopeterewb_params.nonce;
        var statuses = getSelectedStatuses();

        $btn.prop('disabled', true);
        $icon.addClass('joopeterewb-spin');

        $.ajax({
            url: joopeterewb_params.ajaxurl,
            type: 'POST',
            data: {
                action: 'joopeterewb_refresh_customer_count',
                product_id: post_id,
                security: nonce,
                statuses: statuses
            },
            success: function(response) {
                $btn.prop('disabled', false);
                $icon.removeClass('joopeterewb-spin');

                if ( response.success ) {
                    $('#joopeterewb-count-value').text( response.data.count );
                    $('#joopeterewb-count-label').text( response.data.label );
                } else {
                    if (isManualClick) {
                        alert( response.data || 'Error refreshing count' );
                    }
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $icon.removeClass('joopeterewb-spin');
                if (isManualClick) {
                    alert( joopeterewb_params.strings.server_error );
                }
            }
        });
    }

    // Refresh manually when button is clicked
    $('#joopeterewb_refresh_count_btn').on('click', function(e) {
        e.preventDefault();
        triggerRefreshCount(true);
    });

    // Chip click toggle and auto-refresh count
    $('.joopeterewb-chip').on('click', function() {
        $(this).toggleClass('active');
        triggerRefreshCount(false);
    });


    /* ==========================================================================
       Email Logs Dashboard Logic
       ========================================================================== */

    var $logRows = $('.joopeterewb-log-row');

    // Filter and search logic
    function filterLogs() {
        var searchText = $('#joopeterewb_log_search').val().toLowerCase();
        var statusFilter = $('#joopeterewb_log_status_filter').val();

        $logRows.each(function() {
            var $row = $(this);
            var rowStatus = $row.data('status');
            var rowSearch = $row.data('search');

            var matchesSearch = rowSearch.indexOf(searchText) !== -1;
            var matchesStatus = (statusFilter === 'all' || rowStatus === statusFilter);

            if ( matchesSearch && matchesStatus ) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }

    // Bind filters
    $('#joopeterewb_log_search').on('input', filterLogs);
    $('#joopeterewb_log_status_filter').on('change', filterLogs);

    // Modal message viewer
    $logRows.on('click', '.joopeterewb-view-msg-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var index = $btn.data('index');
        var $row = $btn.closest('.joopeterewb-log-row');

        // Extract metadata from row
        var recipient = $row.find('.column-recipient').text().trim();
        var subject = $row.find('.column-subject').text().trim();
        var messageHTML = $('#joopeterewb-msg-' + index).html();

        // Inject content into modal
        $('#joopeterewb-modal-recipient').text(recipient);
        $('#joopeterewb-modal-subject').text(subject);
        $('#joopeterewb-modal-message-body').html(messageHTML);

        // Open modal
        $('#joopeterewb-preview-modal').css('display', 'flex');
    });

    // Close modal handlers
    $('.joopeterewb-modal-close').on('click', function() {
        $('#joopeterewb-preview-modal').hide();
    });

    // Close when clicking outside of modal content
    $(window).on('click', function(e) {
        if ($(e.target).is('#joopeterewb-preview-modal')) {
            $('#joopeterewb-preview-modal').hide();
        }
    });

    // Clear Logs AJAX action
    $('#joopeterewb_clear_logs_btn').on('click', function(e) {
        e.preventDefault();

        if ( ! confirm( joopeterewb_params.strings.confirm_clear ) ) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: joopeterewb_params.ajaxurl,
            type: 'POST',
            data: {
                action: 'joopeterewb_clear_email_logs',
                security: joopeterewb_params.nonce
            },
            success: function(response) {
                if ( response.success ) {
                    // Refresh the page to update stats and table clean state
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                    alert( response.data || 'Error clearing logs' );
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert( joopeterewb_params.strings.server_error );
            }
        });
    });

    // Clear Success Logs AJAX action
    $('#joopeterewb_clear_success_btn').on('click', function(e) {
        e.preventDefault();

        if ( ! confirm( joopeterewb_params.strings.confirm_clear_success ) ) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: joopeterewb_params.ajaxurl,
            type: 'POST',
            data: {
                action: 'joopeterewb_clear_success_logs',
                security: joopeterewb_params.nonce
            },
            success: function(response) {
                if ( response.success ) {
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                    alert( response.data || 'Error clearing success logs' );
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert( joopeterewb_params.strings.server_error );
            }
        });
    });

    // Retry Failed Emails AJAX action
    $('#joopeterewb_retry_failed_btn').on('click', function(e) {
        e.preventDefault();

        if ( ! confirm( joopeterewb_params.strings.confirm_retry ) ) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: joopeterewb_params.ajaxurl,
            type: 'POST',
            data: {
                action: 'joopeterewb_retry_failed_emails',
                security: joopeterewb_params.nonce
            },
            success: function(response) {
                if ( response.success ) {
                    alert( response.data );
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                    alert( response.data || 'Error retrying failed emails' );
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert( joopeterewb_params.strings.server_error );
            }
        });
    });

    // Dashboard tabs toggling (with smooth slide/fade interactions)
    $('.joopeterewb-tab-nav-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var targetTab = $btn.data('tab');

        // Toggle active buttons
        $('.joopeterewb-tab-nav-btn').removeClass('active');
        $btn.addClass('active');

        // Hide all panels instantly to avoid clashing callbacks, then fade in the target panel
        $('.joopeterewb-tab-panel').hide();
        $('#joopeterewb-tab-' + targetTab).stop().fadeIn(200);
    });

    // Save settings form AJAX action
    $('#joopeterewb-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#joopeterewb_save_settings_btn');
        var $spinner = $('#joopeterewb_settings_spinner');
        var $feedback = $('#joopeterewb-settings-feedback');

        var selectedRoles = [];
        $('input[name="allowed_roles[]"]:checked').each(function() {
            selectedRoles.push($(this).val());
        });

        // Always ensure administrator is active
        if (selectedRoles.indexOf('administrator') === -1) {
            selectedRoles.push('administrator');
        }

        var logExpiryDays = $('#joopeterewb_log_expiry_days').val();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $feedback.hide().removeClass('notice-error notice-success');

        $.ajax({
            url: joopeterewb_params.ajaxurl,
            type: 'POST',
            data: {
                action: 'joopeterewb_save_settings',
                security: joopeterewb_params.nonce,
                roles: selectedRoles,
                log_expiry_days: logExpiryDays
            },
            success: function(response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $feedback.show();

                if ( response.success ) {
                    $feedback.addClass('notice notice-success').html('<p>' + joopeterewb_params.strings.settings_saved + '</p>');
                    // Fade out feedback after 3 seconds
                    setTimeout(function() {
                        $feedback.fadeOut('slow');
                    }, 3000);
                } else {
                    $feedback.addClass('notice notice-error').html('<p>' + ( response.data || 'Error saving settings' ) + '</p>');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $feedback.show().addClass('notice notice-error').html('<p>' + joopeterewb_params.strings.server_error + '</p>');
            }
        });
    });
});
