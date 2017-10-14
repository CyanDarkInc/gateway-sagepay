<?php
/**
 * SagePay API.
 *
 * @package blesta
 * @subpackage blesta.components.modules.sagepay
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class SagepayApi
{
    /**
     * @var string The vendor name
     */
    private $vendor_name;

    /**
     * @var string The integration key
     */
    private $integration_key;

    /**
     * @var string The integration password
     */
    private $integration_password;

    /**
     * @var bool True to use sandbox api
     */
    private $sandbox;

    /**
     * Initializes the class.
     *
     * @param string $vendor_name The vendor name
     * @param string $integration_key The integration key
     * @param string $integration_password The integration password
     * @param mixed $sandbox
     */
    public function __construct($vendor_name, $integration_key, $integration_password, $sandbox = false)
    {
        $this->vendor_name = $vendor_name;
        $this->integration_key = $integration_key;
        $this->integration_password = $integration_password;
        $this->sandbox = $sandbox;
    }

    /**
     * Send a request to the SagePay API.
     *
     * @param string $method Specifies the endpoint and method to invoke
     * @param array $params The parameters to include in the api call
     * @param string $merchant_session_key The merchant session key (only for bearer authentication)
     * @return stdClass An object containing the api response
     */
    private function apiRequest($method, array $params = [], $merchant_session_key = null)
    {
        $url = 'https://pi-' . ($this->sandbox ? 'test' : 'live') . '.sagepay.com/api/v1/';

        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

        // Set authentication details
        if (empty($merchant_session_key)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->integration_key . ':' . $this->integration_password)
            ]);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $merchant_session_key
            ]);
        }

        // Execute request
        curl_setopt($ch, CURLOPT_URL, 'https://pi-test.sagepay.com/api/v1/' . trim($method, '/'));
        $data = json_decode(curl_exec($ch));
        curl_close($ch);

        return $data;
    }

    /**
     * Create a merchant session key.
     *
     * @param string $transaction_id The transaction id
     * @return stdClass An object contaning the request details
     */
    public function getMerchantSessionKey()
    {
        return $this->apiRequest('/merchant-session-keys', ['vendorName' => $this->vendor_name]);
    }

    /**
     * Generates a card identifier.
     *
     * @param string $card_holder The card holder name
     * @param string $card_number The card number
     * @param int $exp_date The card expiration date, in MMYY format
     * @param int $security_code The card security code
     * @return stdClass An object containing the card identifier details
     */
    public function getCardIdentifier($card_holder, $card_number, $exp_date, $security_code)
    {
        $merchant_session_key = $this->getMerchantSessionKey();

        $response = $this->apiRequest('/card-identifiers', ['cardDetails' => [
            'cardholderName' => $card_holder,
            'cardNumber' => $card_number,
            'expiryDate' => $exp_date,
            'securityCode' => $security_code
        ]], $merchant_session_key->merchantSessionKey);

        return (object) array_merge(['merchantSessionKey' => $merchant_session_key->merchantSessionKey], (array) $response);
    }

    public function buildPayment($card_identifier, $amount, $currency, $address, $transaction_id = null)
    {
        // Generate a unique ID
        $unique_id = uniqid();

        // Build parameters array
        $params = [
            'transactionType' => 'Payment',
            'paymentMethod' => [
                'card' => [
                    'merchantSessionKey' => $card_identifier->merchantSessionKey,
                    'cardIdentifier' => $card_identifier->cardIdentifier
                ]
            ],
            'vendorTxCode' => !empty($transaction_id) ? $transaction_id : $unique_id,
            'amount' => (int) strtr($amount, ['.' => '', ',' => '']),
            'currency' => $currency,
            'description' => !empty($transaction_id) ? $transaction_id : $unique_id,
            'apply3DSecure' => 'Disable',
            'customerFirstName' => $address['first_name'],
            'customerLastName' => $address['last_name'],
            'billingAddress' => [
                'address1' => $address['address1'],
                'city' => $address['city'],
                'postalCode' => $address['zip'],
                'country' => $address['country']['alpha2']
            ],
            'entryMethod' => 'Ecommerce'
        ];

        return $this->apiRequest('/transactions', $params);
    }
}

$app = new SagepayApi('sandbox', 'hJYxsw7HLbj40cB8udES8CDRFLhuJ8G54O6rDpUXvE6hYDrria', 'o2iHSrFybYMZpmWOQMuhsXP52V4fBtpuSDshrKDSWsBY1OiN6hwd9Kb12z4j5Us5u', true);
$card_identifier = $app->getCardIdentifier('John Doe', '4929000005559', '1019', '123');
$payment = $app->buildPayment($card_identifier, '100.00', 'GBP', [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'address1' => '407 St. John Street',
    'city' => 'London',
    'zip' => 'EC1V 4AB',
    'country' => [
        'alpha2' => 'GB'
    ]
]);
print_r($payment);
