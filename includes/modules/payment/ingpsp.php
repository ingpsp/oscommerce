<?php

class ingpsp {
  var $code, $title, $description, $sort_order, $enabled, $debug_mode, $log_to, $ingpsp;

  // Class Constructor
  function ingpsp() {
    global $order;

    $this->code = 'ingpsp';
    $this->title_selection = MODULE_PAYMENT_INGPSP_TEXT_TITLE;
    $this->title = MODULE_PAYMENT_INGPSP_TEXT_TITLE;
    $this->description = MODULE_PAYMENT_INGPSP_TEXT_DESCRIPTION;
    $this->sort_order = 0;
    $this->enabled = ( ( MODULE_PAYMENT_INGPSP_STATUS == 'True' ) ? true : false );
    $this->debug_mode = ( ( MODULE_PAYMENT_INGPSP_DEBUG_MODE == 'True' ) ? true : false );
    $this->log_to = MODULE_PAYMENT_INGPSP_LOG_TO;

    if ( (int)MODULE_PAYMENT_INGPSP_ORDER_STATUS_ID > 0 ) {
      $this->order_status = MODULE_PAYMENT_INGPSP_ORDER_STATUS_ID;
      $payment = 'ingpsp';
    } else if ( $payment=='ingpsp' ) {
        $payment='';
      }
    if ( is_object( $order ) ) {
      $this->update_status();
    }

    $this->ingpsp = null;
    if ($this->enabled) {
      if ( file_exists( 'ingpsp/ing_lib.php' ) ) {
        require_once 'ingpsp/ing_lib.php';
        $this->ingpsp = new Ing_Services_Lib( MODULE_PAYMENT_INGPSP_APIKEY, $this->log_to, $this->debug_mode, MODULE_PAYMENT_INGPSP_PRODUCT );
      } else {
        // TODO: SHOULD GIVE WARNING
      }
    }
  }

  // Class Methods
  function update_status() {
    // check that api key is not blank
    if ( !MODULE_PAYMENT_INGPSP_APIKEY or !strlen( MODULE_PAYMENT_INGPSP_APIKEY ) ) {
      print 'no secret '.MODULE_PAYMENT_INGPSP_APIKEY;
      $this->enabled = false;
    }
  }

  function javascript_validation() {
    return false;
  }

  function selection() {
    return false;
  }

  function pre_confirmation_check() {
  }

  function confirmation() {
    return false;
  }

  function process_button() {
    return false;
  }

  function before_process() {
    return false;
  }

  function after_process() {
    return false;
  }

  function get_error() {
    return false;
  }

  function check() {
    if ( !isset( $this->_check ) ) {
      $check_query = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_INGPSP_STATUS'" );
      $this->_check = tep_db_num_rows( $check_query );
    }
    return $this->_check;
  }

  function tableColumnExists($table_name, $column_name) {
    $check_q = tep_db_query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . $table_name . "' AND COLUMN_NAME = '" . $column_name ."'");
    return tep_db_num_rows($check_q);
  }

  function install() {

    // ADD INGPSP ORDER ID TO THE ORDERS TABLE
    if (!$this->tableColumnExists("orders", "ingpsp_order_id")) {
      if (!tep_db_query("ALTER TABLE orders ADD ingpsp_order_id VARCHAR( 36 ) NULL DEFAULT NULL ;")) {
        die("To be able to work; please add the column ingpsp_order_id (VARCHAR 36, DEFAULT NULL) to your order table");
      }
    }

    $sort_order = 0;
    $add_array = array(
      "configuration_title" => 'Enable ING PSP Module',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_STATUS',
      "configuration_value" => 'False',
      "configuration_description" => 'Do you want to accept payments using ING?',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('True', 'False'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'PSP Product',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_PRODUCT',
      "configuration_value" => 'kassacompleet',
      "configuration_description" => 'What product do you want to use?',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('ingcheckout', 'kassacompleet', 'ingepay'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'API Key',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_APIKEY',
      "configuration_value" => '',
      "configuration_description" => 'Enter your API key',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'New Order Status',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_NEW_STATUS_ID',
      "configuration_value" => intval( DEFAULT_ORDERS_STATUS_ID ),
      "configuration_description" => 'New order status.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_order_statuses(",
      "use_function" => "tep_get_order_status_name",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Pending Order Status',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_PENDING_STATUS_ID',
      "configuration_value" => intval( DEFAULT_ORDERS_STATUS_ID ),
      "configuration_description" => 'Pending order status.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_order_statuses(",
      "use_function" => "tep_get_order_status_name",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Complete Order Status',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_COMPLETE_STATUS_ID',
      "configuration_value" => 2,
      "configuration_description" => 'Complete order status.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_order_statuses(",
      "use_function" => "tep_get_order_status_name",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Cancelled Order Status',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_CANCELLED_STATUS_ID',
      "configuration_value" => intval( DEFAULT_ORDERS_STATUS_ID ),
      "configuration_description" => 'Cancelled order status.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_order_statuses(",
      "use_function" => "tep_get_order_status_name",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Error Order Status',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_ERROR_STATUS_ID',
      "configuration_value" => intval( DEFAULT_ORDERS_STATUS_ID ),
      "configuration_description" => 'Error order status.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_order_statuses(",
      "use_function" => "tep_get_order_status_name",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Debug mode',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_DEBUG_MODE',
      "configuration_value" => 'False',
      "configuration_description" => 'In DEBUG mode request are logged from the ing_lib',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('True', 'False'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Log to',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_LOG_TO',
      "configuration_value" => 'file',
      "configuration_description" => 'Log to',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('file', 'php'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

  }

  function remove() {
    tep_db_query( "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );
  }

  function keys() {
    return array(
      'MODULE_PAYMENT_INGPSP_STATUS',
      'MODULE_PAYMENT_INGPSP_PRODUCT',
      'MODULE_PAYMENT_INGPSP_APIKEY',
      'MODULE_PAYMENT_INGPSP_NEW_STATUS_ID',
      'MODULE_PAYMENT_INGPSP_PENDING_STATUS_ID',
      'MODULE_PAYMENT_INGPSP_COMPLETE_STATUS_ID',
      'MODULE_PAYMENT_INGPSP_CANCELLED_STATUS_ID',
      'MODULE_PAYMENT_INGPSP_ERROR_STATUS_ID',
      'MODULE_PAYMENT_INGPSP_DEBUG_MODE',
      'MODULE_PAYMENT_INGPSP_LOG_TO',
    );
  }

  function tep_remove_order( $order_id, $restock = false ) {
    if ( $restock == 'on' ) {
      $order_query = tep_db_query( "select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'" );
      while ( $order = tep_db_fetch_array( $order_query ) ) {
        tep_db_query( "update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order['products_quantity'] . ", products_ordered = products_ordered - " . $order['products_quantity'] . " where products_id = '" . (int)$order['products_id'] . "'" );
      }
    }

    tep_db_query( "delete from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . (int)$order_id . "'" );
    tep_db_query( "delete from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "'" );
  }

}
