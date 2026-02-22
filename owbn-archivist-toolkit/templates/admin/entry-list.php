<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1>All Entries</h1>

    <form method="get">
        <input type="hidden" name="page" value="oat-entries">
        <?php $list_table->display(); ?>
    </form>
</div>
