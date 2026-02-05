/**
 * WooCommerce Edition Management - Admin JavaScript
 */
(function($) {
    'use strict';

    $(function() {
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

        // Save button handler - ensure form submits properly
        $(document).on('click', '.wcem-save-btn', function(e) {
            var $form = $('#wcem-edition-form');
            if ($form.length) {
                // Clear any validation errors first
                $('.wcem-field-error').removeClass('wcem-field-error');
                $('.wcem-validation-error').remove();

                // Validate dates before submission
                var hasErrors = false;
                $('.wcem-course-settings-panel').each(function() {
                    var $panel = $(this);
                    if (!validatePanelDates($panel)) {
                        hasErrors = true;
                    }
                });

                if (hasErrors) {
                    e.preventDefault();
                    alert('Please fix the date validation errors before saving.');
                    return false;
                }

                // Ensure form submits
                $form.find('input[name="wcem_save_settings"]').remove();
                $form.append('<input type="hidden" name="wcem_save_settings" value="1">');
            }
        });

        // Comprehensive date validation function
        function validatePanelDates($panel) {
            var isValid = true;

            // Get current edition dates
            var startField = $panel.find('input[name$="edition_start"]').not('[name*="next_"]');
            var endField = $panel.find('input[name$="edition_end"]').not('[name*="next_"]');
            var ebStartField = $panel.find('input[name$="early_bird_start"]').not('[name*="next_"]');
            var ebEndField = $panel.find('input[name$="early_bird_end"]').not('[name*="next_"]');
            var ebEnabled = $panel.find('.wcem-early-bird-toggle').is(':checked');

            var courseStart = startField.val() ? new Date(startField.val()) : null;
            var courseEnd = endField.val() ? new Date(endField.val()) : null;
            var ebStart = ebStartField.val() ? new Date(ebStartField.val()) : null;
            var ebEnd = ebEndField.val() ? new Date(ebEndField.val()) : null;

            // Validate course end > start
            if (courseStart && courseEnd && courseEnd <= courseStart) {
                markFieldError(endField, 'End date must be after start date');
                isValid = false;
            }

            // Validate early bird dates if enabled
            if (ebEnabled) {
                // Early bird start must be before early bird end
                if (ebStart && ebEnd && ebEnd <= ebStart) {
                    markFieldError(ebEndField, 'Early bird end must be after early bird start');
                    isValid = false;
                }

                // Early bird end must be before or equal to course start (early bird is PRE-course)
                if (ebEnd && courseStart && ebEnd > courseStart) {
                    markFieldError(ebEndField, 'Early bird must end before course starts');
                    isValid = false;
                }
            }

            // Validate next edition dates if enabled
            var nextEnabled = $panel.find('.wcem-next-edition-toggle').is(':checked');
            if (nextEnabled) {
                var nextStartField = $panel.find('input[name$="next_start"]');
                var nextEndField = $panel.find('input[name$="next_end"]');
                var nextEbStartField = $panel.find('input[name$="next_early_bird_start"]');
                var nextEbEndField = $panel.find('input[name$="next_early_bird_end"]');
                var nextEbEnabled = $panel.find('.wcem-next-early-bird-toggle').is(':checked');

                var nextStart = nextStartField.val() ? new Date(nextStartField.val()) : null;
                var nextEnd = nextEndField.val() ? new Date(nextEndField.val()) : null;
                var nextEbStart = nextEbStartField.val() ? new Date(nextEbStartField.val()) : null;
                var nextEbEnd = nextEbEndField.val() ? new Date(nextEbEndField.val()) : null;

                // Next course end > start
                if (nextStart && nextEnd && nextEnd <= nextStart) {
                    markFieldError(nextEndField, 'End date must be after start date');
                    isValid = false;
                }

                // Next edition must start after current edition ends
                if (courseEnd && nextStart && nextStart < courseEnd) {
                    markFieldError(nextStartField, 'Next edition must start after current edition ends');
                    isValid = false;
                }

                // Next early bird validation
                if (nextEbEnabled) {
                    if (nextEbStart && nextEbEnd && nextEbEnd <= nextEbStart) {
                        markFieldError(nextEbEndField, 'Early bird end must be after early bird start');
                        isValid = false;
                    }

                    if (nextEbEnd && nextStart && nextEbEnd > nextStart) {
                        markFieldError(nextEbEndField, 'Early bird must end before course starts');
                        isValid = false;
                    }
                }
            }

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

        $(document).on('change', '.wcem-early-bird-toggle', function() {
            var $panel = $(this).closest('.wcem-course-settings-panel');
            var $fields = $panel.find('.wcem-early-bird-fields');
            var $info = $panel.find('.wcem-info-banner');
            if (this.checked) {
                $fields.slideDown(150);
                $info.slideDown(150);
            } else {
                $fields.slideUp(150);
                $info.slideUp(150);
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

        $(document).on('click', '.wcem-manual-increment', function(e) {
            e.preventDefault();
            var $button = $(this);
            var course = $button.data('course');

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

        // Real-time date validation on change
        $(document).on('change', 'input[type="date"]', function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            var $panel = $field.closest('.wcem-course-settings-panel');

            // Clear previous error on this field
            clearFieldError($field);

            // Determine which slot we're in (current or next)
            var isNextSlot = fieldName.includes('next_');

            if (isNextSlot) {
                // Next edition validation
                var nextStartField = $panel.find('input[name$="next_start"]');
                var nextEndField = $panel.find('input[name$="next_end"]');
                var nextEbStartField = $panel.find('input[name$="next_early_bird_start"]');
                var nextEbEndField = $panel.find('input[name$="next_early_bird_end"]');
                var currentEndField = $panel.find('input[name$="edition_end"]').not('[name*="next_"]');

                var nextStart = nextStartField.val() ? new Date(nextStartField.val()) : null;
                var nextEnd = nextEndField.val() ? new Date(nextEndField.val()) : null;
                var nextEbStart = nextEbStartField.val() ? new Date(nextEbStartField.val()) : null;
                var nextEbEnd = nextEbEndField.val() ? new Date(nextEbEndField.val()) : null;
                var currentEnd = currentEndField.val() ? new Date(currentEndField.val()) : null;

                // Validate next edition end > start
                if (fieldName.includes('next_end') && nextStart && nextEnd && nextEnd <= nextStart) {
                    markFieldError(nextEndField, 'End date must be after start date');
                }

                // Validate next edition starts after current ends
                if (fieldName.includes('next_start') && currentEnd && nextStart && nextStart < currentEnd) {
                    markFieldError(nextStartField, 'Must start after current edition ends');
                }

                // Validate next early bird end > start
                if (fieldName.includes('next_early_bird_end') && nextEbStart && nextEbEnd && nextEbEnd <= nextEbStart) {
                    markFieldError(nextEbEndField, 'Early bird end must be after start');
                }

                // Validate next early bird end <= next course start (early bird is PRE-course only)
                if (fieldName.includes('next_early_bird_end') && nextStart && nextEbEnd && nextEbEnd > nextStart) {
                    markFieldError(nextEbEndField, 'Early bird must end before course starts');
                }
            } else {
                // Current edition validation
                var startField = $panel.find('input[name$="edition_start"]').not('[name*="next_"]');
                var endField = $panel.find('input[name$="edition_end"]').not('[name*="next_"]');
                var ebStartField = $panel.find('input[name$="early_bird_start"]').not('[name*="next_"]');
                var ebEndField = $panel.find('input[name$="early_bird_end"]').not('[name*="next_"]');

                var courseStart = startField.val() ? new Date(startField.val()) : null;
                var courseEnd = endField.val() ? new Date(endField.val()) : null;
                var ebStart = ebStartField.val() ? new Date(ebStartField.val()) : null;
                var ebEnd = ebEndField.val() ? new Date(ebEndField.val()) : null;

                // Validate course end > start
                if (fieldName.includes('edition_end') && courseStart && courseEnd && courseEnd <= courseStart) {
                    markFieldError(endField, 'End date must be after start date');
                }

                // Validate early bird end > start
                if (fieldName.includes('early_bird_end') && ebStart && ebEnd && ebEnd <= ebStart) {
                    markFieldError(ebEndField, 'Early bird end must be after start');
                }

                // Validate early bird end <= course start (early bird is PRE-course only)
                if (fieldName.includes('early_bird_end') && courseStart && ebEnd && ebEnd > courseStart) {
                    markFieldError(ebEndField, 'Early bird must end before course starts');
                }
            }
        });

        $('input[type="number"]').on('change', function() {
            var v = parseInt($(this).val(), 10);
            if (isNaN(v) || v < 1) {
                $(this).val(1);
            }
        });

        initCourse();
        $(window).on('hashchange', initCourse);
    });

})(jQuery);
