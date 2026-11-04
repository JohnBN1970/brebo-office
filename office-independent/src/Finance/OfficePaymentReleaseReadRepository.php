<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Read-only Office-owned source for Finance payment releases.
 * No Drupal dependency, recipient lookup, mutation, or bank submission.
 */
final class OfficePaymentReleaseReadRepository {
  public function __construct(private readonly \PDO $pdo, private readonly bool $schemaVerified = false) {}

  public function approved(int $releaseId): array {
    if (!$this->schemaVerified) throw new \DomainException('Finance release schema not verified');
    if ($releaseId < 1) throw new \DomainException('Invalid payment release');
    $query = $this->pdo->prepare(
      'SELECT r.id AS release_id, r.invoice_id, r.release_number, r.status AS release_status, '
      . 'r.regular_account_amount, r.g_account_amount, r.total_amount, r.currency, '
      . 'i.id AS matched_invoice_id, i.project_nid, i.invoice_number, '
      . 'i.status AS invoice_status, i.match_status '
      . 'FROM brebo_finance_payment_release r '
      . 'INNER JOIN brebo_finance_purchase_invoice i ON i.id = r.invoice_id '
      . 'WHERE r.id = :release_id'
    );
    $query->execute(['release_id'=>$releaseId]);
    $row = $query->fetch(\PDO::FETCH_ASSOC);
    if (!is_array($row)
      || $row['release_status'] !== 'approved'
      || $row['match_status'] !== 'matched'
      || in_array($row['invoice_status'], ['paid','cancelled'], true)
      || $row['currency'] !== 'EUR'
      || (int)$row['matched_invoice_id'] !== (int)$row['invoice_id']) {
      throw new \DomainException('Approved and matched Finance release unavailable');
    }
    foreach (['regular_account_amount','g_account_amount','total_amount'] as $key) {
      if (!preg_match('/^[0-9]+(?:\\.[0-9]{1,4})?$/D', (string)$row[$key])) {
        throw new \DomainException('Invalid Finance release amount');
      }
    }
    // G-account routing is deliberately unsupported until verified recipient data exists.
    if ((float)$row['g_account_amount'] > 0) {
      throw new \DomainException('G-account payment requires verified dedicated IBAN');
    }
    return $row;
  }
}
