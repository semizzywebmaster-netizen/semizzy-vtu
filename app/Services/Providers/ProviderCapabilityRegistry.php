<?php

namespace App\\Services\\Providers;

use App\\Models\\ApiProvider;
use InvalidArgumentException;

class ProviderCapabilityRegistry
{
    public const SUPPORTED_CAPABILITIES = [
        'balance_inquiry',
        'catalogue_retrieval',
        'transaction_initiation',
        'transaction_status',
        'refund',
        'reversal',
        'webhook',
        'health_check',
        'kyc_verification',
        'identity_document_verify',
        'identity_status',
        'number_reservation',
        'number_release',
        'inventory_sync',
        'sms_send',
        'sms_status',
        'network_lookup',
        'social_account_purchase',
        'foreign_number_purchase',
        'foreign_number_status',
        'foreign_number_sms',

        // Investment, securities and market-data operations.
        'investment_product_catalogue',
        'investment_quote',
        'investment_subscribe',
        'investment_redemption',
        'investment_status',
        'portfolio_inquiry',
        'portfolio_valuation',
        'stock_market_data',
        'stock_quote',
        'stock_order_create',
        'stock_order_cancel',
        'stock_order_status',
        'stock_positions',

        // FX rates/conversion are distinct from FX trade execution.
        'fx_rate_quote',
        'fx_conversion_quote',
        'fx_conversion_execute',
        'fx_conversion_status',
        'fx_trade_create',
        'fx_trade_status',

        // Lending and repayment partners.
        'loan_offer_quote',
        'loan_application_submit',
        'loan_application_status',
        'loan_disbursement_status',
        'loan_repayment_status',

        // Crypto exchange/trading is distinct from crypto payment acceptance.
        'crypto_market_data',
        'crypto_order_create',
        'crypto_order_cancel',
        'crypto_order_status',
        'crypto_balance_inquiry',
        'crypto_deposit_status',
        'crypto_withdrawal_create',
        'crypto_withdrawal_status',

        // Additional service-provider operations.
        'gift_card_catalogue',
        'gift_card_purchase',
        'gift_card_status',
        'education_catalogue',
        'education_purchase',
        'education_purchase_status',
        'exam_pin_purchase',
        'travel_search',
        'travel_booking_create',
        'travel_booking_status',
        'travel_cancel',
        'insurance_quote',
        'insurance_policy_issue',
        'insurance_policy_status',
        'insurance_claim_submit',
        'insurance_claim_status',
        'card_issue',
        'card_fund',
        'card_status',
        'card_transaction_status',
        'card_freeze',
        'card_unfreeze',
        'card_transaction_list',
        'government_application_submit',
        'government_application_status',
        'cac_name_search',
        'cac_filing_submit',
        'cac_application_status',
        'domain_register',
        'domain_status',
        'dns_manage',
        'hosting_provision',
        'hosting_status',
        'marketplace_catalogue',
        'marketplace_order_create',
        'marketplace_order_status',
        'shipping_quote',
        'fulfillment_status',
    ];

    /**
     * Irreversible or money-moving operations require a matching status/requery
     * capability, or the generic transaction_status operation with a typed payload.
     */
    private const STATUS_REQUIREMENTS = [
        'investment_subscribe' => 'investment_status',
        'investment_redemption' => 'investment_status',
        'stock_order_create' => 'stock_order_status',
        'fx_conversion_execute' => 'fx_conversion_status',
        'fx_trade_create' => 'fx_trade_status',
        'loan_application_submit' => 'loan_application_status',
        'crypto_order_create' => 'crypto_order_status',
        'crypto_withdrawal_create' => 'crypto_withdrawal_status',
        'gift_card_purchase' => 'gift_card_status',
        'education_purchase' => 'education_purchase_status',
        'exam_pin_purchase' => 'transaction_status',
        'travel_booking_create' => 'travel_booking_status',
        'insurance_policy_issue' => 'insurance_policy_status',
        'insurance_claim_submit' => 'insurance_claim_status',
        'card_issue' => 'card_status',
        'card_fund' => 'card_transaction_status',
        'government_application_submit' => 'government_application_status',
        'cac_filing_submit' => 'cac_application_status',
        'domain_register' => 'domain_status',
        'hosting_provision' => 'hosting_status',
        'marketplace_order_create' => 'marketplace_order_status',
    ];

    public function validate(ApiProvider $provider): void
    {
        $capabilities = $provider->capabilities ?? [];
        $unknown = array_values(array_diff($capabilities, self::SUPPORTED_CAPABILITIES));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported provider capabilities: '.implode(', ', $unknown));
        }

        if (in_array('transaction_initiation', $capabilities, true)
            && ! in_array('transaction_status', $capabilities, true)) {
            throw new InvalidArgumentException('Transaction initiation requires status inquiry or an explicitly reviewed reconciliation adapter.');
        }

        foreach (self::STATUS_REQUIREMENTS as $operation => $specificStatus) {
            if (in_array($operation, $capabilities, true)
                && ! in_array($specificStatus, $capabilities, true)
                && ! in_array('transaction_status', $capabilities, true)) {
                throw new InvalidArgumentException(
                    $operation.' requires '.$specificStatus.' or generic transaction_status requery support.'
                );
            }
        }

        if ($provider->enabled
            && $provider->integration_status === 'live_verified'
            && $provider->verification_status !== 'live_verified') {
            throw new InvalidArgumentException('A provider cannot be live-enabled before verification.');
        }
    }

    public function supports(ApiProvider $provider, string $operation): bool
    {
        return in_array($operation, $provider->capabilities ?? [], true);
    }
}
