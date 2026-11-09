<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\PurchaseInvoiceImportRepositoryInterface;
use RuntimeException;
use UnexpectedValueException;

/** Imports Moneybird purchase invoices into the operational Finance mirror. */
final class PurchaseInvoiceImporter {

  public function __construct(
    private readonly PurchaseInvoiceImportRepositoryInterface $repository,
    private readonly PurchaseInvoiceIntegrationClient $client,
    private readonly ?VatCalculator $decimal = NULL,
  ) {}

  /** @return array{received:int,inserted:int,updated:int,unchanged:int} */
  public function sync(): array {
    throw new RuntimeException('Moneybird-to-Office purchase invoice import is disabled; BREBO Office is authoritative.');
  }

  /**
   * Fail closed until a verified Office-owned supplier IBAN is available.
   * Moneybird invoice GET must never be used to authorize payments.
   * @return array<string, mixed>
   */
  public function paymentRecipientSnapshot(int $invoiceId, int $actorUid = 0): array {
    $invoice = $this->repository->paymentRecipientInvoice($invoiceId);
    if ($invoice === NULL) {
      throw new UnexpectedValueException('Purchase invoice does not exist.');
    }
    throw new RuntimeException('Payment preparation blocked: verified supplier IBAN and account holder must be sourced from BREBO Office, not Moneybird.');
  }

  /** Never approve a stale recipient until Office-owned verification exists. */
  public function paymentRecipientUnchanged(int $invoiceId, string $expectedHash, int $actorUid = 0): bool {
    $this->paymentRecipientSnapshot($invoiceId, $actorUid);
    return FALSE;
  }

  private function supplierName(array $source): string {
    $name = trim((string) ($source['supplier_name'] ?? ''));
    return $name !== '' ? substr($name, 0, 255) : 'Onbekende leverancier';
  }

  private function invoiceNumber(array $source, string $moneybirdId): string {
    $reference = trim((string) ($source['reference'] ?? ''));
    return substr($reference !== '' ? $reference : 'MB-' . $moneybirdId, 0, 128);
  }

  private function status(string $state): string {
    return match ($state) {
      'paid' => 'paid',
      'late' => 'late',
      'open', 'pending_payment' => 'open',
      'new', 'saved' => 'received',
      default => 'received',
    };
  }

  private function number(mixed $value): float {
    return is_numeric($value) ? (float) $value : 0.0;
  }

  private function nullable(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? NULL : substr($value, 0, 255);
  }

  private function date(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : NULL;
  }

  private function normaliseIban(string $iban): string {
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $iban));
  }

  private function validIban(string $iban): bool {
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
      return FALSE;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $character) {
      $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
      $remainder = (($remainder * 10) + (int) $digit) % 97;
    }
    return $remainder === 1;
  }

}
