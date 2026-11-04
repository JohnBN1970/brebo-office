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
      'submission_uniqueness_verified'=>false,
      'artifact_uniqueness_verified'=>false,
      'mandate_uniqueness_verified'=>false,
      'payment_column_types_verified'=>false,
      'payment_transactional_tables_verified'=>false,
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
      // MySQL is the production database. Verify safety-critical column types
      // independently of mere column existence; fail closed on other drivers.
      if ($checks['mandate_schema_present'] && $checks['artifact_schema_present']
        && $checks['submission_schema_present']) {
        try {
          if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $expected = [
              'office_payment_mandates'=>[
                'operator_id'=>'bigint unsigned', 'limit_cents'=>'bigint unsigned',
                'dual_above_cents'=>'bigint unsigned', 'enabled'=>'tinyint',
                'valid_from'=>'datetime', 'valid_until'=>'datetime',
              ],
              'office_sepa_batch_artifacts'=>[
                'batch_id'=>'bigint unsigned', 'ciphertext'=>'longblob',
                'encryption_nonce'=>'varbinary', 'payload_sha256'=>'char',
              ],
              'office_bank_submission_intents'=>[
                'batch_id'=>'bigint unsigned', 'idempotency_key'=>'char',
                'payload_sha256'=>'char', 'requested_by'=>'bigint unsigned',
                'created_at'=>'datetime',
              ],
            ];
            $statement = $pdo->query(
              "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('office_payment_mandates',
                 'office_sepa_batch_artifacts','office_bank_submission_intents')"
            );
            $actual = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {
              $actual[$column['TABLE_NAME']][$column['COLUMN_NAME']] = strtolower($column['COLUMN_TYPE']);
            }
            $checks['payment_column_types_verified'] = true;
            foreach ($expected as $table=>$columns) {
              foreach ($columns as $name=>$type) {
                if (!str_starts_with($actual[$table][$name] ?? '', $type)) {
                  $checks['payment_column_types_verified'] = false;
                }
              }
            }
          }
        } catch (\PDOException $ignored) {
          $checks['payment_column_types_verified'] = false;
        }
      }
      // Payment reservations must be transactionally durable. MyISAM and other
      // non-transactional engines cannot guarantee the reserve/commit boundary.
      try {
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
          $statement = $pdo->query(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME IN ('office_payment_mandates','office_sepa_batch_artifacts',
               'office_bank_submission_intents','brebo_finance_payment_batch',
               'brebo_finance_payment_batch_item')"
          );
          $engines = [];
          foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $table) {
            $engines[$table['TABLE_NAME']] = strtoupper((string)$table['ENGINE']);
          }
          $checks['payment_transactional_tables_verified'] = count($engines) === 5
            && count(array_filter($engines, static fn(string $engine): bool => $engine === 'INNODB')) === 5;
        }
      } catch (\PDOException $ignored) {
        $checks['payment_transactional_tables_verified'] = false;
      }
      // Enforce one immutable artifact and one active mandate record per identity,
      // as well as exactly one durable reservation per batch and idempotency key.
      $constraints = [
        'submission_uniqueness_verified'=>[
          'table'=>'office_bank_submission_intents',
          'columns'=>[['batch_id'],['idempotency_key']],
          'schema'=>'submission_schema_present',
        ],
        'artifact_uniqueness_verified'=>[
          'table'=>'office_sepa_batch_artifacts',
          'columns'=>[['batch_id']],
          'schema'=>'artifact_schema_present',
        ],
        'mandate_uniqueness_verified'=>[
          'table'=>'office_payment_mandates',
          'columns'=>[['operator_id']],
          'schema'=>'mandate_schema_present',
        ],
      ];
      foreach ($constraints as $check=>$rule) {
        if (!$checks[$rule['schema']]) continue;
        try {
          $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
          $uniqueColumns = [];
          if ($driver === 'mysql') {
            $statement = $pdo->prepare(
              "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
                 AND NON_UNIQUE = 0 ORDER BY INDEX_NAME, SEQ_IN_INDEX"
            );
            $statement->execute(['table'=>$rule['table']]);
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $index) {
              $uniqueColumns[$index['INDEX_NAME']][] = $index['COLUMN_NAME'];
            }
          } elseif ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA index_list('" . $rule['table'] . "')")->fetchAll(\PDO::FETCH_ASSOC) as $index) {
              if ((int)$index['unique'] !== 1) continue;
              $names = [];
              foreach ($pdo->query('PRAGMA index_info(' . $pdo->quote($index['name']) . ')')->fetchAll(\PDO::FETCH_ASSOC) as $column) {
                $names[] = $column['name'];
              }
              $uniqueColumns[$index['name']] = $names;
            }
          }
          $checks[$check] = true;
          foreach ($rule['columns'] as $required) {
            if (!in_array($required, $uniqueColumns, true)) $checks[$check] = false;
          }
        } catch (\PDOException $ignored) {
          $checks[$check] = false;
        }
      }
    }
    return [
      'ready'=>!in_array(false, $checks, true),
      'checks'=>$checks,
    ];
  }
}
