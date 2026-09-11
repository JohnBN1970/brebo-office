<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;

/** Owns BREBO sales invoice numbering, reservations and skips. */
final class SalesInvoiceNumberManager {

  private const COLLECTION = 'brebo_finance.sales_invoice_numbers';
  private const LOCK = 'brebo_finance.sales_invoice_numbers';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly LockBackendInterface $lock,
  ) {}

  public function nextAutomatic(?int $year = NULL): string {
    $year ??= (int) date('Y');
    if (!$this->lock->acquire(self::LOCK, 10.0)) {
      throw new \RuntimeException('Factuurnummering is tijdelijk bezet. Probeer opnieuw.');
    }
    try {
      $store = $this->keyValueFactory->get(self::COLLECTION);
      $cursorKey = $this->cursorKey($year);
      $cursor = max($this->startNumber(), (int) $store->get($cursorKey, $this->startNumber()));
      do {
        $candidate = $this->format($cursor, $year);
        $cursor++;
      } while ($store->has($this->numberKey($candidate)));

      $store->set($cursorKey, $cursor);
      $store->set($this->numberKey($candidate), ['status' => 'used', 'year' => $year]);
      return $candidate;
    }
    finally {
      $this->lock->release(self::LOCK);
    }
  }

  public function reserveManual(string $invoiceNumber): void {
    $invoiceNumber = trim($invoiceNumber);
    if ($invoiceNumber === '') {
      throw new \InvalidArgumentException('Factuurnummer is verplicht.');
    }
    if (!$this->lock->acquire(self::LOCK, 10.0)) {
      throw new \RuntimeException('Factuurnummering is tijdelijk bezet. Probeer opnieuw.');
    }
    try {
      $store = $this->keyValueFactory->get(self::COLLECTION);
      $key = $this->numberKey($invoiceNumber);
      if ($store->has($key)) {
        throw new \RuntimeException('Dit factuurnummer is al gereserveerd of gebruikt.');
      }
      $store->set($key, ['status' => 'reserved']);
    }
    finally {
      $this->lock->release(self::LOCK);
    }
  }

  public function markUsed(string $invoiceNumber): void {
    $invoiceNumber = trim($invoiceNumber);
    if ($invoiceNumber === '') {
      throw new \InvalidArgumentException('Factuurnummer is verplicht.');
    }
    $store = $this->keyValueFactory->get(self::COLLECTION);
    $existing = $store->get($this->numberKey($invoiceNumber));
    if (is_array($existing) && ($existing['status'] ?? '') === 'used') {
      return;
    }
    $store->set($this->numberKey($invoiceNumber), ['status' => 'used']);
  }

  public function isOccupied(string $invoiceNumber): bool {
    return $this->keyValueFactory->get(self::COLLECTION)->has($this->numberKey(trim($invoiceNumber)));
  }

  public function format(int $sequence, ?int $year = NULL): string {
    $config = $this->configFactory->get('brebo_finance.sales');
    $year ??= (int) date('Y');
    $prefix = (string) ($config->get('numbering.prefix') ?? 'VF-');
    $separator = (string) ($config->get('numbering.separator') ?? '-');
    $digits = max(1, min(10, (int) ($config->get('numbering.digits') ?? 4)));
    $includeYear = (bool) ($config->get('numbering.include_year') ?? TRUE);
    $number = str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
    return $includeYear ? $prefix . $year . $separator . $number : $prefix . $number;
  }

  private function startNumber(): int {
    return max(1, (int) ($this->configFactory->get('brebo_finance.sales')->get('numbering.start_number') ?? 1));
  }

  private function cursorKey(int $year): string {
    $resetYearly = (bool) ($this->configFactory->get('brebo_finance.sales')->get('numbering.reset_yearly') ?? TRUE);
    return 'cursor:' . ($resetYearly ? (string) $year : 'global');
  }

  private function numberKey(string $invoiceNumber): string {
    return 'number:' . $invoiceNumber;
  }

}
