<div class="wrap">
    <h1>History Package</h1>
    <form action="" method="GET">
        <?php $list_table->search_box( __( 'Search' ), 'search-box-id' ); ?>
        <?php $list_table->display(); ?>
        <input type="hidden" name="page" value="<?= esc_attr($_REQUEST['page']) ?>"/>
    </form>
</div>