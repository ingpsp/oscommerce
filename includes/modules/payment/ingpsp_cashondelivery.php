<?php
$can_activate = false;
// determine if we can do Cash on delivery
// if there's an API key, but the module is not yet activated, check if we will list it
if (strlen(MODULE_PAYMENT_INGPSP_APIKEY)) {
  $allowed_products = array();
  if ( file_exists( '../ingpsp/ing_lib.php' ) )
    $ing_lib_location = '../ingpsp/ing_lib.php';
  if ( file_exists( 'ingpsp/ing_lib.php' ) )
    $ing_lib_location = 'ingpsp/ing_lib.php';

  if ( file_exists($ing_lib_location)) {
    require_once($ing_lib_location);

    $ing_services_lib = new Ing_Services_Lib( MODULE_PAYMENT_INGPSP_APIKEY, MODULE_PAYMENT_INGPSP_LOG_TO, MODULE_PAYMENT_INGPSP_DEBUG_MODE == 'True', MODULE_PAYMENT_INGPSP_PRODUCT );

    $allowed_products = $ing_services_lib->getAllowedProducts();

    if (in_array('cashondelivery', $allowed_products))
      $can_activate = true;
  }
}

if ($can_activate) {

class ingpsp_cashondelivery {
  var $code, $title, $description, $sort_order, $enabled, $debug_mode, $log_to, $ingpsp;

  // Class Constructor
  function ingpsp_cashondelivery() {
    global $order;

    $this->code = 'ingpsp_cashondelivery';
    $this->title_selection = MODULE_PAYMENT_INGPSP_CASHONDELIVERY_TEXT_TITLE;
    $this->title = 'ING PSP ' . $this->title_selection;
    $this->description = MODULE_PAYMENT_INGPSP_CASHONDELIVERY_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_PAYMENT_INGPSP_CASHONDELIVERY_SORT_ORDER;
    $this->enabled = ( ( MODULE_PAYMENT_INGPSP_CASHONDELIVERY_STATUS == 'True' ) ? true : false );
    $this->debug_mode = ( ( MODULE_PAYMENT_INGPSP_DEBUG_MODE == 'True' ) ? true : false );
    $this->log_to = MODULE_PAYMENT_INGPSP_LOG_TO;

    if ( (int)MODULE_PAYMENT_INGPSP_ORDER_STATUS_ID > 0 ) {
      $this->order_status = MODULE_PAYMENT_INGPSP_ORDER_STATUS_ID;
      $payment = 'ingpsp_cashondelivery';
    } else if ( $payment=='ingpsp_cashondelivery' ) {
        $payment='';
      }
    if ( is_object( $order ) ) {
      $this->update_status();
    }
    $this->email_footer = '';

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
    global $order;

    if ( ( $this->enabled == true ) && ( (int)MODULE_PAYMENT_INGPSP_CASHONDELIVERY_ZONE > 0 ) ) {
      $check_flag = false;
      $check_query = tep_db_query( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . intval( MODULE_PAYMENT_INGPSP_CASHONDELIVERY_ZONE ) . "' and zone_country_id = '" . intval( $order->billing['country']['id'] ) . "' order by zone_id" );
      while ( $check = tep_db_fetch_array( $check_query ) ) {
        if ( $check['zone_id'] < 1 ) {
          $check_flag = true;
          break;
        }
        elseif ( $check['zone_id'] == $order->billing['zone_id'] ) {
          $check_flag = true;
          break;
        }
      }

      if ( $check_flag == false ) {
        $this->enabled = false;
      }
    }

    if ( $order->info['currency'] != "EUR" ) {
      $this->enabled = false;
    }

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
    $selection['id'] = $this->code;
    $selection['module'] = $this->title_selection;
    return $selection;
  }

  function pre_confirmation_check() {
    return false;
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
    global $insert_id, $order;

    $customer = array(
      'address'       => $order->customer['street_address'] . "\n" . $order->customer['postcode'] . ' ' . $order->customer['city'],
      'address_type'  => 'customer',
      'country'       => $order->customer['country']['iso_code_2'],
      'email_address' => $order->customer['email_address'],
      'first_name'    => $order->customer['firstname'],
      'last_name'     => $order->customer['lastname'],
      'postal_code'   => $order->customer['postcode'],
      'locale'        => 'nl_NL',  
      );

    global $languages_id;
    // check if it's not english
    $language_row = tep_db_fetch_array(tep_db_query("SELECT * FROM languages WHERE languages_id = '" . $languages_id . "'"));
    if ($language_row['code'] == 'en')
      $customer['locale'] = 'en_GB';    

    $ingpsp_order = $this->ingpsp->ingCreateCashondeliveryOrder( $insert_id, 
                                                                $order->info['total'], 
                                                                STORE_NAME . " " . $insert_id, 
                                                                $customer 
                                                                );

    // change order status to value selected by merchant
    tep_db_query( "update ". TABLE_ORDERS. " set orders_status = " . intval( MODULE_PAYMENT_INGPSP_NEW_STATUS_ID ) . ", ingpsp_order_id = '" . $ingpsp_order['id']  . "' where orders_id = ". intval( $insert_id ) );

    $this->ingpsp->ingLog( $ingpsp_order );

    if ( !is_array( $ingpsp_order ) or array_key_exists( 'error', $ingpsp_order) or $ingpsp_order['status'] == 'error' ) {
      // TODO: Remove this? I don't know if I like it removing orders, or make it optional
      $this->tep_remove_order( $insert_id, $restock = true );
      tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( "Error placing ingpsp order" ), 'SSL' ) );
    }
    return true;
  }

  function get_error() {
    return false;
  }

  function check() {
    if ( !isset( $this->_check ) ) {
      $check_query = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_STATUS'" );
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
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_STATUS',
      "configuration_value" => 'False',
      "configuration_description" => 'Do you want to accept cash on delivery payments via ING psp?',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('True', 'False'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Payment Zone',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_ZONE',
      "configuration_value" => 0,
      "configuration_description" => 'If a zone is selected, only enable this payment method for that zone.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_pull_down_zone_classes(",
      "use_function" => "tep_get_zone_class_title",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Sort Order of Display',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_SORT_ORDER',
      "configuration_value" => 0,
      "configuration_description" => 'Sort order of display. Lowest is displayed first.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
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
      'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_STATUS',
      'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_ZONE',
      'MODULE_PAYMENT_INGPSP_CASHONDELIVERY_SORT_ORDER',
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
}