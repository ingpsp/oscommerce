<?php

class Ing_Services_Lib
{
    public $debugMode;
    public $logTo;
    public $apiKey;
    public $apiEndpoint;
    public $apiVersion;
    public $debugCurl;

    public function __construct($apiKey, $logTo, $debugMode, $product = 'kassacompleet')
    {
        $this->debugMode   = $debugMode;
        $this->logTo       = $logTo;
        $this->apiKey      = $apiKey;

        switch ($product) {
            case 'kassacompleet':
                $this->apiEndpoint = 'https://api.kassacompleet.nl';
                break;
            case 'ingcheckout':
                $this->apiEndpoint = 'https://api.ing-checkout.com';
                break;
            case 'epay':
                $this->apiEndpoint = 'https://api.epay.ing.be';
                break;
            default:
                // TODO: throw exception
        }

        $this->apiVersion  = "v1";

        $this->debugCurl   = true;

        $this->plugin_version = 'osc-1.0.8';
    }

    public function ingLog($contents)
    {
        if ($this->logTo == 'file') {
            $file = dirname(__FILE__) . '/inglog.txt';
            file_put_contents($file, date('Y-m-d H.i.s') . ": ", FILE_APPEND);

            if (is_array($contents)) {
                $contents = var_export($contents, true);
            } elseif (is_object($contents)) {
                $contents = json_encode($contents);
            }

            file_put_contents($file, $contents . "\n", FILE_APPEND);
        } else {
            error_log($contents);
        }
    }

    public function performApiCall($api_method, $post = false)
    {
        $url = implode("/", array($this->apiEndpoint, $this->apiVersion, $api_method));

        $curl = curl_init($url);

        $length = 0;
        if ($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            // curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            $length = strlen($post);
        }

        $request_headers = array(
            "Accept: application/json",
            "Content-Type: application/json",
            "User-Agent: gingerphplib",
            "X-Ginger-Client-Info: " . php_uname(),
            "Authorization: Basic " . base64_encode($this->apiKey . ":"),
            "Connection: close",
            "Content-length: " . $length,
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 2 = to check the existence of a common name and also verify that it matches the hostname provided. In production environments the value of this option should be kept at 2 (default value).
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);

        if ($this->debugCurl) {
            curl_setopt($curl, CURLOPT_VERBOSE, 1); // prevent caching issues
            $file = dirname(__FILE__) . '/ingcurl.txt';
            $file_handle = fopen($file, "a+");
            curl_setopt($curl, CURLOPT_STDERR, $file_handle); // prevent caching issues
        }

        $responseString = curl_exec($curl);

        if ($responseString == false) {
            $response = array('error' => curl_error($curl));
        } else {
            $response = json_decode($responseString, true);

            if (!$response) {
                $this->ingLog('invalid json: JSON error code: ' . json_last_error() . "\nRequest: " . $responseString);
                $response = array('error' =>  'Invalid JSON');
            }
        }
        curl_close($curl);

        return $response;
    }

    public function ingGetIssuers()
    {
        // API Request to ING to fetch the issuers
        return $this->performApiCall("ideal/issuers/");
    }

    public function ingCreateIdealOrder($orders_id, $total, $issuer_id, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),                 
            "description"       => (string)$description,
            "return_url"        => (string)$return_url,
            "transactions"      => array(
                array(
                    "payment_method"         => "ideal",
                    "payment_method_details" => array("issuer_id" => $issuer_id)
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function ingCreateKlarnaOrder($orders_id, $total, $return_url, $description, $customer, $order_lines)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'birthdate'     => !empty($customer['birthdate']) ? (string)$customer['birthdate'] : null,
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'ip_address'    => !empty($customer['ip_address']) ? (string)$customer['ip_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'gender'        => !empty($customer['gender'   ]) ? (string)$customer['gender'   ] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
                'phone_numbers' => array($customer['phone_number']),
            ),                 
            "description"       => (string)$description,
            "return_url"        => (string)$return_url,
            "transactions"      => array(
                array(
                    "payment_method"         => "klarna",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
            'order_lines' => $order_lines,
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function ingCreateCreditCardOrder($orders_id, $total, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),            
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "credit-card",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function ingCreateBcOrder($orders_id, $total, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),            
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "bancontact",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function ingCreatePayconiqOrder($orders_id, $total, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),            
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "payconiq",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }  

    public function ingCreateHomepayOrder($orders_id, $total, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),            
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "homepay",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }  

    public function ingCreatePaypalOrder($orders_id, $total, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),            
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "paypal",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }        

    public function ingCreateSofortOrder($orders_id, $total, $return_url, $description, $customer)
    {
        $post = array(
            "type"              => "payment",
            "currency"          => "EUR",
            "amount"            => 100 * round($total, 2),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
                'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),            
            "description"       => $description,
            "return_url"        => $return_url,
            "transactions"      => array(
                array(
                    "payment_method" => "sofort",
                )
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }    

    public function ingCreateBanktransferOrder($orders_id, $total, $description, $customer = array())
    {
        $post = array(
            "type"         => "payment",
            "currency"     => "EUR",
            "amount"       => 100 * round($total, 2),
            "description"  => (string)$description,
            "transactions" => array(array(
                "payment_method" => "bank-transfer",
            )),
            "merchant_order_id" => (string)$orders_id,
			'customer' => array(
	            'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
	            'address_type'  => 'customer',
	            'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
	            'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
	            'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
	            'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null,
	            'locale'        => !empty($customer['locale']) ? (string)$customer['locale'] : null,
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function ingCreateCashondeliveryOrder($orders_id, $total, $description, $customer = array())
    {
        $post = array(
            "type"         => "payment",
            "currency"     => "EUR",
            "amount"       => 100 * round($total, 2),
            "description"  => (string)$description,
            "transactions" => array(array(
                "payment_method" => "cash-on-delivery",
            )),
            "merchant_order_id" => (string)$orders_id,
            'customer' => array(
                'address'       => !empty($customer['address']) ? (string)$customer['address'] : null,
                'address_type'  => 'customer',
                'country'       => !empty($customer['country']) ? (string)$customer['country'] : null,
                'email_address' => !empty($customer['email_address']) ? (string)$customer['email_address'] : null,
                'first_name'    => !empty($customer['first_name']) ? (string)$customer['first_name'] : null,
                'last_name'     => !empty($customer['last_name']) ? (string)$customer['last_name'] : null,
                'postal_code'   => !empty($customer['postal_code']) ? (string)$customer['postal_code'] : null
            ),
            'extra' => array(
                'plugin' => $this->plugin_version,
            ),
        );

        $order = json_encode($post);
        $result = $this->performApiCall("orders/", $order);

        return $result;
    }

    public function getOrderStatus($order_id)
    {
        $order = $this->performApiCall("orders/" . $order_id . "/");

        if (!is_array($order) || array_key_exists('error', $order)) {
            return 'error';
        }
        else {
            return $order['status'];
        }
    }

    public function getOrderDetails($order_id)
    {
        $order = $this->performApiCall("orders/" . $order_id . "/");

        if (!is_array($order) || array_key_exists('error', $order)) {
            return 'error';
        }
        else {
            return $order;
        }
    }

    public function getAllowedProducts() {
        $result = array();
        $project_details = $this->performApiCall("merchants/self/projects/self/");
        if (!array_key_exists('permissions', $project_details))
            return $result;

        if (array_key_exists('status', $project_details) && $project_details['status'] == 'active-testing')
            return array('ideal');

        $products_to_check = array(
            'ideal' => 'ideal',
            'bank-transfer' => 'banktransfer',
            'bancontact' => 'bancontact',
            'payconiq' => 'payconiq',
            'homepay' => 'homepay',
            'cash-on-delivery' => 'cashondelivery',
            'credit-card' => 'creditcard',
            'sofort' => 'sofort',
            'paypal' => 'paypal',
            'klarna' => 'klarna',
            );

        foreach ($products_to_check as $permission_id => $payment_product_id) {
            if (array_key_exists('/payment-methods/' . $permission_id . '/', $project_details['permissions']) &&
            array_key_exists('POST', $project_details['permissions']['/payment-methods/' . $permission_id . '/']) &&
            $project_details['permissions']['/payment-methods/' . $permission_id . '/']['POST']
            )
            $result[] = $payment_product_id;
        }
        return $result;
    }    
}