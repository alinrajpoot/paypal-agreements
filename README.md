# PayPal Billing Agreement Service

This project provides a service class for handling PayPal integrations and operations, including creating billing agreement tokens, executing billing agreements, and charging customers.

## Installation

### JavaScript Version

1. Clone the repository:

   ```sh
   git clone https://github.com/alinrajpoot/paypal-agreements.git
   cd paypal-agreements
   ```

2. Install dependencies:

   ```sh
   npm install
   ```

3. Set up environment variables:

   ```sh
   export PAYPAL_CLIENT_ID=your-client-id
   export PAYPAL_SECRET=your-secret
   ```

4. Run the service:
   ```sh
   node index.js
   ```

### PHP Version

1. Clone the repository:

   ```sh
   git clone https://github.com/alinrajpoot/paypal-agreements.git
   cd paypal-agreements
   ```

2. Set up environment variables in your server configuration or `.env` file:

   ```sh
   PAYPAL_CLIENT_ID=your-client-id
   PAYPAL_SECRET=your-secret
   ```

3. Include the `paypal.php` file in your project and use the `PayPalBillingAgreementService` class.

## Usage

### JavaScript Example

```javascript
import PayPalBillingAgreementService from "./index.js";

const paypal = PayPalBillingAgreementService;

try {
  const approvalUrl = await paypal.createBillingAgreementToken(
    "https://example.com/success",
    "https://example.com/cancel"
  );
  console.log("Approval URL:", approvalUrl);

  const agreementId = await paypal.executeBillingAgreement(
    "TOKEN_FROM_RETURN_URL"
  );
  console.log("Agreement ID:", agreementId);

  const payment = await paypal.chargeCustomer(agreementId, "55.00");
  console.log("Payment Response:", payment);
} catch (error) {
  console.error(error);
}
```

### PHP Example

```php
require_once 'paypal.php';

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
```

## License

This project is licensed under the MIT License.
