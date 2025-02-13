<?php

/**
 * Service class for handling PayPal integrations and operations.
 * 
 * This class provides methods for managing PayPal transactions, subscriptions,
 * agreements, and other PayPal-related functionalities.
 */
class PayPalBillingAgreementService {
  // PayPal API configuration
  private static $authInstance = null;
  private static $config = null;

  // Initialize PayPal API configuration
  private static function initConfig() {
    if (self::$config === null) {
      self::$config = [
        'SANDBOX_BASE_URL' => 'https://api-m.sandbox.paypal.com/v1',
        'CLIENT_ID' => 'YOUR_CLIENT_ID',
        'SECRET' => 'YOUR_SECRET'
      ];
    }
  }

  // Get PayPal API authentication instance
  private static function getAuthInstance() {
    self::initConfig();
    if (self::$authInstance) {
      return self::$authInstance;
    }

    $url = self::$config['SANDBOX_BASE_URL'] . '/oauth2/token';
    $auth = base64_encode(self::$config['CLIENT_ID'] . ':' . self::$config['SECRET']);
    $headers = [
      'Authorization: Basic ' . $auth,
      'Content-Type: application/x-www-form-urlencoded',
    ];
    $data = 'grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
      throw new Exception('Authentication failed');
    }

    $responseData = json_decode($response, true);
    if (!isset($responseData['access_token'])) {
      throw new Exception('Failed to retrieve access token');
    }

    self::$authInstance = [
      'base_url' => self::$config['SANDBOX_BASE_URL'],
      'headers' => [
        'Authorization: Bearer ' . $responseData['access_token'],
        'Content-Type: application/json'
      ],
    ];

    return self::$authInstance;
  }

  // Create a billing agreement token
  public static function createBillingAgreementToken($returnUrl, $cancelUrl) {
    $instance = self::getAuthInstance();
    $url = $instance['base_url'] . '/billing-agreements/agreement-tokens';
    $data = json_encode([
      'description' => 'Flexible Payment Agreement',
      'payer' => ['payment_method' => 'PAYPAL'],
      'plan' => [
        'type' => 'MERCHANT_INITIATED_BILLING',
        'merchant_preferences' => [
          'return_url' => $returnUrl,
          'cancel_url' => $cancelUrl,
        ],
      ],
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $instance['headers']);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
      throw new Exception('Failed to create billing agreement token');
    }

    $responseData = json_decode($response, true);
    foreach ($responseData['links'] as $link) {
      if ($link['rel'] === 'approval_url') {
        return $link['href'];
      }
    }

    throw new Exception('Approval URL not found');
  }

  // Execute a billing agreement
  public static function executeBillingAgreement($token) {
    $instance = self::getAuthInstance();
    $url = $instance['base_url'] . '/billing-agreements/agreements';
    $data = json_encode(['token_id' => $token]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $instance['headers']);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
      throw new Exception('Failed to execute billing agreement');
    }

    $responseData = json_decode($response, true);
    if (!isset($responseData['id'])) {
      throw new Exception('Billing Agreement ID not found');
    }

    return $responseData['id'];
  }

  // Charge a customer for a billing agreement
  public static function chargeCustomer($agreementId, $amount, $currency = 'USD') {
    $instance = self::getAuthInstance();
    $url = $instance['base_url'] . "/payments/billing-agreements/$agreementId/payments";
    $data = json_encode([
      'amount' => [
        'value' => $amount,
        'currency' => $currency
      ],
      'note' => 'Charge at delivery time',
      'custom_id' => uniqid()
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $instance['headers']);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
      throw new Exception('Payment failed');
    }

    return json_decode($response, true);
  }
}

// Usage example:
try {
    $approvalUrl = PayPalBillingAgreementService::createBillingAgreementToken('https://example.com/success', 'https://example.com/cancel');
    echo "Approval URL: " . $approvalUrl . PHP_EOL;

    // After approval, execute agreement
    $agreementId = PayPalBillingAgreementService::executeBillingAgreement('TOKEN_FROM_RETURN_URL');
    echo "Agreement ID: " . $agreementId . PHP_EOL;

    // Charge the customer
    $payment = PayPalBillingAgreementService::chargeCustomer($agreementId, '55.00');
    echo "Payment Response: " . json_encode($payment) . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
