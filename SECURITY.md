# Security

Author: Salman | Version: 1.2.0
Description: Safe vulnerability reporting and credential handling guidance.

Do not publish API keys, signing secrets, return URLs containing order keys, database dumps, customer data or card details in public issues. Use the repository owner's private reporting channel if enabled. No private contact address is specified in this package. If no private channel is available, request one in a public issue without disclosing exploitation details or sensitive information.

Do not disable HTTPS validation, full order key comparison, signed webhook verification or server-side payment checks to work around failures. Reconcile outstanding payments before rotating credentials. The project is provided without warranty; merchants must validate their own hosting, provider configuration and fulfillment behavior.
