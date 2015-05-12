<?php

/*
Copyright © 2015 Brent "Atomic Blaze" Smith

This file is part of "Bitcoin Donations".

    "Bitcoin Donations" is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    "Bitcoin Donations" is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with "Bitcoin Donations".  If not, see <http://www.gnu.org/licenses/>.
*/

if (!defined('SMF'))
	die('Hacking attempt...');

class Coinbase
{
    const API_BASE = 'https://api.coinbase.com/v1/';
    private $_rpc;
    private $_authentication;


    public static function withApiKey($key, $secret)
    {
        return new Coinbase(new Coinbase_ApiKeyAuthentication($key, $secret));
    }

    public static function withSimpleApiKey($key)
    {
        return new Coinbase(new Coinbase_SimpleApiKeyAuthentication($key));
    }

    public static function withOAuth($oauth, $tokens)
    {
        return new Coinbase(new Coinbase_OAuthAuthentication($oauth, $tokens));
    }

    // This constructor is deprecated.
    public function __construct($authentication, $tokens=null, $apiKeySecret=null)
    {
        // First off, check for a legit authentication class type
        if (is_a($authentication, 'Coinbase_Authentication')) {
            $this->_authentication = $authentication;
        } else {
            // Here, $authentication was not a valid authentication object, so
            // analyze the constructor parameters and return the correct object.
            // This should be considered deprecated, but it's here for backward compatibility.
            // In older versions of this library, the first parameter of this constructor
            // can be either an API key string or an OAuth object.
            if ($tokens !== null) {
                $this->_authentication = new Coinbase_OAuthAuthentication($authentication, $tokens);
            } else if ($authentication !== null && is_string($authentication)) {
                $apiKey = $authentication;
                if ($apiKeySecret === null) {
                    // Simple API key
                    $this->_authentication = new Coinbase_SimpleApiKeyAuthentication($apiKey);
                } else {
                    $this->_authentication = new Coinbase_ApiKeyAuthentication($apiKey, $apiKeySecret);
                }
            } else {
                throw new Coinbase_ApiException('Could not determine API authentication scheme');
            }
        }

        $this->_rpc = new Coinbase_Rpc(new Coinbase_Requestor(), $this->_authentication);
    }

    // Used for unit testing only
    public function setRequestor($requestor)
    {
        $this->_rpc = new Coinbase_Rpc($requestor, $this->_authentication);
        return $this;
    }

    public function get($path, $params=array())
    {
        return $this->_rpc->request("GET", $path, $params);
    }

    public function post($path, $params=array())
    {
        return $this->_rpc->request("POST", $path, $params);
    }

    public function delete($path, $params=array())
    {
        return $this->_rpc->request("DELETE", $path, $params);
    }

    public function put($path, $params=array())
    {
        return $this->_rpc->request("PUT", $path, $params);
    }

    private function getPaginatedResource($resource, $listElement, $unwrapElement, $page=0, $params=array())
    {
        $result = $this->get($resource, array_merge(array( "page" => $page ), $params));
        $elements = array();
        foreach($result->{$listElement} as $element) {
            $elements[] = $element->{$unwrapElement}; // Remove one layer of nesting
        }

        $returnValue = new stdClass();
        $returnValue->total_count = $result->total_count;
        $returnValue->num_pages = $result->num_pages;
        $returnValue->current_page = $result->current_page;
        $returnValue->{$listElement} = $elements;
        return $returnValue;
    }

    public function getBalance()
    {
        return $this->get("account/balance", array())->amount;
    }

    public function getReceiveAddress()
    {
        return $this->get("account/receive_address", array())->address;
    }

    public function getAllAddresses($query=null, $page=0, $limit=null)
    {
        $params = array();
        if ($query !== null) {
            $params['query'] = $query;
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        return $this->getPaginatedResource("addresses", "addresses", "address", $page, $params);
    }

    public function generateReceiveAddress($callback=null, $label=null)
    {
        $params = array();
        if($callback !== null) {
            $params['address[callback_url]'] = $callback;
        }
        if($label !== null) {
            $params['address[label]'] = $label;
        }
        return $this->post("account/generate_receive_address", $params)->address;
    }

    public function sendMoney($to, $amount, $notes=null, $userFee=null, $amountCurrency=null)
    {
        $params = array( "transaction[to]" => $to );

        if($amountCurrency !== null) {
            $params["transaction[amount_string]"] = $amount;
            $params["transaction[amount_currency_iso]"] = $amountCurrency;
        } else {
            $params["transaction[amount]"] = $amount;
        }

        if($notes !== null) {
            $params["transaction[notes]"] = $notes;
        }

        if($userFee !== null) {
            $params["transaction[user_fee]"] = $userFee;
        }

        return $this->post("transactions/send_money", $params);
    }

    public function requestMoney($from, $amount, $notes=null, $amountCurrency=null)
    {
        $params = array( "transaction[from]" => $from );

        if($amountCurrency !== null) {
            $params["transaction[amount_string]"] = $amount;
            $params["transaction[amount_currency_iso]"] = $amountCurrency;
        } else {
            $params["transaction[amount]"] = $amount;
        }

        if($notes !== null) {
            $params["transaction[notes]"] = $notes;
        }

        return $this->post("transactions/request_money", $params);
    }

    public function resendRequest($id)
    {
        return $this->put("transactions/" . $id . "/resend_request", array());
    }

    public function cancelRequest($id)
    {
        return $this->delete("transactions/" . $id . "/cancel_request", array());
    }

    public function completeRequest($id)
    {
        return $this->put("transactions/" . $id . "/complete_request", array());
    }

    public function createButton($name, $price, $currency, $custom=null, $options=array())
    {

        $params = array(
            "name" => $name,
            "price_string" => $price,
            "price_currency_iso" => $currency
        );
        if($custom !== null) {
            $params['custom'] = $custom;
        }
        foreach($options as $option => $value) {
            $params[$option] = $value;
        }

        return $this->createButtonWithOptions($params);
    }

    public function createButtonWithOptions($options=array())
    {

        $response = $this->post("buttons", array( "button" => $options ));

        if(!$response->success) {
            return $response;
        }

        $returnValue = new stdClass();
        $returnValue->button = $response->button;
        $returnValue->embedHtml = "<div class=\"coinbase-button\" data-code=\"" . $response->button->code . "\"></div><script src=\"https://coinbase.com/assets/button.js\" type=\"text/javascript\"></script>";
        $returnValue->success = true;
        return $returnValue;
    }

    public function createOrderFromButtonCode($buttonCode)
    {
        return $this->post("buttons/" . $buttonCode . "/create_order");
    }

    public function createUser($email, $password)
    {
        return $this->post("users", array(
            "user[email]" => $email,
            "user[password]" => $password,
        ));
    }

    public function buy($amount, $agreeBtcAmountVaries=false)
    {
        return $this->post("buys", array(
            "qty" => $amount,
            "agree_btc_amount_varies " => $agreeBtcAmountVaries,
        ));
    }

    public function sell($amount)
    {
        return $this->post("sells", array(
            "qty" => $amount,
        ));
    }

    public function getContacts($query=null, $page=0, $limit=null)
    {
        $params = array(
            "page" => $page,
        );
        if ($query !== null) {
            $params['query'] = $query;
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        $result = $this->get("contacts", $params);
        $contacts = array();
        foreach($result->contacts as $contact) {
            if(trim($contact->contact->email) != false) { // Check string not empty
                $contacts[] = $contact->contact->email;
            }
        }

        $returnValue = new stdClass();
        $returnValue->total_count = $result->total_count;
        $returnValue->num_pages = $result->num_pages;
        $returnValue->current_page = $result->current_page;
        $returnValue->contacts = $contacts;
        return $returnValue;
    }

    public function getCurrencies()
    {
        $response = $this->get("currencies", array());
        $result = array();
        foreach ($response as $currency) {
            $currency_class = new stdClass();
            $currency_class->name = $currency[0];
            $currency_class->iso = $currency[1];
            $result[] = $currency_class;
        }
        return $result;
    }

    public function getExchangeRate($from=null, $to=null)
    {
        $response = $this->get("currencies/exchange_rates", array());

        if ($from !== null && $to !== null) {
            return $response->{"{$from}_to_{$to}"};
        } else {
            return $response;
        }
    }

    public function getTransactions($page=0)
    {
        return $this->getPaginatedResource("transactions", "transactions", "transaction", $page);
    }

    public function getOrders($page=0)
    {
        return $this->getPaginatedResource("orders", "orders", "order", $page);
    }

    public function getTransfers($page=0)
    {
        return $this->getPaginatedResource("transfers", "transfers", "transfer", $page);
    }

    public function getBuyPrice($qty=1)
    {
        return $this->get("prices/buy", array( "qty" => $qty ))->amount;
    }

    public function getSellPrice($qty=1)
    {
        return $this->get("prices/sell", array( "qty" => $qty ))->amount;
    }

    public function getTransaction($id)
    {
        return $this->get("transactions/" . $id, array())->transaction;
    }

    public function getOrder($id)
    {
        return $this->get("orders/" . $id, array())->order;
    }

    public function getUser()
    {
        return $this->get("users", array())->users[0]->user;
    }

}

class Coinbase_Requestor
{

    public function doCurlRequest($curl)
    {
        $response = curl_exec($curl);

        // Check for errors
        if($response === false) {
            $error = curl_errno($curl);
            $message = curl_error($curl);
            curl_close($curl);
            throw new Coinbase_ConnectionException("Network error " . $message . " (" . $error . ")");
        }

        // Check status code
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if($statusCode != 200) {
            throw new Coinbase_ApiException("Status code " . $statusCode, $statusCode, $response);
        }

        return array( "statusCode" => $statusCode, "body" => $response );
    }

}

class Coinbase_Rpc
{
    private $_requestor;
    private $authentication;

    public function __construct($requestor, $authentication)
    {
        $this->_requestor = $requestor;
        $this->_authentication = $authentication;
    }

    public function request($method, $url, $params)
    {
        // Create query string
        $queryString = http_build_query($params);
        $url = Coinbase::API_BASE . $url;

        // Initialize CURL
        $curl = curl_init();
        $curlOpts = array();

        // HTTP method
        $method = strtolower($method);
        if ($method == 'get') {
            $curlOpts[CURLOPT_HTTPGET] = 1;
            if ($queryString) {
                $url .= "?" . $queryString;
            }
        } else if ($method == 'post') {
            $curlOpts[CURLOPT_POST] = 1;
            $curlOpts[CURLOPT_POSTFIELDS] = $queryString;
        } else if ($method == 'delete') {
            $curlOpts[CURLOPT_CUSTOMREQUEST] = "DELETE";
            if ($queryString) {
                $url .= "?" . $queryString;
            }
        } else if ($method == 'put') {
            $curlOpts[CURLOPT_CUSTOMREQUEST] = "PUT";
            $curlOpts[CURLOPT_POSTFIELDS] = $queryString;
        }

        // Headers
        $headers = array('User-Agent: CoinbasePHP/v1');

        $auth = $this->_authentication->getData();

        // Get the authentication class and parse its payload into the HTTP header.
        $authenticationClass = get_class($this->_authentication);
        switch ($authenticationClass) {
            case 'Coinbase_OAuthAuthentication':
                // Use OAuth
                if(time() > $auth->tokens["expire_time"]) {
                    throw new Coinbase_TokensExpiredException("The OAuth tokens are expired. Use refreshTokens to refresh them");
                }

                $headers[] = 'Authorization: Bearer ' . $auth->tokens["access_token"];
                break;

            case 'Coinbase_ApiKeyAuthentication':
                // Use HMAC API key
                $microseconds = sprintf('%0.0f',round(microtime(true) * 1000000));

                $dataToHash =  $microseconds . $url;
                if (array_key_exists(CURLOPT_POSTFIELDS, $curlOpts)) {
                    $dataToHash .= $curlOpts[CURLOPT_POSTFIELDS];
                }
                $signature = hash_hmac("sha256", $dataToHash, $auth->apiKeySecret);

                $headers[] = "ACCESS_KEY: {$auth->apiKey}";
                $headers[] = "ACCESS_SIGNATURE: $signature";
                $headers[] = "ACCESS_NONCE: $microseconds";
                break;

            case 'Coinbase_SimpleApiKeyAuthentication':
                // Use Simple API key
                // Warning! This authentication mechanism is deprecated
                $headers[] = 'Authorization: api_key ' . $auth->apiKey;
                break;

            default:
                throw new Coinbase_ApiException("Invalid authentication mechanism");
                break;
        }

        // CURL options
        $curlOpts[CURLOPT_URL] = $url;
        $curlOpts[CURLOPT_HTTPHEADER] = $headers;
        $curlOpts[CURLOPT_CAINFO] = dirname(__FILE__) . '/ca-coinbase.crt';
        $curlOpts[CURLOPT_RETURNTRANSFER] = true;

        // Do request
        curl_setopt_array($curl, $curlOpts);
        $response = $this->_requestor->doCurlRequest($curl);

        // Decode response
        try {
            $json = json_decode($response['body']);
        } catch (Exception $e) {
            throw new Coinbase_ConnectionException("Invalid response body", $response['statusCode'], $response['body']);
        }
        if($json === null) {
            throw new Coinbase_ApiException("Invalid response body", $response['statusCode'], $response['body']);
        }
        if(isset($json->error)) {
            throw new Coinbase_ApiException($json->error, $response['statusCode'], $response['body']);
        } else if(isset($json->errors)) {
            throw new Coinbase_ApiException(implode($json->errors, ', '), $response['statusCode'], $response['body']);
        }

        return $json;
    }
}

abstract class Coinbase_Authentication
{
    abstract public function getData();
}

class Coinbase_ApiKeyAuthentication extends Coinbase_Authentication
{
    private $_apiKey;
    private $_apiKeySecret;

    public function __construct($apiKey, $apiKeySecret)
    {
        $this->_apiKey = $apiKey;
        $this->_apiKeySecret = $apiKeySecret;
    }

    public function getData()
    {
        $data = new stdClass();
        $data->apiKey = $this->_apiKey;
        $data->apiKeySecret = $this->_apiKeySecret;
        return $data;
    }
}

class Coinbase_OAuth
{
    private $_clientId;
    private $_clientSecret;
    private $_redirectUri;

    public function __construct($clientId, $clientSecret, $redirectUri)
    {
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;
        $this->_redirectUri = $redirectUri;
    }

    public function createAuthorizeUrl($scope)
    {
        $url = "https://coinbase.com/oauth/authorize?response_type=code" .
            "&client_id=" . urlencode($this->_clientId) .
            "&redirect_uri=" . urlencode($this->_redirectUri) .
            "&scope=" . $scope;

        foreach(func_get_args() as $key => $scope)
        {
            if(0 == $key) {
                // First scope was already appended
            } else {
                $url .= "+" . urlencode($scope);
            }
        }

        return $url;
    }

    public function refreshTokens($oldTokens)
    {
        return $this->getTokens($oldTokens["refresh_token"], "refresh_token");
    }

    public function getTokens($code, $grantType='authorization_code')
    {
        $postFields["grant_type"] = $grantType;
        $postFields["redirect_uri"] = $this->_redirectUri;
        $postFields["client_id"] = $this->_clientId;
        $postFields["client_secret"] = $this->_clientSecret;

        if("refresh_token" === $grantType) {
            $postFields["refresh_token"] = $code;
        } else {
            $postFields["code"] = $code;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($curl, CURLOPT_URL, 'https://coinbase.com/oauth/token');
        curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . '/ca-coinbase.crt');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('User-Agent: CoinbasePHP/v1'));

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if($response === false) {
            $error = curl_errno($curl);
            $message = curl_error($curl);
            curl_close($curl);
            throw new Coinbase_ConnectionException("Could not get tokens - network error " . $message . " (" . $error . ")");
        }
        if($statusCode !== 200) {
            throw new Coinbase_ApiException("Could not get tokens - code " . $statusCode, $statusCode, $response);
        }
        curl_close($curl);

        try {
            $json = json_decode($response);
        } catch (Exception $e) {
            throw new Coinbase_ConnectionException("Could not get tokens - JSON error", $statusCode, $response);
        }

        return array(
            "access_token" => $json->access_token,
            "refresh_token" => $json->refresh_token,
            "expire_time" => time() + 7200 );
    }
}

class Coinbase_OAuthAuthentication extends Coinbase_Authentication
{
    private $_oauth;
    private $_tokens;

    public function __construct($oauth, $tokens)
    {
        $this->_oauth = $oauth;
        $this->_tokens = $tokens;
    }

    public function getData()
    {
        $data = new stdClass();
        $data->oauth = $this->_oauth;
        $data->tokens = $this->_tokens;
        return $data;
    }
}

class Coinbase_Exception extends Exception
{
    public function __construct($message, $http_code=null, $response=null)
    {
        parent::__construct($message);
        $this->http_code = $http_code;
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }
}

class Coinbase_SimpleApiKeyAuthentication extends Coinbase_Authentication
{
    private $_apiKey;

    public function __construct($apiKey)
    {
        $this->_apiKey = $apiKey;
    }

    public function getData()
    {
        $data = new stdClass();
        $data->apiKey = $this->_apiKey;
        return $data;
    }
}

class Coinbase_ApiException extends Coinbase_Exception
{
}

class Coinbase_ConnectionException extends Coinbase_Exception
{
}

class Coinbase_TokensExpiredException extends Coinbase_Exception
{
}