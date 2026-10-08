# Stocks & Investments Marketplace — Research & Safety Boundary

## Scope
This roadmap position extends the existing `investments.wealth` addon rather than creating a duplicate addon directory.

The marketplace is designed to support:
- investment product discovery and subscription;
- listed securities/instruments catalogue;
- market-price snapshots supplied only by an approved data source;
- licensed broker/provider integrations for actual order execution;
- user portfolios, holdings and transaction history;
- dividends/corporate-action records where a trusted provider supplies them;
- admin/provider controls and full auditability.

## Nigeria regulatory boundary
The platform must not pretend to be a stock exchange, broker, investment adviser, fund manager, or crowdfunding intermediary unless the business and the relevant provider are appropriately authorised/registered.

NGX states that trading listed securities is executed through its Trading License Holders. NGX also states that redistribution of its real-time market data requires an appropriate distribution licence. SEC Nigeria states that entities providing/promoting investment services in Nigeria's capital market must be registered, and its digital-intermediary rules include record retention, client protections, governance and technology-risk requirements.

Therefore:
- no fake live prices;
- no simulated trade execution presented as real;
- no hard-coded broker;
- no fabricated securities or returns;
- market data must carry source, timestamp and freshness status;
- trade execution remains disabled until a real approved broker/provider is configured and verified;
- provider responses must be reconciled against requested symbol, side, quantity, price/amount and order/reference identifiers;
- all investment/trading actions require audit records and appropriate KYC/eligibility controls.

## Product categories
The marketplace will distinguish at minimum:
1. Managed/term investment products (existing investment-products flow).
2. Listed equities.
3. ETFs.
4. Bonds/fixed-income instruments.
5. Other securities/instruments only when an approved provider and regulatory basis exist.

Crowdfunding is not silently included in the stock marketplace. It requires its own SEC-regulated intermediary model and controls.

## Data model principle
Keep catalogue data separate from execution data:
- security/instrument = what exists in the market;
- quote = time-stamped market observation;
- broker/provider = who can execute or supply data;
- order = user's requested trade;
- execution = provider-confirmed fill;
- holding = reconciled position;
- corporate action = dividend/split/interest/etc.;
- investment account = existing fixed/managed investment position.

## Source references
- SEC Nigeria — FinTech rules and digital intermediary controls.
- SEC Nigeria — registered operator directory and public warnings.
- NGX — Trading License Holders / find-a-broker.
- NGX — market-data vendor/licensing information.
- CSCS — investor account/portfolio services.
