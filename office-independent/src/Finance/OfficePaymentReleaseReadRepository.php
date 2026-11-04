<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Fail-closed read-only adapter for an approved Finance payment release.
 * This is not an authorization to pay or to transmit bank instructions.
 */
final class OfficePaymentReleaseReadRepository {
  public function __construct(private readonly \PDO $pdo, private readonly bool $schemaVerified = false) {}

  private static function units(string $amount): int {
    if (!preg_match('/^([0-9]{1,12})(?:\\.([0-9]{1,4}))?$/D', $amount, $matches)) {
      throw new \DomainException('Invalid Finance release amount');
    }
    return ((int)$matches[1] * 10000) + (int)str_pad($matches[2] ?? '', 4, '0');
  }

  public function approved(int $releaseId): array {
    if (!$this->schemaVerified) throw new \DomainException('Finance release schema not verified');
    if ($releaseId < 1) throw new \DomainException('Invalid payment release');
    $query = $this->pdo->prepare(
      'SELECT r.id AS release_id, r.invoice_id, r.project_nid AS release_project_nid, '
      . 'r.release_number, r.status AS release_status, r.approved, r.approved_by, '
      . 'r.executed, r.executed_by, r.regular_account_amount, r.g_account_amount, '
      . 'r.total_amount, r.currency, i.id AS matched_invoice_id, '
      . 'i.project_nid, i.invoice_number, i.status AS invoice_status, '
      . 'i.match_status, i.amount_inc_vat AS invoice_amount_inc_vat, '
      . 'i.regular_account_amount AS invoice_regular_account_amount, '
      . 'i.g_account_amount AS invoice_g_account_amount, i.currency AS invoice_currency '
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
      || $row['invoice_currency'] !== 'EUR'
      || (int)$row['matched_invoice_id'] !== (int)$row['invoice_id']
      || (int)$row['project_nid'] !== (int)$row['release_project_nid']
      || (int)($row['approved'] ?? 0) <= 0
      || (int)($row['approved_by'] ?? 0) <= 0
      || (int)($row['executed'] ?? 0) > 0
      || (int)($row['executed_by'] ?? 0) > 0) {
      throw new \DomainException('Approved and matched Finance release unavailable');
    }

    $regular = self::units((string)$row['regular_account_amount']);
    $gAccount = self::units((string)$row['g_account_amount']);
    $total = self::units((string)$row['total_amount']);
    $invoiceTotal = self::units((string)$row['invoice_amount_inc_vat']);
    $invoiceRegular = self::units((string)$row['invoice_regular_account_amount']);
    $invoiceGAccount = self::units((string)$row['invoice_g_account_amount']);
    if ($total <= 0 || $regular + $gAccount !== $total
      || $invoiceRegular + $invoiceGAccount !== $invoiceTotal
      || $total !== $invoiceTotal
      || $regular !== $invoiceRegular || $gAccount !== $invoiceGAccount) {
      throw new \DomainException('Finance release amount mismatch');
    }
    // Never route a G-account amount without a separately verified destination.
    if ($gAccount > 0) {
      throw new \DomainException('G-account payment requires verified dedicated IBAN');
    }
    return $row;
  }
}
