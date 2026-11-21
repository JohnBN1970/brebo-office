# FinanceCenter #1607 — Office supplier verification migration proposal (NOT EXECUTED)

**Status:** design only. Do not run SQL, import legacy supplier accounts, merge or deploy without separate approvals.

## Ownership and boundaries
- Office CRM relation is the canonical supplier identity. Existing `office_crm_relations.id` is the parent.
- Moneybird external identities are matching keys only, never bank account master data or verification evidence.
- Invoice data and payment status remain Office-owned. Moneybird bank transactions are supporting evidence only.
- All imported/legacy bank details must start **unverified**; no grandfathering.
- Drupal adapters are transitional only. New native Finance code must not depend on Drupal.

## Proposed additive SQL (requires explicit database approval)
```sql
CREATE TABLE IF NOT EXISTS office_crm_relation_bank_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  relation_id BIGINT UNSIGNED NOT NULL,
  account_type VARCHAR(16) NOT NULL DEFAULT 'regular',
  iban VARCHAR(34) NOT NULL,
  account_holder VARCHAR(255) NOT NULL,
  bic VARCHAR(11) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'inactive',
  verification_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  verification_evidence_id VARCHAR(128) NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_office_relation_bank_account (relation_id, account_type, iban),
  KEY idx_office_bank_relation_status (relation_id, status, verification_status),
  CONSTRAINT fk_office_bank_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_crm_bank_account_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bank_account_id BIGINT UNSIGNED NOT NULL,
  relation_id BIGINT UNSIGNED NOT NULL,
  action_code VARCHAR(48) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  old_values_json JSON NULL,
  new_values_json JSON NULL,
  evidence_reference VARCHAR(128) NULL,
  KEY idx_office_bank_audit_account_time (bank_account_id, occurred_at),
  CONSTRAINT fk_office_bank_audit_account FOREIGN KEY (bank_account_id) REFERENCES office_crm_relation_bank_accounts(id),
  CONSTRAINT fk_office_bank_audit_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_finance_payment_control_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  four_eyes_required TINYINT(1) NOT NULL DEFAULT 1,
  changed_by BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_office_finance_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Review points before approval:** account type vocabulary and G-account model; data retention/privacy for old/new audit JSON (mask IBAN in audit and keep a secure fingerprint); unique active account rule under concurrency; immutable append-only audit enforcement at repository/DB privilege level; timestamp consistency with PHP policy; verification evidence storage and permission model; whether configuration belongs in existing Office settings rather than a new table. No data migration from Drupal or Moneybird is authorized.

## Planned adapter contract (not connected yet)
1. Resolve purchase invoice to Office CRM supplier relation, without querying Moneybird for bank details.
2. Load active Office bank account and independent verification evidence; reject missing, ambiguous, invalid, inactive or changed records.
3. Pass an immutable snapshot into the existing `PaymentRecipientGatewayInterface` and revalidate before review and release.
4. Keep `PaymentBatchManager` fail-closed. Four-eyes setting may only affect separation-of-duties, never authorization, green controller verdict, recipient integrity, amount checks or sealed payload.
5. G-account instructions remain blocked until independently verified G-account data and controls are implemented.
6. Do not activate adapter or execute schema until independently approved.

## Regression gates
- Valid independently verified supplier succeeds; missing evidence, changed IBAN, changed holder, invalid IBAN, inactive supplier, wrong relation, duplicate active accounts all fail closed.
- Same-user release blocked by default; optional policy requires explicit authorized setting; unauthorized user always blocked.
- Paid/cancelled/unmatched invoices, duplicate release, altered amount, stale hash and G-account without verified details all blocked.
- Office invoice statuses only derive from Office-owned ledger and confirmed uniquely matched bank evidence; no Moneybird invoice GET/import.
- Run unit tests, existing Finance tests and CI against isolated fixtures, not live supplier data.
