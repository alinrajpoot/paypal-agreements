import axios from "axios";

/**
 * PayPal Billing Agreement Service
 *
 * A service class that handles PayPal Billing Agreement operations including:
 * - Creating billing agreement tokens
 * - Executing billing agreements
 * - Processing reference transactions
 *
 * @class PayPalBillingAgreementService
 */
class PayPalBillingAgreementService {
  /** @type {Object|null} PayPal API configuration */
  static config = null;

  /** @type {Object|null} PayPal API authentication instance */
  static authInstance = null;

  /**
   * Configure PayPal API
   *
   * @param {string} clientId - PayPal API Client ID
   * @param {string} secret - PayPal API Secret
   * @param {boolean} [sandbox=true] - Whether to use sandbox environment
   */
  static configure(clientId, secret, sandbox = true) {
    this.config = {
      BASE_URL: sandbox
        ? "https://api-m.sandbox.paypal.com"
        : "https://api-m.paypal.com",
      CLIENT_ID: clientId,
      SECRET: secret,
    };
  }

  /**
   * Get PayPal API authentication instance
   *
   * @returns {Promise<Object>} Authentication instance containing base URL and headers
   * @throws {Error} If authentication fails
   */
  static async getAuthInstance() {
    if (!this.config) {
      throw new Error(
        "PayPal configuration not initialized. Call configure() first."
      );
    }

    if (this.authInstance) {
      return this.authInstance;
    }

    try {
      const { data } = await axios.post(
        `${this.config.BASE_URL}/v1/oauth2/token`,
        "grant_type=client_credentials",
        {
          auth: {
            username: this.config.CLIENT_ID,
            password: this.config.SECRET,
          },
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
        }
      );

      this.authInstance = axios.create({
        baseURL: this.config.BASE_URL,
        headers: {
          Authorization: `Bearer ${data.access_token}`,
          "Content-Type": "application/json",
        },
      });

      return this.authInstance;
    } catch (error) {
      throw new Error(`Authentication failed: ${error.message}`);
    }
  }

  /**
   * Create a billing agreement token
   *
   * @param {string} returnUrl - Success URL after agreement approval
   * @param {string} cancelUrl - Cancel URL if user cancels agreement
   * @returns {Promise<string>} Approval URL for the billing agreement
   * @throws {Error} If token creation fails
   */
  static async createBillingAgreementToken(returnUrl, cancelUrl) {
    try {
      const instance = await this.getAuthInstance();
      const { data } = await instance.post(
        "/v1/billing-agreements/agreement-tokens",
        {
          name: "Post Payment Profile",
          description: "Flexible Payment Agreement",
          start_date: new Date(Date.now() + 60000).toISOString(),
          payer: { payment_method: "PAYPAL" },
          plan: {
            type: "MERCHANT_INITIATED_BILLING",
            merchant_preferences: {
              return_url: returnUrl,
              cancel_url: cancelUrl,
              accepted_pymt_type: "ANY",
              setup_fee: { value: "0.00", currency_code: "USD" },
            },
          },
        }
      );

      const approvalUrl = data.links.find(
        (link) => link.rel === "approval_url"
      );
      if (!approvalUrl) {
        throw new Error("Approval URL not found");
      }

      return approvalUrl.href;
    } catch (error) {
      throw new Error(
        `Failed to create billing agreement token: ${error.message}`
      );
    }
  }

  /**
   * Execute a billing agreement
   *
   * @param {string} token - Billing agreement token
   * @returns {Promise<Object>} Billing agreement details
   * @throws {Error} If execution fails
   */
  static async executeBillingAgreement(token) {
    try {
      const instance = await this.getAuthInstance();
      const { data } = await instance.post(
        "/v1/billing-agreements/agreements",
        { token_id: token }
      );

      if (!data.id) {
        throw new Error("Billing Agreement ID not found");
      }

      return { id: data.id, payer_id: data.payer.payer_info.payer_id };
    } catch (error) {
      throw new Error(`Failed to execute billing agreement: ${error.message}`);
    }
  }

  /**
   * Charge a customer using a reference transaction
   *
   * @param {string} payerId - Payer ID
   * @param {string} agreementId - Billing agreement ID
   * @param {string} amount - Amount to charge
   * @param {string} [currency="USD"] - Currency code
   * @returns {Promise<Object>} Payment response
   * @throws {Error} If charging fails
   */
  static async chargeCustomer(payerId, agreementId, amount, currency = "USD") {
    try {
      const instance = await this.getAuthInstance();

      // Step 1: Convert Billing Agreement to Payment Token (v3)
      const tokenResponse = await instance.post("/v3/vault/payment-tokens", {
        payment_source: {
          token: { type: "BILLING_AGREEMENT", id: agreementId },
        },
      });

      if (!tokenResponse.data.id) {
        throw new Error("Payment token not returned");
      }

      const paymentToken = tokenResponse.data.id;

      // Step 2: Create an Order using the v2 Orders API
      const orderResponse = await instance.post("/v2/checkout/orders", {
        intent: "CAPTURE",
        purchase_units: [
          {
            amount: { currency_code: currency, value: amount },
            description: "Charge amount at delivery",
          },
        ],
        payment_source: {
          token: { id: paymentToken, type: "PAYMENT_METHOD_TOKEN" },
        },
      });

      if (!orderResponse.data.id) {
        throw new Error("Order ID not found");
      }

      return orderResponse.data;
    } catch (error) {
      throw new Error(`Payment failed: ${error.message}`);
    }
  }
}

export default PayPalBillingAgreementService;

// Usage example:
// PayPalBillingAgreementService.configure('your-client-id', 'your-secret');
// try {
//   const approvalUrl = await PayPalBillingAgreementService.createBillingAgreementToken(
//     'https://example.com/success',
//     'https://example.com/cancel'
//   );
//   console.log('Approval URL:', approvalUrl);

//   const agreementDetails = await PayPalBillingAgreementService.executeBillingAgreement('TOKEN_FROM_RETURN_URL');
//   console.log('Agreement Details:', agreementDetails);

//   const paymentResponse = await PayPalBillingAgreementService.chargeCustomer(
//     agreementDetails.payer_id,
//     agreementDetails.id,
//     '55.00'
//   );
//   console.log('Payment Response:', paymentResponse);
// } catch (error) {
//   console.error(error);
// }
