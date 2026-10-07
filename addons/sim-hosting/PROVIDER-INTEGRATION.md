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
