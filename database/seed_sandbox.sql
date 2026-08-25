-- Sample data for testing against the sandbox environment.
-- Replace the wallet_id placeholders with the REAL walletId values of your
-- Asaas sandbox accounts before running a charge.
--
-- All names, e-mail addresses and tax IDs below are fictional.

INSERT INTO producers (name, email, document, type, gateway, wallet_id, account_status) VALUES
('John Smith', 'john@example.com', '12345678909', 'produtor_principal', 'asaas', 'REPLACE_WITH_PRINCIPAL_WALLET_ID',  'ativo'),
('Jane Doe',   'jane@example.com', '98765432100', 'coprodutor',         'asaas', 'REPLACE_WITH_COPRODUCER_WALLET_ID', 'ativo');

INSERT INTO products
(name, description, price_cents, status, checkout_slug, principal_producer_id, coproducer_producer_id, principal_percent, coproducer_percent)
VALUES
('Demo Course — Product Fundamentals', 'Lifetime access to the demo course, certificate included.', 10000, 'ativo', 'curso-demo', 1, 2, 85.00, 15.00);
