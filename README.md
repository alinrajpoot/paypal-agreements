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

4. Build the project:

   ```sh
   npm run build
   ```

5. Run the service:

   ```sh
   npm start
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

// Configure the PayPal service
PayPalBillingAgreementService.configure("your-client-id", "your-secret");

try {
  // Create a billing agreement token
  const approvalUrl =
    await PayPalBillingAgreementService.createBillingAgreementToken(
      "https://example.com/success",
      "https://example.com/cancel"
    );
  console.log("Approval URL:", approvalUrl);

  // Execute the billing agreement after approval
  const agreementDetails =
    await PayPalBillingAgreementService.executeBillingAgreement(
      "TOKEN_FROM_RETURN_URL"
    );
  console.log("Agreement Details:", agreementDetails);

  // Charge the customer
  const paymentResponse = await PayPalBillingAgreementService.chargeCustomer(
    agreementDetails.payer_id,
    agreementDetails.id,
    "55.00"
  );
  console.log("Payment Response:", paymentResponse);
} catch (error) {
  console.error(error);
}
```

### PHP Example

```php
require_once 'paypal.php';

// Configure the PayPal service
PayPal\BillingAgreement\PayPalBillingAgreementService::configure('your-client-id', 'your-secret');

try {
    if (!isset($_GET['returnUrl']) && !isset($_GET['agreement_id'])) {
        // Create a billing agreement token
        $approvalUrl = PayPal\BillingAgreement\PayPalBillingAgreementService::createBillingAgreementToken(
            'http://localhost/paypal.php?returnUrl=success',
            'http://localhost/paypal.php?returnUrl=cancel'
        );
        echo "Approval URL: <a href=\"$approvalUrl\">" . $approvalUrl . "</a>";
    }

    if (isset($_GET['returnUrl']) && $_GET['returnUrl'] == 'success'
         && isset($_GET['token']) && !empty($_GET['token'])
         && isset($_GET['ba_token']) && !empty($_GET['ba_token'])) {
        // Execute the billing agreement after approval
        $agreement = PayPal\BillingAgreement\PayPalBillingAgreementService::executeBillingAgreement($_GET['ba_token']);
        echo "Agreement ID: " . $agreement['id'] . "<br>";
        echo "Charge: <a href=\"paypal.php?payer_id=" . $agreement['payer_id'] . "&agreement_id=" . $agreement['id'] . "\">Click here to charge</a><br>";
    }

    if (isset($_GET['agreement_id']) && !empty($_GET['agreement_id']) && isset($_GET['payer_id']) && !empty($_GET['payer_id'])) {
        // Charge the customer using the reference transaction approach
        $payerId = $_GET['payer_id'];
        $agreementId = $_GET['agreement_id'];
        $payment = PayPal\BillingAgreement\PayPalBillingAgreementService::chargeCustomer($payerId, $agreementId, '55.00');
        echo "Payment Response: " . json_encode($payment);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## License

This project is licensed under the MIT License.
