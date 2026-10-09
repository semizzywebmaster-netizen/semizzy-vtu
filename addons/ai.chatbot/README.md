# AI Chatbot & Platform Assistant

Addon identifier: ai.chatbot. Category: AI & Automation. Core compatibility: >=2.0.0.

## Included
- Core addon manifest, permissions and route registration.
- OpenAI, Anthropic and Google Gemini provider adapters.
- Encrypted provider credentials, masked provider listing and live bounded connection tests.
- Priority fallback across explicitly enabled providers, timeout and output limits.
- Admin configuration for widget visibility, page allow/exclusion lists, message limits and bounded conversation context.
- Published knowledge items and keyword-ranked retrieval context.
- Persistent conversations with user ownership checks or random visitor-cookie ownership.
- Support escalation into the existing Core support-ticket model for signed-in users.
- Audit records for provider/settings changes and successful answers.

## Setup
Install and activate the addon in the Core addon manager, open Admin > AI Chatbot, configure an official provider key and supported model, test the connection, add approved knowledge items, then enable the assistant. No AI key is required to install or deactivate it.

## Security and privacy
Credentials are encrypted with Laravel Crypt and never returned to the browser. Only official HTTPS provider endpoints are used; arbitrary endpoints are deliberately disabled to avoid SSRF. Conversation ownership is checked on the server. The assistant has no arbitrary SQL, shell, financial or account-management tools. Never share passwords, OTPs, PINs or payment secrets in chat. Public visitors can chat, but support ticket creation requires sign-in.

## Hosting and limitations
Uses Laravel HTTP client and the Core database. No Redis, Docker, permanent worker or extra service is required. Knowledge retrieval uses keyword scoring rather than embeddings. A daily Laravel Scheduler task deletes conversations older than the configured retention period while the addon is active. Configure cPanel cron to run php artisan schedule:run every minute so the daily task executes. Verify the real cPanel deployment and provider connection before enabling for live users.
