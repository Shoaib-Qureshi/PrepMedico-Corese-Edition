/**
 * WooCommerce Edition Management - Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Manual Edition Increment
        $('.wcem-manual-increment').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var course = $button.data('course');

            if (!confirm(wcemAdmin.strings.confirmSwitch)) {
                return;
            }

            $button.prop('disabled', true).text(wcemAdmin.strings.switching);

            $.ajax({
                url: wcemAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcem_manual_edition_switch',
                    nonce: wcemAdmin.nonce,
                    course: course
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(wcemAdmin.strings.error + '\n' + response.data.message);
                        $button.prop('disabled', false).text('Increment Edition (+1)');
                    }
                },
                error: function() {
                    alert(wcemAdmin.strings.error);
                    $button.prop('disabled', false).text('Increment Edition (+1)');
                }
            });
        });

        // Test FluentCRM Connection
        $('#wcem-test-fluentcrm').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).text('Testing...');

            $.ajax({
                url: wcemAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcem_test_fluentcrm',
                    nonce: wcemAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message;
                        if (response.data.missing && response.data.missing.length > 0) {
                            alert('⚠️ ' + message);
                        } else {
                            alert('✅ ' + message);
                        }
                    } else {
                        alert('❌ Error: ' + response.data.message);
                    }
                    $button.prop('disabled', false).text('Test FluentCRM Connection');
                },
                error: function() {
                    alert('❌ Connection test failed');
                    $button.prop('disabled', false).text('Test FluentCRM Connection');
                }
            });
        });

        // Run Cron Manually
        $('#wcem-run-cron').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).text('Running...');

            $.ajax({
                url: wcemAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcem_run_cron',
                    nonce: wcemAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ ' + response.data.message);
                    }
                    $button.prop('disabled', false).text('Run Edition Check Now');
                },
                error: function() {
                    alert('❌ Cron run failed');
                    $button.prop('disabled', false).text('Run Edition Check Now');
                }
            });
        });

        // Date validation
        $('input[type="date"]').on('change', function() {
            var $this = $(this);
            var fieldName = $this.attr('name');

            // Validate date ranges
            if (fieldName.includes('edition_end')) {
                var prefix = fieldName.replace('edition_end', '');
                var $startField = $('input[name="' + prefix + 'edition_start"]');

                if ($startField.val() && $this.val()) {
                    var startDate = new Date($startField.val());
                    var endDate = new Date($this.val());

                    if (endDate <= startDate) {
                        alert('End date must be after start date');
                        $this.val('');
                    }
                }
            }

            // Validate early bird end is before edition end
            if (fieldName.includes('early_bird_end')) {
                var prefix = fieldName.replace('early_bird_end', '');
                var $editionEndField = $('input[name="' + prefix + 'edition_end"]');

                if ($editionEndField.val() && $this.val()) {
                    var editionEndDate = new Date($editionEndField.val());
                    var earlyBirdEndDate = new Date($this.val());

                    if (earlyBirdEndDate > editionEndDate) {
                        alert('Early Bird end date should be before edition end date');
                    }
                }
            }
        });

        // Edition number validation
        $('input[type="number"]').on('change', function() {
            var $this = $(this);
            var value = parseInt($this.val());

            if (value < 1) {
                $this.val(1);
            }
        });

        // Highlight course cards with warnings (no dates set)
        $('.wcem-course-card').each(function() {
            var $card = $(this);
            var $startDate = $card.find('input[name*="edition_start"]');
            var $endDate = $card.find('input[name*="edition_end"]');

            if (!$startDate.val() || !$endDate.val()) {
                $card.css('border-color', '#dc3545');
                $card.find('h3').append(' <span style="color: #dc3545; font-size: 12px;">⚠️ Set dates!</span>');
            } else {
                var endDate = new Date($endDate.val());
                var today = new Date();
                var daysUntilEnd = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));

                if (daysUntilEnd <= 7 && daysUntilEnd > 0) {
                    $card.css('border-color', '#ffc107');
                    $card.find('h3').append(' <span style="color: #856404; font-size: 12px;">⚠️ Ending soon!</span>');
                }
            }
        });

    });

})(jQuery);
