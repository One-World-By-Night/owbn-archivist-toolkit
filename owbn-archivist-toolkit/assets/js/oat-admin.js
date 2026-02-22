(function($) {
    'use strict';

    // Domain selector → reload page with domain param to render fields.
    $('#oat_domain').on('change', function() {
        var domain = $(this).val();
        if (domain) {
            window.location.href = window.location.pathname + '?page=oat-submit&domain=' + encodeURIComponent(domain);
        }
    });

    // AJAX action handler (for inline actions from inbox or entry detail).
    $(document).on('click', '.oat-ajax-action', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var entryId = $btn.data('entry-id');
        var actionType = $btn.data('action');
        var note = $btn.closest('.oat-action-card').find('textarea[name="oat_note"]').val() || '';

        if (!note && actionType !== 'bump') {
            alert('A note is required for this action.');
            return;
        }

        $btn.prop('disabled', true).text('Processing...');

        $.post(oat_ajax.url, {
            action: 'oat_process_action',
            nonce: oat_ajax.nonce,
            entry_id: entryId,
            action_type: actionType,
            note: note
        }, function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
                $btn.prop('disabled', false).text($btn.data('label'));
            }
        }).fail(function() {
            alert('Request failed.');
            $btn.prop('disabled', false).text($btn.data('label'));
        });
    });

    // Conditional fields: show/hide <tr> rows based on another field value.
    $('tr[data-condition-field]').each(function() {
        var $row = $(this);
        var condField = $row.data('condition-field');
        var condValue = String($row.data('condition-value'));

        $('[name="oat_meta_' + condField + '"]').on('change', function() {
            if ($(this).val() === condValue) {
                $row.show();
            } else {
                $row.hide().find(':input').val('');
            }
        }).trigger('change');
    });

})(jQuery);
