<?php
$can_activate = false;
// determine if we can do Klarna
// if there's an API key, but the module is not yet activated, check if we will list it
if (strlen(MODULE_PAYMENT_INGPSP_APIKEY) || (defined('MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY') && strlen(MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY))) {
  $allowed_products = array();
  if ( file_exists( '../ingpsp/ing_lib.php' ) )
    $ing_lib_location = '../ingpsp/ing_lib.php';
  if ( file_exists( 'ingpsp/ing_lib.php' ) )
    $ing_lib_location = 'ingpsp/ing_lib.php';

  if ( file_exists($ing_lib_location)) {
    require_once($ing_lib_location);

    if (defined('MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY') && MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY != '')
      $ing_services_lib = new Ing_Services_Lib( MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY, MODULE_PAYMENT_INGPSP_LOG_TO, MODULE_PAYMENT_INGPSP_DEBUG_MODE == 'True', MODULE_PAYMENT_INGPSP_PRODUCT );
    else 
      $ing_services_lib = new Ing_Services_Lib( MODULE_PAYMENT_INGPSP_APIKEY, MODULE_PAYMENT_INGPSP_LOG_TO, MODULE_PAYMENT_INGPSP_DEBUG_MODE == 'True', MODULE_PAYMENT_INGPSP_PRODUCT );

    $allowed_products = $ing_services_lib->getAllowedProducts();

    if (in_array('klarna', $allowed_products))
      $can_activate = true;
  }
}

if ($can_activate) {

class ingpsp_klarna {
  var $code, $title, $description, $sort_order, $enabled, $debug_mode, $log_to, $ingpsp;

  // Class Constructor
  function ingpsp_klarna() {
    global $order;

    $this->code = 'ingpsp_klarna';
    $this->title_selection = MODULE_PAYMENT_INGPSP_KLARNA_TEXT_TITLE;
    $this->title = 'ING PSP ' . $this->title_selection;
    $this->description = MODULE_PAYMENT_INGPSP_KLARNA_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_PAYMENT_INGPSP_KLARNA_SORT_ORDER;
    $this->enabled = ( ( MODULE_PAYMENT_INGPSP_KLARNA_STATUS == 'True' ) ? true : false );
    $this->debug_mode = ( ( MODULE_PAYMENT_INGPSP_DEBUG_MODE == 'True' ) ? true : false );
    $this->log_to = MODULE_PAYMENT_INGPSP_LOG_TO;

    if ( (int)MODULE_PAYMENT_INGPSP_ORDER_STATUS_ID > 0 ) {
      $this->order_status = MODULE_PAYMENT_INGPSP_ORDER_STATUS_ID;
      $payment = 'ingpsp_klarna';
    } else if ( $payment=='ingpsp_klarna' ) {
        $payment='';
      }
    if ( is_object( $order ) ) {
      $this->update_status();
    }

    $this->ingpsp = null;
    if ($this->enabled) {
      if ( file_exists( 'ingpsp/ing_lib.php' ) ) {
        require_once 'ingpsp/ing_lib.php';
        if (defined('MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY') && MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY != '')
          $this->ingpsp = new Ing_Services_Lib( MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY, $this->log_to, $this->debug_mode, MODULE_PAYMENT_INGPSP_PRODUCT );
        else 
          $this->ingpsp = new Ing_Services_Lib( MODULE_PAYMENT_INGPSP_APIKEY, $this->log_to, $this->debug_mode, MODULE_PAYMENT_INGPSP_PRODUCT );
      } else {
        // TODO: SHOULD GIVE WARNING
      }
    }
  }

  // Class Methods
  function update_status() {
    global $order;

    if ( ( $this->enabled == true ) && ( (int)MODULE_PAYMENT_INGPSP_KLARNA_ZONE > 0 ) ) {
      $check_flag = false;
      $check_query = tep_db_query( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . intval( MODULE_PAYMENT_INGPSP_KLARNA_ZONE ) . "' and zone_country_id = '" . intval( $order->billing['country']['id'] ) . "' order by zone_id" );
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
    if (in_array($_SERVER['REMOTE_ADDR'], explode(';', MODULE_PAYMENT_INGPSP_KLARNA_TEST_IP))) {
      return;
    }

    $selection['id'] = $this->code;
    $selection['module'] = $this->title_selection;

    // ASK FOR DOB if not KNOWN
    // $selection['fields'][0]['title'] = '';
    // $selection['fields'][0]['field'] = tep_draw_pull_down_menu( 'ingpsp_issuer_id', $this->get_issuers(), $_SESSION['ingpsp_issuer_id'], $onFocus );

    return $selection;
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
    global $insert_id, $order, $cart, $currencies, $customer_id;

    $order_lines = array();

    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
      $order_lines[] = array(
        'amount' => (int)round(($order->products[$i]['final_price'] + tep_calculate_tax($order->products[$i]['final_price'], $order->products[$i]['tax'])) * 100, 0),
        'currency' => 'EUR',
        'merchant_order_line_id' => $insert_id . "_" . $order->products[$i]['id'],
        'name' => $order->products[$i]['name'],
        'quantity' => (int)$order->products[$i]['qty'],
        'type' => 'physical',
        'url' => tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $order->products[$i]['id']),
        'vat_percentage' => (int)round(($order->products[$i]['tax'] * 100), 0),
        );
    }
    // check if there is shipping
    if ($order->info['shipping_cost']) {
      $order_lines[] = array(
        'amount' => (int)round($order->info['shipping_cost'] * 100, 0),
        'currency' => 'EUR',
        'merchant_order_line_id' => $insert_id . "_shipping",
        'name' => $order->info['shipping_method'],
        'quantity' => (int)1,
        'type' => 'shipping_fee',
        'vat_percentage' => (int)2100,
        );
    }
    $customer_data_not_in_customer_object = tep_db_fetch_array(tep_db_query("SELECT customers_dob, customers_gender FROM customers WHERE customers_id = '" . (int)$customer_id . "'"));

    $customer = array(
      'address'       => $order->customer['street_address'] . "\n" . $order->customer['postcode'] . ' ' . $order->customer['city'],
      'address_type'  => 'customer',
      'birthdate'     => date("Y-m-d", strtotime($customer_data_not_in_customer_object['customers_dob'])),
      'country'       => $order->customer['country']['iso_code_2'],
      'email_address' => $order->customer['email_address'],
      'first_name'    => $order->customer['firstname'],
      'last_name'     => $order->customer['lastname'],
      'gender'        => $order->customer['gender'] == "f" ? 'female' : 'male',
      'postal_code'   => $order->customer['postcode'],
      'phone_number'  => $order->customer['telephone'],
      'ip_address'    => $_SERVER['REMOTE_ADDR'],
      'locale'        => 'nl_NL',  
      );

    global $languages_id;
    // check if it's not english
    $language_row = tep_db_fetch_array(tep_db_query("SELECT * FROM languages WHERE languages_id = '" . $languages_id . "'"));
    if ($language_row['code'] == 'en')
      $customer['locale'] = 'en_GB';
    else
      $customer['locale'] = $language_row['code'] . "_" . strtoupper($language_row['code']);

    $ingpsp_order = $this->ingpsp->ingCreateKlarnaOrder( $insert_id, $order->info['total'], tep_href_link( "ext/modules/payment/ingpsp/redir.php", '', 'SSL' ), STORE_NAME . " " . $insert_id, $customer, $order_lines );

    // change order status to value selected by merchant
    tep_db_query( "update ". TABLE_ORDERS. " set orders_status = " . intval( MODULE_PAYMENT_INGPSP_NEW_STATUS_ID ) . ", ingpsp_order_id = '" . $ingpsp_order['id']  . "' where orders_id = ". intval( $insert_id ) );

    $this->ingpsp->ingLog( $ingpsp_order );

    if ( !is_array( $ingpsp_order ) or array_key_exists( 'error', $ingpsp_order) or $ingpsp_order['status'] == 'error' ) {
      // TODO: Remove this? I don't know if I like it removing orders, or make it optional
      // $this->tep_remove_order( $insert_id, $restock = true );
      // check if we have a reason
      $reason = "Error placing Klarna order";
      if (array_key_exists('error', $ingpsp_order) && array_key_exists('value', $ingpsp_order['error']))
        $reason .= " " . $ingpsp_order['error']['value'];
      if (array_key_exists('reason', $ingpsp_order['transactions'][0]))
        $reason .= " " . $ingpsp_order['transactions'][0]['reason'];
      tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( $reason ), 'SSL' ) );
    }

    return true;
  }

  function get_error() {
    return false;
  }

  function check() {
    if ( !isset( $this->_check ) ) {
      $check_query = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_INGPSP_KLARNA_STATUS'" );
      $this->_check = tep_db_num_rows( $check_query );
    }
    return $this->_check;
  }

  function tableColumnExists($table_name, $column_name) {
    $check_q = tep_db_query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . $table_name . "' AND COLUMN_NAME = '" . $column_name ."'");
    return tep_db_num_rows($check_q);
  }

  function install() {

    $sort_order = 0;
    $add_array = array(
      "configuration_title" => 'Enable ING PSP Klarna Module',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_KLARNA_STATUS',
      "configuration_value" => 'False',
      "configuration_description" => 'Do you want to accept Klarna payments via ING psp?',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "set_function" => "tep_cfg_select_option(array('True', 'False'), ",
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Payment Zone',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_KLARNA_ZONE',
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
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_KLARNA_SORT_ORDER',
      "configuration_value" => 0,
      "configuration_description" => 'Sort order of display. Lowest is displayed first.',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Test API key',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY',
      "configuration_value" => '',
      "configuration_description" => 'Test API key, if filled this one is used to initiate the Klarna transaction',
      "configuration_group_id " => '6',
      "sort_order" => $sort_order,
      "date_added " => 'now()',
    );
    tep_db_perform( TABLE_CONFIGURATION, $add_array );
    $sort_order++;

    $add_array = array(
      "configuration_title" => 'Test IP addresses',
      "configuration_key" => 'MODULE_PAYMENT_INGPSP_KLARNA_TEST_IP',
      "configuration_value" => '',
      "configuration_description" => 'IP Addresses to test Klarna with, seperated by ; leave empty to disable IP filtering',
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
      'MODULE_PAYMENT_INGPSP_KLARNA_STATUS',
      'MODULE_PAYMENT_INGPSP_KLARNA_ZONE',
      'MODULE_PAYMENT_INGPSP_KLARNA_SORT_ORDER',
      'MODULE_PAYMENT_INGPSP_KLARNA_TEST_APIKEY',
      'MODULE_PAYMENT_INGPSP_KLARNA_TEST_IP',
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