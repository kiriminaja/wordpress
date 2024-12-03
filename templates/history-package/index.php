<?php
class History_Package_Table extends WP_List_Table {

    function __construct() {
        parent::__construct([
            'singular' => 'history', // Singular name
            'plural'   => 'histories', // Plural name
            'ajax'     => false // No AJAX for this table
        ]);
    }

    function get_columns() {
        return [
            'id'       => 'ID',
            'order_id' => 'Order ID',
            'destination_sub_district' => 'Subdistrict',
            'created_at' => 'Created At',
        ];
    }

    function prepare_items() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kiriminaja_transactions'; // Change this to your table name
        $query      = "SELECT id,order_id,destination_sub_district,created_at FROM $table_name WHERE destination_sub_district != '0'";

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        // here we configure table headers, defined in our methods
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // search table
        $search  = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';        
        if( !empty($search) ){
            $query .= $wpdb->prepare(" AND id LIKE %s OR order_id LIKE %s OR destination_sub_district LIKE %s", "%{$search}%","%{$search}%", "%{$search}%");
        }

        // Pagination arguments
        $per_page   = 10; // Number of items per page
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM ($query) AS subquery");

        // Pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);

        $this->items = $wpdb->get_results($query . " LIMIT " . (($current_page - 1) * $per_page) . ", $per_page", ARRAY_A);
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />', $item['id']
        );
    }

    function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }

    function get_sortable_columns() {
        return [
            'id'   => ['id', true],
            'order_id' => ['order_id', true],
            'destination_sub_district' => ['destination_sub_district', true],
        ];
    }
}

$list_table  = new History_Package_Table();
$list_table->prepare_items();

/** Return vars and view*/
include 'view/index.php';
?>