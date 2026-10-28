<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/** Native Office settings adapter. Never fall back to Drupal or assumed VAT rates. */
final class OfficeVatRegimeCatalog {
  public function __construct(private readonly PDO $db) {}

  /** @return array<string,string> Active code => display label. */
  public function activeOptions(): array {
    if (getenv('OFFICE_VAT_REGIMES_SCHEMA_VERIFIED') !== 'enabled') {
      throw new \DomainException('Office VAT settings not verified');
    }
    $q = $this->db->query('SELECT code, label FROM office_vat_regimes WHERE active=1 ORDER BY sort_order, code');
    $options = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $code = (string)($row['code'] ?? '');
      $label = trim((string)($row['label'] ?? ''));
      if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $code) || $label === '') {
        throw new \DomainException('Invalid Office VAT regime configuration');
      }
      $options[$code] = $label;
    }
    if ($options === []) throw new \DomainException('No active Office VAT regimes');
    return $options;
  }
}
