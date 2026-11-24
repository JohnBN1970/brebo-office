-- Office-test organization-level presentation defaults only.
-- Apply after backup and schema preflight; no historical documents are rewritten.
CREATE TABLE IF NOT EXISTS office_financial_display_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
    locale_code VARCHAR(10) NOT NULL DEFAULT 'nl-NL',
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_office_financial_singleton CHECK (id = 1),
    CONSTRAINT chk_office_financial_currency CHECK (currency_code IN ('EUR','USD','GBP')),
    CONSTRAINT chk_office_financial_locale CHECK (locale_code IN ('nl-NL','en-GB'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO office_financial_display_settings (id,currency_code,locale_code)
VALUES (1,'EUR','nl-NL');
