<?php
declare(strict_types=1);
namespace Brebo\Office\Finance\Center;
final class Schema {
  public static function statements(bool $sqlite=false): array {
    $id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';$tail=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $tables=[
      "office_finance_postings (id $id, source_key VARCHAR(160) NOT NULL UNIQUE, document_key VARCHAR(64) NULL UNIQUE, source_kind VARCHAR(24) NOT NULL, source_id BIGINT NULL, document_ref VARCHAR(255) NOT NULL, booking_date VARCHAR(10) NOT NULL, due_date VARCHAR(10) NULL, operator_id VARCHAR(128) NOT NULL, net_cents BIGINT NOT NULL, vat_cents BIGINT NOT NULL, gross_cents BIGINT NOT NULL, paid_cents BIGINT NOT NULL DEFAULT 0, g_cents BIGINT NOT NULL DEFAULT 0, cash_due INTEGER NOT NULL DEFAULT 1, status VARCHAR(24) NOT NULL DEFAULT 'booked', revision INTEGER NOT NULL DEFAULT 1, source_hash VARCHAR(64) NOT NULL, snapshot_json TEXT NOT NULL, created_at VARCHAR(32) NOT NULL)",
      "office_finance_posting_lines (id $id, posting_id BIGINT NOT NULL, office_project_id BIGINT NULL, category VARCHAR(24) NOT NULL, net_cents BIGINT NOT NULL, vat_cents BIGINT NOT NULL, gross_cents BIGINT NOT NULL, description VARCHAR(1000) NOT NULL)",
      "office_finance_cash_records (id $id, entity_type VARCHAR(16) NOT NULL, entity_id BIGINT NOT NULL, bank_reference VARCHAR(160) NOT NULL UNIQUE, account_bucket VARCHAR(16) NOT NULL, amount_cents BIGINT NOT NULL, payment_date VARCHAR(10) NOT NULL, operator_id VARCHAR(128) NOT NULL, created_at VARCHAR(32) NOT NULL)",
      "office_finance_cost_rates (id $id, person_id BIGINT NOT NULL, effective_from VARCHAR(10) NOT NULL, cents_per_hour BIGINT NOT NULL, evidence_reference VARCHAR(255) NOT NULL, operator_id VARCHAR(128) NOT NULL, created_at VARCHAR(32) NOT NULL, UNIQUE(person_id,effective_from))",
      "office_finance_cash_plan (id $id, operator_id VARCHAR(128) NOT NULL, office_project_id BIGINT NULL, direction VARCHAR(8) NOT NULL, account_bucket VARCHAR(16) NOT NULL, amount_cents BIGINT NOT NULL, due_date VARCHAR(10) NOT NULL, description VARCHAR(255) NOT NULL, evidence_reference VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'planned', paid_cents BIGINT NOT NULL DEFAULT 0, created_at VARCHAR(32) NOT NULL, UNIQUE(operator_id,evidence_reference))",
      "office_finance_balance_sets (id $id, operator_id VARCHAR(128) NOT NULL, balance_date VARCHAR(10) NOT NULL, evidence_reference VARCHAR(255) NOT NULL, created_at VARCHAR(32) NOT NULL)",
      "office_finance_balance_accounts (id $id, balance_set_id BIGINT NOT NULL, account_reference VARCHAR(160) NOT NULL, account_bucket VARCHAR(16) NOT NULL, balance_cents BIGINT NOT NULL, UNIQUE(balance_set_id,account_reference))",
      "office_finance_period_reviews (id $id, operator_id VARCHAR(128) NOT NULL, through_date VARCHAR(10) NOT NULL, costs_complete INTEGER NOT NULL, hours_complete INTEGER NOT NULL, overhead_complete INTEGER NOT NULL, cash_complete INTEGER NOT NULL, evidence_reference VARCHAR(255) NOT NULL, source_hash VARCHAR(64) NOT NULL, created_at VARCHAR(32) NOT NULL)",
      "office_finance_center_audit (id $id, operator_id VARCHAR(128) NOT NULL, event VARCHAR(64) NOT NULL, entity_id BIGINT NULL, detail_json TEXT NOT NULL, created_at VARCHAR(32) NOT NULL)",
      "office_finance_sales_approvals (invoice_id BIGINT NOT NULL PRIMARY KEY, invoice_revision INTEGER NOT NULL, state VARCHAR(16) NOT NULL, requested_by VARCHAR(128) NOT NULL, approved_by VARCHAR(128) NULL, recipient VARCHAR(254) NOT NULL, message TEXT NOT NULL, content_hash VARCHAR(64) NOT NULL, created_at VARCHAR(32) NOT NULL, approved_at VARCHAR(32) NULL)",
    ];return array_map(static fn($s)=>'CREATE TABLE '.$s.$tail,$tables);
  }
}
