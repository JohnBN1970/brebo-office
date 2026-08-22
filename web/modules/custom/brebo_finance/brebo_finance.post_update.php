<?php

declare(strict_types=1);

/**
 * @file
 * Post-update hooks for BREBO Finance.
 */

/**
 * Adds line-level VAT storage for billing instalments and sales invoices.
 */
function brebo_finance_post_update_billing_vat_lines(array &$sandbox): string {
  $database = \Drupal::database();
  $schema = $database->schema();

  $money = [
    'type' => 'numeric',
    'precision' => 18,
    'scale' => 4,
    'not null' => TRUE,
    'default' => 0,
  ];
  $timestamp = [
    'type' => 'int',
    'unsigned' => TRUE,
    'not null' => TRUE,
  ];
  $user = [
    'type' => 'int',
    'unsigned' => TRUE,
    'not null' => FALSE,
  ];

  if (!$schema->tableExists('brebo_finance_billing_instalment_line')) {
    $schema->createTable('brebo_finance_billing_instalment_line', [
      'description' => 'VAT-bearing lines belonging to a project billing instalment.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'instalment_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'line_number' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'description' => ['type' => 'varchar', 'length' => 512, 'not null' => TRUE],
        'amount_ex_vat' => $money,
        'vat_code' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'vat_rate' => ['type' => 'numeric', 'precision' => 7, 'scale' => 4, 'not null' => TRUE],
        'vat_amount' => $money,
        'amount_inc_vat' => $money,
        'source_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'created' => $timestamp,
        'created_by' => $user,
        'changed' => $timestamp,
        'changed_by' => $user,
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'instalment_line' => ['instalment_id', 'line_number'],
      ],
      'indexes' => [
        'project_instalment' => ['project_nid', 'instalment_id'],
        'vat_code' => ['vat_code'],
      ],
    ]);
  }

  if (!$schema->tableExists('brebo_finance_sales_invoice_line')) {
    $schema->createTable('brebo_finance_sales_invoice_line', [
      'description' => 'VAT-bearing lines mirrored from a client sales invoice.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'sales_invoice_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'line_number' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'description' => ['type' => 'varchar', 'length' => 512, 'not null' => TRUE],
        'amount_ex_vat' => $money,
        'vat_code' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'vat_rate' => ['type' => 'numeric', 'precision' => 7, 'scale' => 4, 'not null' => TRUE],
        'vat_amount' => $money,
        'amount_inc_vat' => $money,
        'source_ref' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'created' => $timestamp,
        'created_by' => $user,
        'changed' => $timestamp,
        'changed_by' => $user,
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'sales_invoice_line' => ['sales_invoice_id', 'line_number'],
      ],
      'indexes' => [
        'project_invoice' => ['project_nid', 'sales_invoice_id'],
        'vat_code' => ['vat_code'],
      ],
    ]);
  }

  return 'BREBO Finance billing and sales-invoice VAT line storage installed.';
}
