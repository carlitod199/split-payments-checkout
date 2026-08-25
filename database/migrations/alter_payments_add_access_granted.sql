-- Records when access was granted for this payment.
-- Ensures that redeliveries of the "paid" webhook cannot create a duplicate
-- user, enrollment or e-mail.
ALTER TABLE payments
    ADD COLUMN access_granted_at DATETIME NULL DEFAULT NULL AFTER paid_at;
