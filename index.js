import axios from "axios";

const config = {
  SANDBOX_BASE_URL: "https://api-m.sandbox.paypal.com/v1",
  CLIENT_ID: process.env.PAYPAL_CLIENT_ID,
  SECRET: process.env.PAYPAL_SECRET,
};

// Singleton pattern for auth instance
let authInstance = null;

class PayPalBillingAgreementService {
  static async getAuthInstance() {
    if (authInstance) {
      return authInstance;
    }

    try {
      const { data } = await axios.post(
        `${config.SANDBOX_BASE_URL}/oauth2/token`,
        "grant_type=client_credentials",
        {
          auth: {
            username: config.CLIENT_ID,
            password: config.SECRET,
          },
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
        }
      );

      authInstance = axios.create({
        baseURL: config.SANDBOX_BASE_URL,
        headers: { Authorization: `Bearer ${data.access_token}` },
      });

      return authInstance;
    } catch (error) {
      throw new Error(`Authentication failed: ${error.message}`);
    }
  }

  static async createBillingAgreementToken(returnUrl, cancelUrl) {
    try {
      const instance = await this.getAuthInstance();
      const { data } = await instance.post(
        "/billing-agreements/agreement-tokens",
        {
          description: "Flexible Payment Agreement",
          payer: { payment_method: "PAYPAL" },
          plan: {
            type: "MERCHANT_INITIATED_BILLING",
            merchant_preferences: {
              return_url: returnUrl,
              cancel_url: cancelUrl,
            },
          },
        }
      );

      return data.links.find((link) => link.rel === "approval_url").href;
    } catch (error) {
      throw new Error(
        `Failed to create billing agreement token: ${error.message}`
      );
    }
  }

  static async executeBillingAgreement(token) {
    try {
      const instance = await this.getAuthInstance();
      const { data } = await instance.post("/billing-agreements/agreements", {
        token_id: token,
      });

      return data.id;
    } catch (error) {
      throw new Error(`Failed to execute billing agreement: ${error.message}`);
    }
  }

  static async chargeCustomer(agreementId, amount, currency = "USD") {
    try {
      const instance = await this.getAuthInstance();
      const { data } = await instance.post("/payments/payment", {
        intent: "sale",
        payer: { payment_method: "paypal" },
        transactions: [
          {
            amount: { total: amount, currency },
            description: "Final amount at delivery",
          },
        ],
        billing_agreement_id: agreementId,
      });

      return data;
    } catch (error) {
      throw new Error(`Payment failed: ${error.message}`);
    }
  }
}

export default PayPalBillingAgreementService;

// Usage example:
// const paypal = PayPalBillingAgreementService;
// try {
//   const approvalUrl = await paypal.createBillingAgreementToken(
//     'https://example.com/success',
//     'https://example.com/cancel'
//   );
//   const agreementId = await paypal.executeBillingAgreement('TOKEN_FROM_RETURN_URL');
//   const payment = await paypal.chargeCustomer(agreementId, '55.00');
// } catch (error) {
//   console.error(error);
// }
