<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( empty( $embedded ) ) : ?><div class="wrap">
	<h1>Creature Type Taxonomy</h1>
<?php endif; ?>

	<?php if ( ! empty( $_GET['imported'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Taxonomy imported successfully.</p></div>
	<?php endif; ?>

	<div style="margin-bottom:15px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-taxonomy&export_json=1' ) ); ?>" class="button">Export JSON</a>
		<button type="button" class="button" id="oat-tax-import-btn">Import JSON</button>
		<button type="button" class="button button-primary" id="oat-tax-save-btn" disabled>Save Changes</button>
		<span id="oat-tax-status" style="margin-left:10px;"></span>
	</div>

	<!-- Import modal -->
	<div id="oat-tax-import-modal" style="display:none;margin-bottom:15px;padding:15px;background:#fff;border:1px solid #ccd0d4;">
		<form method="post">
			<?php wp_nonce_field( 'oat_taxonomy_import' ); ?>
			<p><strong>Paste taxonomy JSON:</strong></p>
			<textarea name="import_json" rows="10" style="width:100%;font-family:monospace;font-size:12px;"></textarea>
			<p>
				<button type="submit" class="button button-primary">Import</button>
				<button type="button" class="button" id="oat-tax-import-cancel">Cancel</button>
			</p>
		</form>
	</div>

	<div id="oat-tax-tree" style="font-size:13px;">
		<!-- Rendered by JS -->
	</div>
</div>

<style>
.oat-tax-genre, .oat-tax-faction, .oat-tax-type, .oat-tax-variant-row {
	padding: 2px 0;
}
.oat-tax-genre { margin-bottom: 8px; }
.oat-tax-genre > .oat-tax-label { font-weight: bold; font-size: 14px; cursor: pointer; }
.oat-tax-faction > .oat-tax-label { font-weight: 600; cursor: pointer; }
.oat-tax-type > .oat-tax-label { cursor: default; }
.oat-tax-children { margin-left: 24px; border-left: 1px solid #ddd; padding-left: 12px; }
.oat-tax-collapsed > .oat-tax-children { display: none; }
.oat-tax-toggle { display: inline-block; width: 16px; cursor: pointer; color: #999; user-select: none; }
.oat-tax-actions { display: inline; margin-left: 8px; }
.oat-tax-actions button { font-size: 11px; padding: 0 4px; cursor: pointer; border: none; background: none; color: #2271b1; }
.oat-tax-actions button:hover { color: #d63638; }
.oat-tax-actions button.oat-add { color: #00a32a; }
.oat-tax-actions button.oat-add:hover { color: #006b1a; }
.oat-tax-add-row { margin: 4px 0 4px 24px; }
.oat-tax-add-row input { font-size: 12px; width: 200px; }
.oat-tax-variants { display: inline; color: #666; font-size: 12px; margin-left: 8px; }
</style>

<script>
(function($) {
	var taxonomy = <?php echo wp_json_encode( $taxonomy ); ?>;
	var nonce = <?php echo wp_json_encode( $nonce ); ?>;
	var dirty = false;

	function markDirty() {
		dirty = true;
		$('#oat-tax-save-btn').prop('disabled', false).text('Save Changes *');
		$('#oat-tax-status').text('');
	}

	function render() {
		var $tree = $('#oat-tax-tree').empty();
		var genres = taxonomy.genres || {};
		var genreNames = Object.keys(genres).sort();

		genreNames.forEach(function(genre) {
			$tree.append(renderGenre(genre, genres[genre]));
		});

		// Add genre button.
		$tree.append(
			$('<div class="oat-tax-add-row">').append(
				$('<input type="text" placeholder="New genre...">').on('keydown', function(e) {
					if (e.key === 'Enter' && this.value.trim()) {
						taxonomy.genres[this.value.trim()] = { factions: {} };
						markDirty(); render();
					}
				}),
				' ',
				$('<button class="button button-small">+ Genre</button>').on('click', function() {
					var $input = $(this).prev('input');
					if ($input.val().trim()) {
						taxonomy.genres[$input.val().trim()] = { factions: {} };
						markDirty(); render();
					}
				})
			)
		);
	}

	function renderGenre(name, data) {
		var $el = $('<div class="oat-tax-genre">');
		var factions = data.factions || {};
		var factionCount = Object.keys(factions).length;
		var typeCount = 0;
		Object.values(factions).forEach(function(f) { typeCount += Object.keys(f.types || {}).length; });

		$el.append(
			$('<div>').append(
				$('<span class="oat-tax-toggle">').text('\u25BC').on('click', function() {
					$el.toggleClass('oat-tax-collapsed');
					$(this).text($el.hasClass('oat-tax-collapsed') ? '\u25B6' : '\u25BC');
				}),
				$('<span class="oat-tax-label">').text(name),
				$('<span style="color:#999;font-size:12px;margin-left:6px;">').text('(' + factionCount + ' factions, ' + typeCount + ' types)'),
				$('<span class="oat-tax-actions">').append(
					$('<button class="oat-add" title="Add faction">+ faction</button>').on('click', function() {
						var n = prompt('Faction name:');
						if (n) { factions[n] = { types: {} }; data.factions = factions; markDirty(); render(); }
					}),
					$('<button title="Rename">\u270E</button>').on('click', function() {
						var n = prompt('Rename genre:', name);
						if (n && n !== name) { taxonomy.genres[n] = taxonomy.genres[name]; delete taxonomy.genres[name]; markDirty(); render(); }
					}),
					$('<button title="Delete">\u2716</button>').on('click', function() {
						if (confirm('Delete genre "' + name + '" and all its contents?')) { delete taxonomy.genres[name]; markDirty(); render(); }
					})
				)
			)
		);

		var $children = $('<div class="oat-tax-children">');
		Object.keys(factions).sort().forEach(function(faction) {
			$children.append(renderFaction(name, faction, factions[faction], data));
		});
		$el.append($children);
		return $el;
	}

	function renderFaction(genre, name, data, genreData) {
		var $el = $('<div class="oat-tax-faction">');
		var types = data.types || {};
		var typeCount = Object.keys(types).length;

		$el.append(
			$('<div>').append(
				$('<span class="oat-tax-toggle">').text('\u25BC').on('click', function() {
					$el.toggleClass('oat-tax-collapsed');
					$(this).text($el.hasClass('oat-tax-collapsed') ? '\u25B6' : '\u25BC');
				}),
				$('<span class="oat-tax-label">').text(name || '(none)'),
				$('<span style="color:#999;font-size:12px;margin-left:6px;">').text('(' + typeCount + ' types)'),
				$('<span class="oat-tax-actions">').append(
					$('<button class="oat-add" title="Add type">+ type</button>').on('click', function() {
						var n = prompt('Type name:');
						if (n) { types[n] = { variants: [] }; data.types = types; markDirty(); render(); }
					}),
					$('<button title="Rename">\u270E</button>').on('click', function() {
						var n = prompt('Rename faction:', name);
						if (n && n !== name) { genreData.factions[n] = genreData.factions[name]; delete genreData.factions[name]; markDirty(); render(); }
					}),
					$('<button title="Delete">\u2716</button>').on('click', function() {
						if (confirm('Delete faction "' + name + '" and all its types?')) { delete genreData.factions[name]; markDirty(); render(); }
					})
				)
			)
		);

		var $children = $('<div class="oat-tax-children">');
		Object.keys(types).sort().forEach(function(type) {
			$children.append(renderType(genre, name, type, types[type], data));
		});
		$el.append($children);
		return $el;
	}

	function renderType(genre, faction, name, data, factionData) {
		var $el = $('<div class="oat-tax-type">');
		var variants = data.variants || [];

		$el.append(
			$('<div>').append(
				$('<span style="display:inline-block;width:16px;">').text('\u2022'),
				$('<span class="oat-tax-label">').text(name),
				variants.length ? $('<span class="oat-tax-variants">').text('variants: ' + variants.join(', ')) : '',
				$('<span class="oat-tax-actions">').append(
					$('<button class="oat-add" title="Edit variants">variants</button>').on('click', function() {
						var v = prompt('Variants (comma-separated):', variants.join(', '));
						if (v !== null) {
							data.variants = v.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
							markDirty(); render();
						}
					}),
					$('<button title="Rename">\u270E</button>').on('click', function() {
						var n = prompt('Rename type:', name);
						if (n && n !== name) { factionData.types[n] = factionData.types[name]; delete factionData.types[name]; markDirty(); render(); }
					}),
					$('<button title="Delete">\u2716</button>').on('click', function() {
						if (confirm('Delete type "' + name + '"?')) { delete factionData.types[name]; markDirty(); render(); }
					})
				)
			)
		);
		return $el;
	}

	// Save button.
	$('#oat-tax-save-btn').on('click', function() {
		var $btn = $(this).prop('disabled', true).text('Saving...');
		$.post(ajaxurl, {
			action: 'oat_taxonomy_save',
			nonce: nonce,
			taxonomy: JSON.stringify(taxonomy)
		}, function(resp) {
			if (resp.success) {
				dirty = false;
				$btn.text('Save Changes').prop('disabled', true);
				$('#oat-tax-status').text('Saved.').css('color', '#00a32a');
			} else {
				$btn.text('Save Changes *').prop('disabled', false);
				$('#oat-tax-status').text('Error: ' + (resp.data || 'unknown')).css('color', '#d63638');
			}
		});
	});

	// Import toggle.
	$('#oat-tax-import-btn').on('click', function() { $('#oat-tax-import-modal').toggle(); });
	$('#oat-tax-import-cancel').on('click', function() { $('#oat-tax-import-modal').hide(); });

	// Warn on leave.
	$(window).on('beforeunload', function() {
		if (dirty) return 'You have unsaved taxonomy changes.';
	});

	render();
})(jQuery);
</script>
