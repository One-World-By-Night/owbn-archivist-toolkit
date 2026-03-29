<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( empty( $embedded ) ) : ?><div class="wrap">
    <h1>Regulation Rules</h1>
<?php endif; ?>

    <?php settings_errors( 'oat_rules' ); ?>

    <?php if ( $message === 'added' ) : ?>
        <div class="notice notice-success"><p>Rule added successfully.</p></div>
    <?php elseif ( $message === 'deactivated' ) : ?>
        <div class="notice notice-success"><p>Rule deactivated.</p></div>
    <?php endif; ?>

    <!-- Add New Rule -->
    <div class="oat-add-rule-section">
        <h2>Add New Rule</h2>
        <form method="post">
            <?php wp_nonce_field( 'oat_add_rule' ); ?>
            <table class="form-table">
                <tr><th><label for="genre">Genre</label></th><td><input type="text" name="genre" id="genre" class="regular-text" required></td></tr>
                <tr><th><label for="category">Category</label></th><td><input type="text" name="category" id="category" class="regular-text" required></td></tr>
                <tr><th><label for="subcategory">Subcategory</label></th><td><input type="text" name="subcategory" id="subcategory" class="regular-text"></td></tr>
                <tr><th><label for="condition_name">Condition Name</label></th><td><input type="text" name="condition_name" id="condition_name" class="regular-text"></td></tr>
                <tr>
                    <th><label for="pc_level">PC Level</label></th>
                    <td>
                        <select name="pc_level" id="pc_level">
                            <option value="">Unregulated</option>
                            <option value="coordinator_notify">Coordinator Notify</option>
                            <option value="coordinator_approval">Coordinator Approval</option>
                            <option value="disallowed">Disallowed</option>
                            <option value="council_vote">Council Vote</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="npc_level">NPC Level</label></th>
                    <td>
                        <select name="npc_level" id="npc_level">
                            <option value="">Unregulated</option>
                            <option value="coordinator_notify">Coordinator Notify</option>
                            <option value="coordinator_approval">Coordinator Approval</option>
                            <option value="disallowed">Disallowed</option>
                            <option value="council_vote">Council Vote</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="controlling_coordinator">Controlling Coordinator</label></th>
                    <td>
                        <?php
                        $coord_list = array();
                        if ( function_exists( 'owc_get_coordinators' ) ) {
                            foreach ( owc_get_coordinators() as $co ) {
                                $co = (array) $co;
                                if ( ! empty( $co['slug'] ) ) {
                                    $coord_list[] = array(
                                        'value' => $co['slug'],
                                        'label' => $co['title'] ?? ucfirst( $co['slug'] ),
                                    );
                                }
                            }
                        }
                        ?>
                        <div class="oat-coord-picker-wrap">
                            <input type="text" id="coord_search" class="regular-text" placeholder="Search coordinators or type free text, then press Enter..." autocomplete="off" />
                            <div id="coord_selected" style="margin-top:6px;"></div>
                            <input type="hidden" name="controlling_coordinator" id="controlling_coordinator" value="" required />
                        </div>
                        <script>
                        jQuery(function($) {
                            var coordData = <?php echo wp_json_encode( $coord_list ); ?>;
                            var selected = []; // Array of { slug, label }

                            // Build slug → label lookup.
                            var labelMap = {};
                            for (var i = 0; i < coordData.length; i++) {
                                labelMap[coordData[i].value] = coordData[i].label;
                            }

                            function renderTags() {
                                var html = '';
                                var slugs = [];
                                for (var i = 0; i < selected.length; i++) {
                                    var label = selected[i].label || selected[i].slug;
                                    slugs.push(selected[i].slug);
                                    html += '<span class="oat-rule-tag" style="display:inline-block;margin:2px 4px 2px 0;padding:4px 8px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:3px;">'
                                        + '<span>' + $('<span>').text(label).html() + '</span>'
                                        + ' <span class="oat-remove-coord" data-idx="' + i + '" style="cursor:pointer;color:#d63638;font-weight:bold;margin-left:4px;">&times;</span>'
                                        + '</span>';
                                }
                                $('#coord_selected').html(html);
                                $('#controlling_coordinator').val(slugs.join(', '));
                            }

                            function addTag(slug) {
                                slug = $.trim(slug);
                                if (!slug) return;
                                for (var i = 0; i < selected.length; i++) {
                                    if (selected[i].slug === slug) return; // Already added.
                                }
                                selected.push({ slug: slug, label: labelMap[slug] || slug });
                                renderTags();
                            }

                            $('#coord_search').autocomplete({
                                source: function(request, response) {
                                    var term = request.term.toLowerCase();
                                    var matches = [];
                                    for (var i = 0; i < coordData.length; i++) {
                                        if (coordData[i].label.toLowerCase().indexOf(term) !== -1 || coordData[i].value.toLowerCase().indexOf(term) !== -1) {
                                            matches.push({ label: coordData[i].label, value: coordData[i].value });
                                        }
                                    }
                                    response(matches);
                                },
                                minLength: 1,
                                select: function(event, ui) {
                                    event.preventDefault();
                                    $(this).val('');
                                    addTag(ui.item.value);
                                }
                            });

                            // Enter key — only add if it matches a coordinator from the list.
                            $('#coord_search').on('keydown', function(e) {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    var val = $.trim($(this).val()).toLowerCase();
                                    for (var i = 0; i < coordData.length; i++) {
                                        if (coordData[i].value.toLowerCase() === val || coordData[i].label.toLowerCase() === val) {
                                            addTag(coordData[i].value);
                                            $(this).val('');
                                            return;
                                        }
                                    }
                                    // No match — don't add.
                                }
                            });

                            // Remove tag.
                            $(document).on('click', '.oat-remove-coord', function() {
                                selected.splice(parseInt($(this).data('idx')), 1);
                                renderTags();
                            });
                        });
                        </script>
                    </td>
                </tr>
                <tr><th><label for="elevation">Elevation</label></th><td><label><input type="checkbox" name="elevation" id="elevation" value="1"> Elevated (blocks BBP auto-approve)</label></td></tr>
            </table>
            <?php submit_button( 'Add Rule', 'primary', 'oat_add_rule' ); ?>
        </form>
    </div>

    <!-- CSV Import -->
    <div class="oat-csv-import-section">
        <h2>CSV Import (append only)</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'oat_import_csv' ); ?>
            <input type="file" name="csv_file" accept=".csv" required>
            <?php submit_button( 'Import CSV', 'secondary', 'oat_import_csv' ); ?>
        </form>
    </div>

    <!-- CSV Sync -->
    <div class="oat-csv-sync-section">
        <h2>CSV Sync (smart upsert)</h2>
        <p class="description">Matches by section_ref, then by content. Unchanged rules keep their IDs. Changed rules get immutable updates. Missing rules are deactivated.</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'oat_sync_csv' ); ?>
            <input type="file" name="csv_file" accept=".csv" required>
            <?php submit_button( 'Sync CSV', 'secondary', 'oat_sync_csv' ); ?>
        </form>
    </div>

    <!-- CSV Export -->
    <div class="oat-csv-export-section" style="margin-bottom:20px;">
        <h2>CSV Export</h2>
        <p class="description">Download all active regulation rules as CSV.</p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-rules&export_csv=1' ) ); ?>" class="button button-secondary">Download CSV</a>
    </div>

    <!-- Rules List -->
    <h2>Rules</h2>
    <form method="get">
        <?php if ( ! empty( $embedded ) ) : ?>
            <input type="hidden" name="page" value="oat-settings">
            <input type="hidden" name="tab" value="rules">
        <?php else : ?>
            <input type="hidden" name="page" value="oat-rules">
        <?php endif; ?>
        <?php $list_table->search_box( 'Search', 'oat-rules-search' ); ?>
        <?php $list_table->display(); ?>
    </form>
<?php if ( empty( $embedded ) ) : ?></div><?php endif; ?>
