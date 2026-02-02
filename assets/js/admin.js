/**
 * WooCommerce Edition Management - Admin JavaScript
 */
(function($) {
    'use strict';

    // State management for Edition Management page
    var currentCourse = null;

    $(document).ready(function() {

        // =====================================================
        // COURSE SELECTION (Edition Management Page)
        // =====================================================

        // Initialize course selection on page load
        initializeCourseSelection();

        // Course item click handler
        $('.wcem-course-item').on('click', function(e) {
            e.preventDefault();
            selectCourse($(this).data('course'));
        });

        // Early Bird toggle (new modern layout)
        $(document).on('change', '.wcem-early-bird-toggle', function() {
            var $section = $(this).closest('.wcem-course-settings');
            var $fields = $section.find('.wcem-early-bird-fields');
            var $infoBanner = $section.find('.wcem-info-banner');

            if ($(this).is(':checked')) {
                $fields.slideDown(200);
                $infoBanner.slideDown(200);
            } else {
                $fields.slideUp(200);
                $infoBanner.slideUp(200);
            }
        });

        // Next Edition toggle (new modern layout)
        $(document).on('change', '.wcem-next-edition-toggle', function() {
            var $section = $(this).closest('.wcem-course-settings');
            var $fields = $section.find('.wcem-next-edition-fields');

            if ($(this).is(':checked')) {
                $fields.slideDown(200);
            } else {
                $fields.slideUp(200);
            }
        });

        // Next Edition Early Bird toggle
        $(document).on('change', '.wcem-next-early-bird-toggle', function() {
            var $section = $(this).closest('.wcem-next-eb-subsection');
            var $fields = $section.find('.wcem-next-early-bird-fields');

            if ($(this).is(':checked')) {
                $fields.slideDown(200);
            } else {
                $fields.slideUp(200);
            }
        });

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

        // Highlight course cards with warnings (no dates set) - Legacy support
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

    /**
     * Initialize course selection on page load
     */
    function initializeCourseSelection() {
        // Only run on Edition Management page with new layout
        if ($('.wcem-modern-layout').length === 0) {
            return;
        }

        // Get course from URL hash or select first
        var hash = window.location.hash.replace('#', '');
        var $firstCourse = $('.wcem-course-item').first();

        if (hash && $('.wcem-course-item[data-course="' + hash + '"]').length) {
            selectCourse(hash);
        } else if ($firstCourse.length) {
            selectCourse($firstCourse.data('course'));
        }
    }

    /**
     * Select and display a course's settings
     */
    function selectCourse(courseSlug) {
        if (currentCourse === courseSlug) {
            return;
        }

        currentCourse = courseSlug;

        // Update course list selection
        $('.wcem-course-item').removeClass('active');
        $('.wcem-course-item[data-course="' + courseSlug + '"]').addClass('active');

        // Update settings panel visibility
        $('.wcem-course-settings').hide();
        var $activeSettings = $('.wcem-course-settings[data-course="' + courseSlug + '"]');
        $activeSettings.fadeIn(200);

        // Update panel header
        var courseName = $activeSettings.data('name');
        var status = $activeSettings.data('status');
        var statusLabel = $activeSettings.data('status-label');

        $('.wcem-panel-title').text(courseName + ' Settings');
        $('.wcem-panel-status-badge')
            .removeClass('wcem-status-active wcem-status-needs-dates wcem-status-ending-soon wcem-status-early-bird wcem-status-expired')
            .addClass('wcem-status-' + status)
            .text(statusLabel);

        // Update URL hash for bookmarking
        if (history.replaceState) {
            history.replaceState(null, null, '#' + courseSlug);
        }

        // Trigger custom event for extensibility
        $(document).trigger('wcem:courseSelected', [courseSlug]);
    }

    // Expose functions globally if needed
    window.wcemSelectCourse = selectCourse;
    window.wcemInitCourseSelection = initializeCourseSelection;

})(jQuery);
