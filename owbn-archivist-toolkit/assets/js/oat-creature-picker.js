/**
 * Creature Type Picker — cascading Genre > Faction > Type > Variant selects.
 *
 * Looks for elements with data-creature-picker attribute.
 * Loads taxonomy data once via AJAX, then cascades locally.
 *
 * Expected markup (rendered by PHP):
 *   <div data-creature-picker data-genre-field="creature_genre" data-faction-field="creature_sub_type"
 *        data-type-field="creature_type" data-variant-field="creature_variant">
 *     <select name="creature_genre"></select>
 *     <select name="creature_sub_type"></select>
 *     <select name="creature_type"></select>
 *     <select name="creature_variant"></select>
 *   </div>
 */
(function($) {
	'use strict';

	var taxonomyData = null;
	var loading = false;
	var callbacks = [];

	function loadTaxonomy(cb) {
		if (taxonomyData) { cb(taxonomyData); return; }
		callbacks.push(cb);
		if (loading) return;
		loading = true;

		$.post(ajaxurl, {
			action: 'oat_creature_taxonomy_picker',
			_ajax_nonce: oatCreaturePicker.nonce
		}, function(resp) {
			if (resp.success) {
				taxonomyData = resp.data;
			} else {
				taxonomyData = { genres: [], factions: {}, types: {}, variants: {} };
			}
			loading = false;
			callbacks.forEach(function(fn) { fn(taxonomyData); });
			callbacks = [];
		});
	}

	function populateSelect($sel, options, currentVal, placeholder) {
		$sel.empty().append('<option value="">' + (placeholder || '— Select —') + '</option>');
		(options || []).forEach(function(opt) {
			$sel.append($('<option>').val(opt).text(opt).prop('selected', opt === currentVal));
		});
		$sel.prop('disabled', !options || options.length === 0);
	}

	function initPicker($container) {
		var $genre   = $container.find('select[name="creature_genre"], select[name$="[creature_genre]"]');
		var $faction = $container.find('select[name="creature_sub_type"], select[name$="[creature_sub_type]"]');
		var $type    = $container.find('select[name="creature_type"], select[name$="[creature_type]"]');
		var $variant = $container.find('select[name="creature_variant"], select[name$="[creature_variant]"]');

		if (!$genre.length) return;

		// Store initial values (for edit forms).
		var initGenre   = $genre.data('value') || $genre.val() || '';
		var initFaction = $faction.data('value') || $faction.val() || '';
		var initType    = $type.data('value') || $type.val() || '';
		var initVariant = $variant.data('value') || $variant.val() || '';

		loadTaxonomy(function(data) {
			// Genre.
			populateSelect($genre, data.genres, initGenre, '— Genre —');

			function updateFactions() {
				var g = $genre.val();
				var opts = g ? (data.factions[g] || []) : [];
				populateSelect($faction, opts, initFaction, '— Faction —');
				initFaction = ''; // Only use initial value once.
				updateTypes();
			}

			function updateTypes() {
				var g = $genre.val();
				var f = $faction.val();
				var key = g + '|' + f;
				var opts = (g && f) ? (data.types[key] || []) : [];
				populateSelect($type, opts, initType, '— Type —');
				initType = '';
				updateVariants();
			}

			function updateVariants() {
				var g = $genre.val();
				var f = $faction.val();
				var t = $type.val();
				var key = g + '|' + f + '|' + t;
				var opts = (g && f && t) ? (data.variants[key] || []) : [];
				if (opts.length > 0) {
					populateSelect($variant, opts, initVariant, '— Variant —');
					$variant.closest('.oat-creature-variant-wrap').show();
				} else {
					$variant.empty().val('').prop('disabled', true);
					$variant.closest('.oat-creature-variant-wrap').hide();
				}
				initVariant = '';
			}

			$genre.on('change', function() { initFaction = ''; initType = ''; initVariant = ''; updateFactions(); });
			$faction.on('change', function() { initType = ''; initVariant = ''; updateTypes(); });
			$type.on('change', function() { initVariant = ''; updateVariants(); });

			// Initial cascade.
			updateFactions();
		});
	}

	// Auto-init on ready.
	$(function() {
		$('[data-creature-picker]').each(function() {
			initPicker($(this));
		});
	});

	// Re-init on AJAX field load (for dynamically loaded forms).
	$(document).on('oat-fields-loaded', function() {
		$('[data-creature-picker]').each(function() {
			if (!$(this).data('picker-init')) {
				$(this).data('picker-init', true);
				initPicker($(this));
			}
		});
	});

})(jQuery);
