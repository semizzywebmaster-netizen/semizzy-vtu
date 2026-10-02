# Universal Provider Engine — Core Contract

## Current data model

- api_providers stores provider configuration, verification/integration states, capabilities, environment, priority, timeout and encrypted credentials.
- provider_service_mappings maps a provider to multiple service keys and allows one service key to map to many providers.
- Credentials use Laravel's encrypted array cast. Set a stable, protected APP_KEY before storing credentials; losing APP_KEY makes encrypted values unreadable.
- Soft deletion preserves provider configuration history. Do not hard-delete records referenced by transactions or audit history.

## Safety invariants

1. Provider directory presence is not integration proof.
2. Only a provider with both verification_status and integration_status set to live_verified can enter the eligible-for-new-transactions query.
3. Disabled or paused providers are not eligible for new transactions.
4. Adapter implementations must declare capabilities; do not infer unsupported refund, reversal, webhook or status operations.
5. A submitted request with an ambiguous timeout remains UNKNOWN/PENDING until status is queried or reconciled.
6. Never fail over to another provider until duplicate-fulfilment risk has been evaluated.
7. Mask credentials in all views, logs and exports. Never return the credentials field to a frontend client.
8. Use a separate sandbox configuration and test with non-production credentials.

## Initial directory candidates

OTOBILL, Ufriends IT, Husmodataapi, VTpass, IACafe, Bigisub, Subpadi, CheapDataHub, VTU.ng, Blessdata, Fonpay, Ogdams SimHosting, Sim Hoster, 2FAST, VTUCreator, Reloadly, Interswitch, Paystack, Flutterwave, Dojah, Prembly and Termii.

Candidates must be researched against official documentation and verified individually. Do not fabricate API URLs, prices, capabilities, test results or credentials. Mark unknown details as pending verification. This list is not proof of commercial availability or official integration support.

## Status meanings

- draft: local record only.
- pending_verification: official documentation or account/capability evidence is incomplete.
- configured: required non-secret settings exist.
- sandbox_tested: an authorised sandbox check actually succeeded.
- production_verification_pending: production credentials exist but verification is incomplete.
- live_verified: authorised production verification and required capability checks succeeded.
- unhealthy: health checks indicate an operational issue.
- disabled / archived: excluded from routing.

A status transition to live_verified must be guarded by an authorised operational workflow and recorded in an audit log. Do not provide a self-service UI shortcut that bypasses this check.
