/**
 * OAT Domain drag-and-drop reordering.
 *
 * Uses jQuery UI Sortable on the domains WP_List_Table tbody.
 */
(function ($) {
    'use strict';

    $(function () {
        var $tbody = $('.wp-list-table tbody');

        if (!$tbody.length) {
            return;
        }

        $tbody.sortable({
            handle: '.oat-drag-handle',
            axis: 'y',
            cursor: 'grabbing',
            placeholder: 'oat-sortable-placeholder',
            opacity: 0.7,
            update: function () {
                var order = [];
                $tbody.find('tr[data-id]').each(function () {
                    order.push($(this).data('id'));
                });

                // Update visible sort_order column values.
                $tbody.find('tr[data-id]').each(function (i) {
                    $(this).find('.column-sort_order').text(i);
                });

                $.post(oatDomainSort.ajaxUrl, {
                    action: 'oat_save_domain_order',
                    _ajax_nonce: oatDomainSort.nonce,
                    order: order
                });
            }
        });
    });
})(jQuery);
