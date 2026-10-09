# SIM Hosting Provider Integration

SIM Hosting is an integration addon, not a duplicate VTU/SMS business engine.

Supported provider capabilities:
- Airtime purchase
- Data purchase
- Data catalogue/plan lookup
- SMS sending
- Provider balance
- Transaction status
- Transaction requery
- Provider webhook

The addon delegates provider execution to the Core Provider Engine. Credentials, authentication, encryption, priority, failover, idempotency, logging, pricing and security remain Core responsibilities.

Consumers:
- VTU & Digital Services can consume airtime_purchase, data_purchase, data_catalogue, transaction_status and transaction_requery.
- Bulk SMS & Communication can consume sms_send and provider balance/status operations.
- Future addons can consume only the capabilities they actually support.

No customer-facing SIM rental workflow is registered by this addon manifest. Legacy rental code remains isolated for migration compatibility and is not part of the active provider contract.

Provider capabilities must only be enabled after real provider documentation, credentials and a successful test/verification cycle.

## Core operation mapping

The addon-facing names above are not all Core provider operation names. The adapter translates airtime/data purchase to `transaction_initiation`, data catalogue to `catalogue_retrieval`, provider/SMS balance to `balance_inquiry`, and transaction requery to `transaction_status`. These aliases do not assert that a particular vendor supports each operation: the enabled provider mapping and Core provider capability must still explicitly allow it.

Number reservation and release are separate operations. Core's generic REST adapter accepts those operation names for configured endpoints, but each provider must have the corresponding endpoint, authentication, payload/response mapping, and tests configured before the capability is enabled. Webhook receipt is inbound and must be implemented through a verified webhook route/signature contract; it is not an outbound REST operation.
