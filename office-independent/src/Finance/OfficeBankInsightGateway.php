<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Read-only Office boundary for bank insight.
 * The transport is server-side and must authenticate to the existing integration API.
 * No browser token, bank payment submission, or implicit account discovery.
 */
final class OfficeBankInsightGateway {
  /** @param callable(string,string,array):array $read Transport returns decoded provider response. */
  public function __construct(
    private readonly \Closure $read,
    private readonly \Closure $accountAllowed,
  ) {}

  public function balance(string $operatorId, string $iban): array {
    $account = self::normalizeIban($iban);
    $this->authorize($operatorId, $account);
    $result = ($this->read)('balance', $account, []);
    if (!is_array($result)) throw new \UnexpectedValueException('Invalid bank balance response');
    return $result;
  }

  public function bookedActivities(string $operatorId, string $iban, ?string $dateFrom = null, ?string $pageKey = null): array {
    $account = self::normalizeIban($iban);
    $this->authorize($operatorId, $account);
    if ($dateFrom !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateFrom)
      || !checkdate((int)substr($dateFrom, 5, 2), (int)substr($dateFrom, 8, 2), (int)substr($dateFrom, 0, 4)))) {
      throw new \InvalidArgumentException('Invalid activity start date');
    }
    if ($pageKey !== null && ($pageKey === '' || strlen($pageKey) > 512 || preg_match('/[\x00-\x1f]/', $pageKey))) {
      throw new \InvalidArgumentException('Invalid pagination cursor');
    }
    $params = [];
    if ($dateFrom !== null) $params['dateFrom'] = $dateFrom;
    if ($pageKey !== null) $params['nextPageKey'] = $pageKey;
    $result = ($this->read)('activities', $account, $params);
    if (!is_array($result) || !isset($result['activities']) || !is_array($result['activities'])) {
      throw new \UnexpectedValueException('Invalid bank activities response');
    }
    foreach ($result['activities'] as $row) {
      if (!is_array($row) || !isset($row['transaction_id']) || !is_string($row['transaction_id']) || $row['transaction_id'] === '') {
        throw new \UnexpectedValueException('Bank activity missing transaction identity');
      }
    }
    return $result;
  }

  private function authorize(string $operatorId, string $account): void {
    if ($operatorId === '' || ($this->accountAllowed)($operatorId, $account) !== true) {
      throw new \DomainException('Bank account access denied');
    }
  }

  private static function normalizeIban(string $iban): string {
    $value = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $value)) {
      throw new \InvalidArgumentException('Invalid bank account');
    }
    return $value;
  }
}
