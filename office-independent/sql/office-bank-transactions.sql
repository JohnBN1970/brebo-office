CREATE TABLE IF NOT EXISTS office_bank_transactions (
  deduplication_key CHAR(64) NOT NULL PRIMARY KEY,
  provider VARCHAR(128) NOT NULL,
  account_id VARCHAR(128) NOT NULL,
  transaction_id VARCHAR(128) NOT NULL,
  booking_date DATE NOT NULL,
  currency CHAR(3) NOT NULL,
  amount_minor BIGINT NOT NULL,
  description TEXT NOT NULL,
  INDEX idx_bank_account_date (provider, account_id, booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
