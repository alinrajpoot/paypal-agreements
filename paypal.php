<?php

namespace PayPal\BillingAgreement;

/**
 * PayPal Billing Agreement Service
 * 
 * A service class that handles PayPal Billing Agreement operations including:
 * - Creating billing agreement tokens
 * - Executing billing agreements
 * - Processing reference transactions
 * 
 * @package PayPal\BillingAgreement
 * @author Your Name <your.email@example.com>
 * @license MIT
 */
class PayPalBillingAgreementService 
{
  /** @var array|null PayPal API configuration */
  private static $config = null;

  /** @var array|null PayPal API authentication instance */
  private static $authInstance = null;

  /**
   * PayPal API Configuration
   * 
   * @param string $clientId PayPal API Client ID
   * @param string $secret PayPal API Secret
   * @param bool $sandbox Whether to use sandbox environment (default: true)
   * @return void
   */
  public static function configure(string $clientId, string $secret, bool $sandbox = true): void 
  {
    self::$config = [
      'BASE_URL' => $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com',
      'CLIENT_ID' => $clientId,
      'SECRET' => $secret
    ];
  }

  /**
   * Get PayPal API authentication instance
   * 
   * @throws \RuntimeException If authentication fails
   * @return array Authentication instance containing base URL and headers
   */
  private static function getAuthInstance(): array 
  {
    if (!self::$config) {
      throw new \RuntimeException('PayPal configuration not initialized. Call configure() first.');
    }

    if (self::$authInstance) {
      return self::$authInstance;
    }

    $url = self::$config['BASE_URL'] . '/v1/oauth2/token';
    $auth = base64_encode(self::$config['CLIENT_ID'] . ':' . self::$config['SECRET']);
    
    $response = self::makeRequest($url, [
      'Authorization: Basic ' . $auth,
      'Content-Type: application/x-www-form-urlencoded'
    ], 'grant_type=client_credentials');

    if (!isset($response['access_token'])) {
      throw new \RuntimeException('Failed to retrieve access token');
    }

    self::$authInstance = [
      'base_url' => self::$config['BASE_URL'],
      'headers' => [
        'Authorization: Bearer ' . $response['access_token'],
        'Content-Type: application/json'
      ],
    ];

    return self::$authInstance;
  }

  /**
   * Create a billing agreement token
   * 
   * @param string $returnUrl Success URL after agreement approval
   * @param string $cancelUrl Cancel URL if user cancels agreement
   * @return string Approval URL for the billing agreement
   * @throws \RuntimeException If token creation fails
   */
  public static function createBillingAgreementToken(string $returnUrl, string $cancelUrl): string 
  {
    $instance = self::getAuthInstance();
    $url = $instance['base_url'] . '/v1/billing-agreements/agreement-tokens';

    $payload = [
      'name' => 'Post Payment Profile',
      'description' => 'Flexible Payment Agreement',
      'start_date' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 minute')),
      'payer' => ['payment_method' => 'PAYPAL'],
      'plan' => [
        'type' => 'MERCHANT_INITIATED_BILLING',
        'merchant_preferences' => [
          'return_url' => $returnUrl,
          'cancel_url' => $cancelUrl,
          'accepted_pymt_type' => 'ANY',
          'setup_fee' => [
            'value' => '0.00',
            'currency_code' => 'USD'
          ]
        ]
      ]
    ];

    $response = self::makeRequest($url, $instance['headers'], json_encode($payload));

    foreach ($response['links'] ?? [] as $link) {
      if ($link['rel'] === 'approval_url') {
        return $link['href'];
      }
    }

    throw new \RuntimeException('Approval URL not found');
  }

  /**
   * Execute a billing agreement
   * 
   * @param string $token Billing agreement token
   * @return array Billing agreement details
   * @throws \RuntimeException If execution fails
   */
  public static function executeBillingAgreement(string $token): array 
  {
    $instance = self::getAuthInstance();
    $url = $instance['base_url'] . '/v1/billing-agreements/agreements';
    $payload = json_encode(['token_id' => $token]);

    $response = self::makeRequest($url, $instance['headers'], $payload);

    if (!isset($response['id'])) {
      throw new \RuntimeException('Billing Agreement ID not found');
    }

    return [
      'id' => $response['id'],
      'payer_id' => $response['payer']['payer_info']['payer_id']
    ];
  }

  /**
   * Charge a customer using a reference transaction
   * 
   * @param string $payerId Payer ID
   * @param string $agreementId Billing agreement ID
   * @param string $amount Amount to charge
   * @param string $currency Currency code (default: USD)
   * @return array Payment response
   * @throws \RuntimeException If charging fails
   */
  public static function chargeCustomer(string $payerId, string $agreementId, string $amount, string $currency = 'USD'): array 
  {
    $instance = self::getAuthInstance();

    // Step 1: Convert Billing Agreement to Payment Token (v3)
    $v3Url = $instance['base_url'] . '/v3/vault/payment-tokens';
    $tokenPayload = json_encode([
      'payment_source' => [
        'token' => [
          'type' => 'BILLING_AGREEMENT',
          'id' => $agreementId
        ]
      ]
    ]);

    $tokenResponse = self::makeRequest($v3Url, $instance['headers'], $tokenPayload);

    if (!isset($tokenResponse['id'])) {
      throw new \RuntimeException('Payment token not returned');
    }

    $paymentToken = $tokenResponse['id'];

    // Step 2: Create an Order using the v2 Orders API
    $orderUrl = $instance['base_url'] . '/v2/checkout/orders';
    $orderPayload = json_encode([
      'intent' => 'CAPTURE',
      'purchase_units' => [
        [
          'amount' => [
            'currency_code' => $currency,
            'value' => $amount
          ],
          'description' => 'Charge amount at delivery'
        ]
      ],
      'payment_source' => [
        'token' => [
          'id' => $paymentToken,
          'type' => 'PAYMENT_METHOD_TOKEN'
        ]
      ]
    ]);

    $orderResponse = self::makeRequest($orderUrl, $instance['headers'], $orderPayload);

    if (!isset($orderResponse['id'])) {
      throw new \RuntimeException('Order ID not found');
    }

    return $orderResponse;
  }

  /**
   * Make HTTP request to PayPal API
   * 
   * @param string $url API endpoint
   * @param array $headers Request headers
   * @param string $data Request payload
   * @return array Response data
   * @throws \RuntimeException If request fails
   */
  private static function makeRequest(string $url, array $headers, string $data): array 
  {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
      throw new \RuntimeException('Request failed: ' . $error);
    }

    return json_decode($response, true) ?? [];
  }
}
