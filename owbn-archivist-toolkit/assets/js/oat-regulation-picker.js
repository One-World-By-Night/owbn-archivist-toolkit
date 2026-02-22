(function($) {
    'use strict';

    var selectedRules = [];

    $('#oat_rule_search').autocomplete({
        source: function(request, response) {
            $.getJSON(oat_ajax.url, {
                action: 'oat_search_rules',
                nonce: oat_ajax.nonce,
                term: request.term
            }, response);
        },
        minLength: 2,
        select: function(event, ui) {
            event.preventDefault();
            addRule(ui.item);
            $(this).val('');
        }
    });

    function addRule(rule) {
        // Prevent duplicates.
        for (var i = 0; i < selectedRules.length; i++) {
            if (selectedRules[i].id === rule.id) {
                return;
            }
        }

        selectedRules.push(rule);
        renderSelectedRules();
    }

    function removeRule(ruleId) {
        selectedRules = selectedRules.filter(function(r) {
            return r.id !== ruleId;
        });
        renderSelectedRules();
    }

    function renderSelectedRules() {
        var $container = $('#oat-selected-rules');
        $container.empty();

        // Remove old hidden inputs.
        $('input[name="oat_rule_ids[]"]').remove();

        selectedRules.forEach(function(rule) {
            var $tag = $('<span class="oat-rule-tag"></span>')
                .text(rule.label + ' ');

            var $remove = $('<span class="oat-remove-rule">&times;</span>')
                .on('click', function() { removeRule(rule.id); });

            $tag.append($remove);
            $container.append($tag);

            // Hidden input for form submission.
            $container.append(
                $('<input type="hidden" name="oat_rule_ids[]">').val(rule.id)
            );
        });

        // Auto-set coordinator genre from first rule.
        if (selectedRules.length > 0 && selectedRules[0].coordinator) {
            $('#oat_coordinator_genre').val(selectedRules[0].coordinator);
        }
    }

    // Expose for external use.
    window.oatRulePicker = {
        getSelected: function() { return selectedRules; },
        addRule: addRule,
        removeRule: removeRule
    };

})(jQuery);
