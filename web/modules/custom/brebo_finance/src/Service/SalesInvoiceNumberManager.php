<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\SalesInvoiceNumberSettingsInterface;
use Drupal\brebo_finance\Contract\SalesInvoiceNumberStoreInterface;
use Drupal\brebo_finance\Contract\SalesInvoiceNumberLockInterface;

/** Owns BREBO sales invoice numbering, reservations and skips. */
final class SalesInvoiceNumberManager {

  public function __construct(
    private readonly SalesInvoiceNumberSettingsInterface $settings,
    private readonly SalesInvoiceNumberStoreInterface $store,
    private readonly SalesInvoiceNumberLockInterface $lock,
  ) {}

  public function nextAutomatic(?int $year = NULL): string {
    $invoiceNumber = $this->reserveNextAutomatic($year);
    $this->markUsed($invoiceNumber);
    return $invoiceNumber;
  }

  public function reserveNextAutomatic(?int $year = NULL): string {
    $year ??= (int) date('Y');
    if (!$this->lock->acquire(10.0)) {
      throw new \RuntimeException('Factuurnummering is tijdelijk bezet. Probeer opnieuw.');
    }
    try {
            $cursorKey = $this->cursorKey($year);
      $cursor = max($this->startNumber(), (int) $this->store->get($cursorKey, $this->startNumber()));
      do {
        $candidate = $this->format($cursor, $year);
        $cursor++;
      } while ($this->store->has($this->numberKey($candidate)));

      $this->store->set($cursorKey, $cursor);
      $this->store->set($this->numberKey($candidate), ['status' => 'reserved', 'year' => $year]);
      return $candidate;
    }
    finally {
      $this->lock->release();
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
            $key = $this->numberKey($invoiceNumber);
      if ($this->store->has($key)) {
        throw new \RuntimeException('Dit factuurnummer is al gereserveerd of gebruikt.');
      }
      $this->store->set($key, ['status' => 'reserved']);
    }
    finally {
      $this->lock->release();
    }
  }

  public function markUsed(string $invoiceNumber): void {
    $invoiceNumber = trim($invoiceNumber);
    if ($invoiceNumber === '') {
      throw new \InvalidArgumentException('Factuurnummer is verplicht.');
    }
    if (!$this->lock->acquire(self::LOCK, 10.0)) {
      throw new \RuntimeException('Factuurnummering is tijdelijk bezet. Probeer opnieuw.');
    }
    try {
            $existing = $this->store->get($this->numberKey($invoiceNumber));
      if (is_array($existing) && ($existing['status'] ?? '') === 'used') {
        return;
      }
      $this->store->set($this->numberKey($invoiceNumber), ['status' => 'used']);
    }
    finally {
      $this->lock->release();
    }
  }

  public function isOccupied(string $invoiceNumber): bool {
    return $this->store->has($this->numberKey(trim($invoiceNumber)));
  }

  public function format(int $sequence, ?int $year = NULL): string {
    $settings = $this->settings->settings();
    $year ??= (int) date('Y');
    $number = str_pad((string) $sequence, $settings['digits'], '0', STR_PAD_LEFT);
    return $settings['include_year']
      ? $settings['prefix'] . $year . $settings['separator'] . $number
      : $settings['prefix'] . $number;
  }

  private function startNumber(): int {
    return $this->settings->settings()['start_number'];
  }

  private function cursorKey(int $year): string {
    return 'cursor:' . ($this->settings->settings()['reset_yearly'] ? (string) $year : 'global');
  }

  private function numberKey(string $invoiceNumber): string {
    return 'number:' . $invoiceNumber;
  }

}
