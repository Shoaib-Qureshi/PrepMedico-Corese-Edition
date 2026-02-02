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

        $('input[type="date"]').on('change', function() {
            var fieldName = $(this).attr('name');
            if (fieldName.includes('edition_end')) {
                var prefix = fieldName.replace('edition_end', '');
                var $start = $('input[name="' + prefix + 'edition_start"]');
                if ($start.val() && $(this).val()) {
                    var s = new Date($start.val());
                    var e = new Date($(this).val());
                    if (e <= s) {
                        alert('End date must be after start date');
                        $(this).val('');
                    }
                }
            }
            if (fieldName.includes('early_bird_end')) {
                var prefix2 = fieldName.replace('early_bird_end', '');
                var $end = $('input[name="' + prefix2 + 'edition_end"]');
                if ($end.val() && $(this).val()) {
                    var eb = new Date($(this).val());
                    var ed = new Date($end.val());
                    if (eb > ed) {
                        alert('Early Bird end date should be before edition end date');
                    }
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
