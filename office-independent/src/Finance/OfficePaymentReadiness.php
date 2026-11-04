<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Read-only readiness snapshot. This is not permission to submit payments.
 * Never exposes credentials or changes flags/schema.
 */
final class OfficePaymentReadiness {
  public static function inspect(\PDO $pdo, bool $schemaVerified, bool $authorized): array {
    $checks = [
      'operator_authorized'=>$authorized,
      'bank_payments_enabled'=>getenv('OFFICE_BANK_PAYMENTS_ENABLED') === 'enabled',
      'canonical_schema_verified'=>$schemaVerified,
      'mandate_schema_present'=>false,
      'artifact_schema_present'=>false,
      'submission_schema_present'=>false,
      'canonical_batch_schema_present'=>false,
    ];
    if ($schemaVerified) {
      $tables = [
        'mandate_schema_present'=>'office_payment_mandates',
        'artifact_schema_present'=>'office_sepa_batch_artifacts',
        'submission_schema_present'=>'office_bank_submission_intents',
        'canonical_batch_schema_present'=>'brebo_finance_payment_batch',
      ];
      foreach ($tables as $key=>$table) {
        try {
          $pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0');
          $checks[$key] = true;
        } catch (\PDOException $ignored) {
          $checks[$key] = false;
        }
      }
      if ($checks['canonical_batch_schema_present']) {
        try {
          $pdo->query('SELECT 1 FROM brebo_finance_payment_batch_item WHERE 1 = 0');
        } catch (\PDOException $ignored) {
          $checks['canonical_batch_schema_present'] = false;
        }
      }
    }
    return [
      'ready'=>!in_array(false, $checks, true),
      'checks'=>$checks,
    ];
  }
}
