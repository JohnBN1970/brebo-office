<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Payment authority is explicitly configured, never inferred from job title. */
final class OfficePaymentMandatePolicy {
  public static function authorize(array $mandate, int $actorId, int $preparerId, ?int $approverId, string $totalAmount): void {
    if ($actorId < 1 || $preparerId < 1 || ($mandate['enabled'] ?? false) !== true) {
      throw new \DomainException('Active payment mandate required');
    }
    if (!in_array(($mandate['mode'] ?? ''), ['single', 'dual', 'threshold'], true)) {
      throw new \DomainException('Invalid payment mandate mode');
    }
    if (!preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/D', $totalAmount) || (float)$totalAmount <= 0) {
      throw new \DomainException('Invalid payment total');
    }
    $limit = $mandate['limit_eur'] ?? null;
    if (!is_string($limit) || !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/D', $limit)) {
      throw new \DomainException('Configured payment limit required');
    }
    if (self::cents($totalAmount) > self::cents($limit)) {
      throw new \DomainException('Payment exceeds mandate limit');
    }
    $requiresSecond = $mandate['mode'] === 'dual';
    if ($mandate['mode'] === 'threshold') {
      $threshold = $mandate['dual_above_eur'] ?? null;
      if (!is_string($threshold) || !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/D', $threshold)) {
        throw new \DomainException('Second approval threshold required');
      }
      $requiresSecond = self::cents($totalAmount) > self::cents($threshold);
    }
    if ($requiresSecond && ($approverId === null || $approverId < 1 || $approverId === $preparerId)) {
      throw new \DomainException('Independent approval required by payment mandate');
    }
  }

  private static function cents(string $amount): int {
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    if (strlen($whole) > 14) throw new \DomainException('Amount exceeds supported range');
    return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
  }
}
