<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
require_once __DIR__.'/OfficeSalesBilling.php';

/** Additive installation only. Never execute DDL from a web request. */
final class OfficeSalesSchema {
  public static function statements(bool $sqlite = false): array {
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $text = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return [
      "CREATE TABLE office_finance_sales_invoice (
        id $id, operator_id VARCHAR(128) NOT NULL, office_project_id BIGINT NULL,
        relation_id BIGINT NOT NULL, customer_json TEXT NOT NULL, invoice_date VARCHAR(10) NOT NULL,
        due_date VARCHAR(10) NOT NULL, reference VARCHAR(255) NOT NULL,
        lines_json $text NOT NULL, net_cents BIGINT NOT NULL, vat_cents BIGINT NOT NULL,
        gross_cents BIGINT NOT NULL, paid_cents BIGINT NOT NULL DEFAULT 0,
        currency VARCHAR(3) NOT NULL DEFAULT 'EUR', status VARCHAR(32) NOT NULL DEFAULT 'draft',
        revision INTEGER NOT NULL DEFAULT 1, provider_json TEXT NULL, invoice_number VARCHAR(128) NULL UNIQUE,
        reminder_count INTEGER NOT NULL DEFAULT 0, last_reminder_date VARCHAR(10) NULL,
        created_at VARCHAR(32) NOT NULL, updated_at VARCHAR(32) NOT NULL
      )$tail",
      "CREATE TABLE office_finance_sales_delivery (
        id $id, invoice_id BIGINT NOT NULL, operation VARCHAR(24) NOT NULL,
        command_key VARCHAR(96) NOT NULL UNIQUE, status VARCHAR(32) NOT NULL,
        recipient VARCHAR(254) NOT NULL, message TEXT NOT NULL, payload_json $text NOT NULL,
        created_at VARCHAR(32) NOT NULL, completed_at VARCHAR(32) NULL,
        result_json TEXT NULL
      )$tail",
      "CREATE TABLE office_finance_sales_audit (
        id $id, invoice_id BIGINT NOT NULL, operator_id VARCHAR(128) NOT NULL,
        event VARCHAR(48) NOT NULL, detail_json TEXT NOT NULL, created_at VARCHAR(32) NOT NULL
      )$tail",
      "CREATE TABLE office_finance_sales_receipt (
        id $id, invoice_id BIGINT NOT NULL, amount_cents BIGINT NOT NULL,
        receipt_date VARCHAR(10) NOT NULL, evidence_reference VARCHAR(128) NOT NULL,
        operator_id VARCHAR(128) NOT NULL, created_at VARCHAR(32) NOT NULL,
        UNIQUE (invoice_id,evidence_reference)
      )$tail",
      OfficeSalesBilling::schema($sqlite),
    ];
  }
}
