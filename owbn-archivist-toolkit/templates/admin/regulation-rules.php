<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1>Regulation Rules</h1>

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
                <tr><th><label for="controlling_coordinator">Controlling Coordinator</label></th><td><input type="text" name="controlling_coordinator" id="controlling_coordinator" class="regular-text" required></td></tr>
                <tr><th><label for="elevation">Elevation</label></th><td><label><input type="checkbox" name="elevation" id="elevation" value="1"> Elevated (blocks BBP auto-approve)</label></td></tr>
            </table>
            <?php submit_button( 'Add Rule', 'primary', 'oat_add_rule' ); ?>
        </form>
    </div>

    <!-- CSV Import -->
    <div class="oat-csv-import-section">
        <h2>CSV Import</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'oat_import_csv' ); ?>
            <input type="file" name="csv_file" accept=".csv" required>
            <?php submit_button( 'Import CSV', 'secondary', 'oat_import_csv' ); ?>
        </form>
    </div>

    <!-- Rules List -->
    <h2>Rules</h2>
    <form method="get">
        <input type="hidden" name="page" value="oat-rules">
        <?php $list_table->search_box( 'Search', 'oat-rules-search' ); ?>
        <?php $list_table->display(); ?>
    </form>
</div>
