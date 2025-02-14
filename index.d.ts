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
declare class PayPalBillingAgreementService {
  /**
   * Configure PayPal API
   *
   * @param clientId - PayPal API Client ID
   * @param secret - PayPal API Secret
   * @param sandbox - Whether to use sandbox environment (default: true)
   */
  static configure(clientId: string, secret: string, sandbox?: boolean): void;

  /**
   * Get PayPal API authentication instance
   *
   * @returns Authentication instance containing base URL and headers
   * @throws If authentication fails
   */
  static getAuthInstance(): Promise<{
    baseURL: string;
    headers: { Authorization: string; "Content-Type": string };
  }>;

  /**
   * Create a billing agreement token
   *
   * @param returnUrl - Success URL after agreement approval
   * @param cancelUrl - Cancel URL if user cancels agreement
   * @returns Approval URL for the billing agreement
   * @throws If token creation fails
   */
  static createBillingAgreementToken(
    returnUrl: string,
    cancelUrl: string
  ): Promise<string>;

  /**
   * Execute a billing agreement
   *
   * @param token - Billing agreement token
   * @returns Billing agreement details
   * @throws If execution fails
   */
  static executeBillingAgreement(
    token: string
  ): Promise<{ id: string; payer_id: string }>;

  /**
   * Charge a customer using a reference transaction
   *
   * @param payerId - Payer ID
   * @param agreementId - Billing agreement ID
   * @param amount - Amount to charge
   * @param currency - Currency code (default: USD)
   * @returns Payment response
   * @throws If charging fails
   */
  static chargeCustomer(
    payerId: string,
    agreementId: string,
    amount: string,
    currency?: string
  ): Promise<{
    id: string;
    status: string;
    purchase_units: Array<{
      amount: { currency_code: string; value: string };
      description: string;
    }>;
  }>;
}

export default PayPalBillingAgreementService;
