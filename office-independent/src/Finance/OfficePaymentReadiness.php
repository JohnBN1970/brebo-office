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
      'bank_provider_contract_verified'=>getenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED') === 'enabled',
    ];
    if ($schemaVerified) {
      $tables = [
        'mandate_schema_present'=>[
          'office_payment_mandates'=>['operator_id','enabled','mode','limit_cents','dual_above_cents','valid_from','valid_until'],
        ],
        'artifact_schema_present'=>[
          'office_sepa_batch_artifacts'=>['batch_id','payload_sha256','ciphertext','encryption_nonce','encryption_key_id','created_by','created_at'],
        ],
        'submission_schema_present'=>[
          'office_bank_submission_intents'=>['batch_id','idempotency_key','payload_sha256','status','bank_reference','requested_by','created_at','changed_at'],
        ],
        'canonical_batch_schema_present'=>[
          'brebo_finance_payment_batch'=>['id','status','batch_number','execution_date','currency','sealed_hash','item_count','created_by','released_by','controller_verdict','control_sum'],
          'brebo_finance_payment_batch_item'=>['id','batch_id','position','release_id','invoice_id','instruction_type','amount','currency','creditor_name','creditor_iban','creditor_bic','recipient_hash','end_to_end_id','remittance_information','status'],
        ],
      ];
      foreach ($tables as $key=>$requiredTables) {
        $checks[$key] = true;
        foreach ($requiredTables as $table=>$columns) {
          try {
            $pdo->query('SELECT ' . implode(', ', $columns) . ' FROM ' . $table . ' WHERE 1 = 0');
          } catch (\PDOException $ignored) {
            $checks[$key] = false;
            break;
          }
        }
      }
    }
    return [
      'ready'=>!in_array(false, $checks, true),
      'checks'=>$checks,
    ];
  }
}
