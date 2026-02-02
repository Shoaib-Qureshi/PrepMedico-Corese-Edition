/**
 * WooCommerce Edition Management - Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // =====================================================
        // COURSE SELECTION HANDLER (Two-Column Layout)
        // =====================================================

        // Course selection - click on course item in sidebar
        $('.wcem-course-item').on('click', function(e) {
            e.preventDefault();
            var courseSlug = $(this).data('course');
            selectCourse(courseSlug);
        });

        // Function to select and display a course's settings
        function selectCourse(courseSlug) {
            // Update active class on course items
            $('.wcem-course-item').removeClass('active');
            $('.wcem-course-item[data-course="' + courseSlug + '"]').addClass('active');

            // Show/hide course settings panels
            $('.wcem-course-settings-panel').hide();
            $('.wcem-course-settings-panel[data-course="' + courseSlug + '"]').show();

            // Update URL hash for bookmarking
            if (history.pushState) {
                history.pushState(null, null, '#' + courseSlug);
            }
        }

        // Initialize from URL hash or select first course
        function initializeCourseSelection() {
            var hash = window.location.hash.substring(1);
            if (hash && $('.wcem-course-item[data-course="' + hash + '"]').length) {
                selectCourse(hash);
            }
        }

        // Initialize on page load
        initializeCourseSelection();

        // Handle back/forward browser navigation
        $(window).on('hashchange', function() {
            initializeCourseSelection();
        });

        // =====================================================
        // TOGGLE HANDLERS FOR NEW LAYOUT
        // =====================================================

        // Early Bird toggle
        $('.wcem-early-bird-toggle').on('change', function() {
            var course = $(this).data('course');
            var $fields = $('.wcem-early-bird-fields[data-course="' + course + '"]');
            if ($(this).is(':checked')) {
                $fields.slideDown(200);
            } else {
                $fields.slideUp(200);
            }
        });

        // Next Edition toggle
        $('.wcem-next-edition-toggle').on('change', function() {
            var course = $(this).data('course');
            var $fields = $('.wcem-next-edition-fields[data-course="' + course + '"]');
            if ($(this).is(':checked')) {
                $fields.slideDown(200);
            } else {
                $fields.slideUp(200);
            }
        });

        // Next Edition Early Bird toggle
        $('.wcem-next-early-bird-toggle').on('change', function() {
            var course = $(this).data('course');
            var $fields = $('.wcem-next-early-bird-fields[data-course="' + course + '"]');
            if ($(this).is(':checked')) {
                $fields.slideDown(200);
            } else {
                $fields.slideUp(200);
            }
        });

        // =====================================================
        // EXISTING HANDLERS
        // =====================================================

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

        // Visual feedback for course items in sidebar based on status
        // Status is already shown via badges in the new layout

    });

})(jQuery);
