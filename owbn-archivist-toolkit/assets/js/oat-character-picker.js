/**
 * OAT Character Picker — Select2 AJAX autocomplete.
 *
 * Initializes all [data-oat-character-picker] elements as Select2 widgets
 * that search characters via the oat_character_search AJAX endpoint.
 *
 * Respects the filter_by attribute: when the referenced field changes,
 * the picker clears and re-scopes future searches.
 *
 * Globals expected (via wp_localize_script):
 *   oatCharacterPicker.ajaxUrl   - admin-ajax.php URL
 *   oatCharacterPicker.nonce     - oat_character_search nonce
 */
(function ($) {
    'use strict';

    if (typeof $.fn.select2 === 'undefined') {
        return;
    }

    function initPicker($el) {
        var filterBy = $el.data('filter-by') || '';
        var $hidden  = $el.closest('.oat-character-picker-wrap').find('input[type="hidden"]');

        $el.select2({
            placeholder: (window.oatCharacterPicker && oatCharacterPicker.i18n && oatCharacterPicker.i18n.searchPlaceholder) || 'Type to search characters...',
            minimumInputLength: 2,
            allowClear: true,
            width: '100%',
            ajax: {
                url: oatCharacterPicker.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    var role = 'player';
                    if (filterBy) {
                        var $roleField = $('[name="' + filterBy + '"]');
                        if ($roleField.length) {
                            role = $roleField.val() || 'player';
                        }
                    }
                    return {
                        action: 'oat_character_search',
                        _ajax_nonce: oatCharacterPicker.nonce,
                        term: params.term,
                        submitter_role: role
                    };
                },
                processResults: function (response) {
                    if (!response.success) {
                        return { results: [] };
                    }
                    return {
                        results: $.map(response.data, function (item) {
                            var label = item.text;
                            if (item.meta && item.meta.player_name) {
                                label += ' (' + item.meta.player_name;
                                if (item.meta.chronicle_slug) {
                                    label += ' — ' + item.meta.chronicle_slug;
                                }
                                label += ')';
                            }
                            return {
                                id: item.id,
                                text: label,
                                meta: item.meta
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // When selection changes, update the hidden input with the character ID.
        $el.on('select2:select', function (e) {
            $hidden.val(e.params.data.id);
        });
        $el.on('select2:clear', function () {
            $hidden.val('');
        });

        // When the filter_by field changes, clear the picker so results re-scope.
        if (filterBy) {
            $(document).on('change', '[name="' + filterBy + '"]', function () {
                $el.val(null).trigger('change');
                $hidden.val('');
            });
        }
    }

    $(function () {
        $('[data-oat-character-picker]').each(function () {
            initPicker($(this));
        });
    });

})(jQuery);
