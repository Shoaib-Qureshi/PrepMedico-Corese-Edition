/**
 * WooCommerce Edition Management - Admin JavaScript
 */
(function($) {
    'use strict';

    $(function() {
        // Course selection
        function selectCourse(slug) {
            $('.wcem-course-item').removeClass('active');
            $('.wcem-course-item[data-course="' + slug + '"]').addClass('active');

            $('.wcem-course-settings-panel').hide();
            var $panel = $('.wcem-course-settings-panel[data-course="' + slug + '"]');
            $panel.show();

            // Update dynamic shortcode examples
            $('.wcem-dynamic-course').text(slug);

            if (history.replaceState) {
                history.replaceState(null, null, '#' + slug);
            }
        }

        function initCourse() {
            if ($('.wcem-modern-layout').length === 0) {
                return;
            }
            var hash = window.location.hash.replace('#', '');
            if (hash && $('.wcem-course-item[data-course="' + hash + '"]').length) {
                selectCourse(hash);
            } else {
                var $first = $('.wcem-course-item').first();
                if ($first.length) {
                    selectCourse($first.data('course'));
                }
            }
        }

        $(document).on('click', '.wcem-course-item', function(e) {
            e.preventDefault();
            selectCourse($(this).data('course'));
        });

        // ============================================
        // FORM SUBMISSION - Simple and reliable
        // ============================================
        $('#wcem-edition-form').on('submit', function(e) {
            // Clear previous validation errors
            $('.wcem-field-error').removeClass('wcem-field-error');
            $('.wcem-validation-error').remove();

            // Only validate the currently VISIBLE panel
            var hasErrors = false;
            var $visiblePanel = $('.wcem-course-settings-panel:visible');

            if ($visiblePanel.length && !validatePanelDates($visiblePanel)) {
                hasErrors = true;
            }

            if (hasErrors) {
                e.preventDefault();
                alert('Please fix the date validation errors before saving.');
                return false;
            }

            // Allow form to submit normally
            return true;
        });

        // ============================================
        // DATE VALIDATION FUNCTIONS
        // ============================================
        function validatePanelDates($panel) {
            var isValid = true;
            var courseName = $panel.find('.wcem-settings-course').text() || 'Unknown';

            // Get current edition fields
            var $startField = $panel.find('input[name$="edition_start"]').not('[name*="next_"]');
            var $endField = $panel.find('input[name$="edition_end"]').not('[name*="next_"]');
            var $ebStartField = $panel.find('input[name$="early_bird_start"]').not('[name*="next_"]');
            var $ebEndField = $panel.find('input[name$="early_bird_end"]').not('[name*="next_"]');
            var ebEnabled = $panel.find('.wcem-early-bird-toggle').is(':checked');

            var courseStart = $startField.val() ? new Date($startField.val() + 'T00:00:00') : null;
            var courseEnd = $endField.val() ? new Date($endField.val() + 'T00:00:00') : null;
            var ebStart = $ebStartField.val() ? new Date($ebStartField.val() + 'T00:00:00') : null;
            var ebEnd = $ebEndField.val() ? new Date($ebEndField.val() + 'T00:00:00') : null;

            console.log('Validating:', courseName, {
                courseStart: $startField.val(),
                courseEnd: $endField.val(),
                ebEnabled: ebEnabled,
                ebStart: $ebStartField.val(),
                ebEnd: $ebEndField.val()
            });

            // Validate course end > start
            if (courseStart && courseEnd && courseEnd <= courseStart) {
                console.log('Error: Course end must be after start');
                markFieldError($endField, 'End date must be after start date');
                isValid = false;
            }

            // Validate early bird dates if enabled AND has values
            if (ebEnabled && $ebEndField.val() && $ebStartField.val()) {
                // Early bird end must be after early bird start
                if (ebStart && ebEnd && ebEnd < ebStart) {
                    console.log('Error: Early bird end must be after start');
                    markFieldError($ebEndField, 'Early bird end must be after start');
                    isValid = false;
                }
            }

            // Early bird end must be on or before course start (only if both have values)
            if (ebEnabled && $ebEndField.val() && $startField.val()) {
                if (ebEnd && courseStart && ebEnd > courseStart) {
                    console.log('Error: Early bird must end before course starts', ebEnd, '>', courseStart);
                    markFieldError($ebEndField, 'Early bird must end before course starts');
                    isValid = false;
                }
            }

            // Validate next edition if enabled
            var nextEnabled = $panel.find('.wcem-next-edition-toggle').is(':checked');
            if (nextEnabled) {
                var $nextStartField = $panel.find('input[name$="next_start"]');
                var $nextEndField = $panel.find('input[name$="next_end"]');
                var $nextEbStartField = $panel.find('input[name$="next_early_bird_start"]');
                var $nextEbEndField = $panel.find('input[name$="next_early_bird_end"]');
                var nextEbEnabled = $panel.find('.wcem-next-early-bird-toggle').is(':checked');

                var nextStart = $nextStartField.val() ? new Date($nextStartField.val() + 'T00:00:00') : null;
                var nextEnd = $nextEndField.val() ? new Date($nextEndField.val() + 'T00:00:00') : null;
                var nextEbStart = $nextEbStartField.val() ? new Date($nextEbStartField.val() + 'T00:00:00') : null;
                var nextEbEnd = $nextEbEndField.val() ? new Date($nextEbEndField.val() + 'T00:00:00') : null;

                console.log('Next Edition:', {
                    nextEnabled: nextEnabled,
                    nextStart: $nextStartField.val(),
                    nextEnd: $nextEndField.val(),
                    nextEbEnabled: nextEbEnabled,
                    nextEbStart: $nextEbStartField.val(),
                    nextEbEnd: $nextEbEndField.val()
                });

                // Next edition end > start (only if both have values)
                if ($nextStartField.val() && $nextEndField.val() && nextStart && nextEnd && nextEnd <= nextStart) {
                    console.log('Error: Next edition end must be after start');
                    markFieldError($nextEndField, 'End date must be after start date');
                    isValid = false;
                }

                // Next edition must start after current edition ends (only if both have values)
                if ($endField.val() && $nextStartField.val() && courseEnd && nextStart && nextStart < courseEnd) {
                    console.log('Error: Next edition must start after current edition ends');
                    markFieldError($nextStartField, 'Must start after current edition ends');
                    isValid = false;
                }

                // Next early bird validation (only if enabled and has values)
                if (nextEbEnabled && $nextEbStartField.val() && $nextEbEndField.val()) {
                    if (nextEbStart && nextEbEnd && nextEbEnd < nextEbStart) {
                        console.log('Error: Next early bird end must be after start');
                        markFieldError($nextEbEndField, 'Early bird end must be after start');
                        isValid = false;
                    }
                }

                if (nextEbEnabled && $nextEbEndField.val() && $nextStartField.val()) {
                    if (nextEbEnd && nextStart && nextEbEnd > nextStart) {
                        console.log('Error: Next early bird must end before course starts');
                        markFieldError($nextEbEndField, 'Early bird must end before course starts');
                        isValid = false;
                    }
                }
            }

            console.log('Panel validation result:', isValid);
            return isValid;
        }

        function markFieldError($field, message) {
            $field.addClass('wcem-field-error');
            var $wrapper = $field.closest('.wcem-field');
            if ($wrapper.find('.wcem-validation-error').length === 0) {
                $wrapper.append('<span class="wcem-validation-error">' + message + '</span>');
            }
        }

        function clearFieldError($field) {
            $field.removeClass('wcem-field-error');
            $field.closest('.wcem-field').find('.wcem-validation-error').remove();
        }

        // ============================================
        // REAL-TIME DATE VALIDATION
        // ============================================
        $(document).on('change', 'input[type="date"]', function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            var $panel = $field.closest('.wcem-course-settings-panel');

            if (!$panel.length) return;

            // Clear error on this field
            clearFieldError($field);

            var isNextSlot = fieldName && fieldName.indexOf('next_') !== -1;

            if (isNextSlot) {
                validateNextEditionDates($panel, fieldName);
            } else {
                validateCurrentEditionDates($panel, fieldName);
            }
        });

        function validateCurrentEditionDates($panel, fieldName) {
            var $startField = $panel.find('input[name$="edition_start"]').not('[name*="next_"]');
            var $endField = $panel.find('input[name$="edition_end"]').not('[name*="next_"]');
            var $ebStartField = $panel.find('input[name$="early_bird_start"]').not('[name*="next_"]');
            var $ebEndField = $panel.find('input[name$="early_bird_end"]').not('[name*="next_"]');

            var courseStart = $startField.val() ? new Date($startField.val() + 'T00:00:00') : null;
            var courseEnd = $endField.val() ? new Date($endField.val() + 'T00:00:00') : null;
            var ebStart = $ebStartField.val() ? new Date($ebStartField.val() + 'T00:00:00') : null;
            var ebEnd = $ebEndField.val() ? new Date($ebEndField.val() + 'T00:00:00') : null;

            // Check edition end > start
            if (fieldName && fieldName.indexOf('edition_end') !== -1 && courseStart && courseEnd && courseEnd <= courseStart) {
                markFieldError($endField, 'End date must be after start date');
            }

            // Check early bird end > start
            if (fieldName && fieldName.indexOf('early_bird_end') !== -1 && ebStart && ebEnd && ebEnd < ebStart) {
                markFieldError($ebEndField, 'Early bird end must be after start');
            }

            // Check early bird end <= course start
            if (fieldName && fieldName.indexOf('early_bird_end') !== -1 && courseStart && ebEnd && ebEnd > courseStart) {
                markFieldError($ebEndField, 'Early bird must end before course starts');
            }
        }

        function validateNextEditionDates($panel, fieldName) {
            var $currentEndField = $panel.find('input[name$="edition_end"]').not('[name*="next_"]');
            var $nextStartField = $panel.find('input[name$="next_start"]');
            var $nextEndField = $panel.find('input[name$="next_end"]');
            var $nextEbStartField = $panel.find('input[name$="next_early_bird_start"]');
            var $nextEbEndField = $panel.find('input[name$="next_early_bird_end"]');

            var currentEnd = $currentEndField.val() ? new Date($currentEndField.val() + 'T00:00:00') : null;
            var nextStart = $nextStartField.val() ? new Date($nextStartField.val() + 'T00:00:00') : null;
            var nextEnd = $nextEndField.val() ? new Date($nextEndField.val() + 'T00:00:00') : null;
            var nextEbStart = $nextEbStartField.val() ? new Date($nextEbStartField.val() + 'T00:00:00') : null;
            var nextEbEnd = $nextEbEndField.val() ? new Date($nextEbEndField.val() + 'T00:00:00') : null;

            // Check next end > start
            if (fieldName && fieldName.indexOf('next_end') !== -1 && nextStart && nextEnd && nextEnd <= nextStart) {
                markFieldError($nextEndField, 'End date must be after start date');
            }

            // Check next start > current end
            if (fieldName && fieldName.indexOf('next_start') !== -1 && currentEnd && nextStart && nextStart < currentEnd) {
                markFieldError($nextStartField, 'Must start after current edition ends');
            }

            // Check next early bird end > start
            if (fieldName && fieldName.indexOf('next_early_bird_end') !== -1 && nextEbStart && nextEbEnd && nextEbEnd < nextEbStart) {
                markFieldError($nextEbEndField, 'Early bird end must be after start');
            }

            // Check next early bird end <= next start
            if (fieldName && fieldName.indexOf('next_early_bird_end') !== -1 && nextStart && nextEbEnd && nextEbEnd > nextStart) {
                markFieldError($nextEbEndField, 'Early bird must end before course starts');
            }
        }

        // ============================================
        // TOGGLE HANDLERS
        // ============================================
        $(document).on('change', '.wcem-early-bird-toggle', function() {
            var $panel = $(this).closest('.wcem-course-settings-panel');
            var $fields = $panel.find('.wcem-early-bird-fields');
            if (this.checked) {
                $fields.slideDown(150);
            } else {
                $fields.slideUp(150);
            }
        });

        $(document).on('change', '.wcem-next-edition-toggle', function() {
            var $panel = $(this).closest('.wcem-course-settings-panel');
            var $fields = $panel.find('.wcem-next-edition-fields');
            if (this.checked) {
                $fields.slideDown(150);
            } else {
                $fields.slideUp(150);
            }
        });

        $(document).on('change', '.wcem-next-early-bird-toggle', function() {
            var $wrap = $(this).closest('.wcem-next-eb-subsection');
            var $fields = $wrap.find('.wcem-next-early-bird-fields');
            if (this.checked) {
                $fields.slideDown(150);
            } else {
                $fields.slideUp(150);
            }
        });

        // ============================================
        // AJAX HANDLERS
        // ============================================
        $(document).on('click', '.wcem-manual-increment', function(e) {
            e.preventDefault();
            var $button = $(this);
            var course = $button.data('course');

            if (typeof wcemAdmin === 'undefined') {
                alert('Error: Admin scripts not loaded properly.');
                return;
            }

            if (!confirm(wcemAdmin.strings.confirmSwitch)) {
                return;
            }

            $button.prop('disabled', true).text(wcemAdmin.strings.switching);

            $.post(wcemAdmin.ajaxUrl, {
                action: 'wcem_manual_edition_switch',
                nonce: wcemAdmin.nonce,
                course: course
            }).done(function(response) {
                if (response.success) {
                    alert(response.data.message || wcemAdmin.strings.success);
                    location.reload();
                } else {
                    alert(response.data && response.data.message ? response.data.message : wcemAdmin.strings.error);
                    $button.prop('disabled', false).text('Increment Edition (+1)');
                }
            }).fail(function() {
                alert(wcemAdmin.strings.error);
                $button.prop('disabled', false).text('Increment Edition (+1)');
            });
        });

        $(document).on('click', '#wcem-test-fluentcrm', function(e) {
            e.preventDefault();
            var $button = $(this).prop('disabled', true).text('Testing...');
            $.post(wcemAdmin.ajaxUrl, {
                action: 'wcem_test_fluentcrm',
                nonce: wcemAdmin.nonce
            }).done(function(response) {
                if (response.success) {
                    alert(response.data.message || 'Connection successful');
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Connection test failed');
                }
            }).fail(function() {
                alert('Connection test failed');
            }).always(function() {
                $button.prop('disabled', false).text('Test Connection');
            });
        });

        $(document).on('click', '#wcem-run-cron', function(e) {
            e.preventDefault();
            var $button = $(this).prop('disabled', true).text('Running...');
            $.post(wcemAdmin.ajaxUrl, {
                action: 'wcem_run_cron',
                nonce: wcemAdmin.nonce
            }).done(function(response) {
                if (response.success) {
                    alert(response.data.message || 'Edition check completed');
                    location.reload();
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Edition check failed');
                }
            }).fail(function() {
                alert('Edition check failed');
            }).always(function() {
                $button.prop('disabled', false).text('Run Edition Check Now');
            });
        });

        // ============================================
        // NUMBER INPUT VALIDATION
        // ============================================
        $('input[type="number"]').on('change', function() {
            var v = parseInt($(this).val(), 10);
            if (isNaN(v) || v < 1) {
                $(this).val(1);
            }
        });

        // ============================================
        // INITIALIZATION
        // ============================================
        initCourse();
        $(window).on('hashchange', initCourse);

        // Debug: Log if form exists
        console.log('WCEM Admin JS loaded. Form found:', $('#wcem-edition-form').length > 0);
    });

})(jQuery);
