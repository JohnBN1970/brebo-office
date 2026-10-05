<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\brebo_office_core\Contract\AdministrationRegistrySourceInterface;

/** Canonical registry for legal entities / financial administrations in Office. */
final class AdministrationRegistry {

  public function __construct(private readonly AdministrationRegistrySourceInterface $source) {}

  /** @return array<string, array<string, mixed>> */
  public function all(): array {
    $configured = $this->source->administrations();
    if ($configured !== []) return $configured;
    return ['primary' => $this->legacyPrimaryAdministration()];
  }

  public function primaryCode(): string {
    $requested = $this->source->primaryAdministrationCode();
    $all = $this->all();
    return $requested !== '' && isset($all[$requested]) ? $requested : (string) array_key_first($all);
  }

  /** @return array<string, mixed> */
  public function primary(): array { return $this->get($this->primaryCode()); }

  /** @return array<string, mixed> */
  public function get(string $code): array {
    $all = $this->all();
    if (!isset($all[$code])) throw new \InvalidArgumentException(sprintf('Onbekende administratie: %s', $code));
    return $all[$code];
  }

  /** @return array<string, mixed> */
  private function legacyPrimaryAdministration(): array {
    $settings = $this->source->legacySettings();
    $tradeName = trim((string) ($settings['trade_name'] ?? 'BREBO'));
    return [
      'code' => 'primary',
      'active' => TRUE,
      'trade_name' => $tradeName,
      'legal_name' => (string) ($settings['legal_name'] ?? 'BREBO Bouw en Advies B.V.'),
      'registration_number' => (string) ($settings['registration_number'] ?? ''),
      'vat_number' => (string) ($settings['vat_number'] ?? ''),
      'address' => (string) ($settings['address'] ?? ''),
      'postal_code' => (string) ($settings['postal_code'] ?? ''),
      'city' => (string) ($settings['city'] ?? ''),
      'country' => (string) ($settings['country'] ?? 'NL'),
      'general_email' => (string) ($settings['general_email'] ?? ''),
      'general_phone' => (string) ($settings['general_phone'] ?? ''),
      'website' => (string) ($settings['website'] ?? ''),
      'logo_uri' => (string) ($settings['logo_uri'] ?? ''),
      'logo_compact_uri' => (string) ($settings['logo_compact_uri'] ?? ''),
      'currency' => (string) ($settings['currency'] ?? 'EUR'),
      'timezone' => (string) ($settings['timezone'] ?? 'Europe/Amsterdam'),
      'default_iban' => (string) ($settings['default_iban'] ?? ''),
      'bic' => (string) ($settings['bic'] ?? ''),
      'moneybird_administration_id' => (string) ($settings['moneybird_administration_id'] ?? ''),
      'numbering' => $this->defaultNumbering($this->legacyProjectPrefix($tradeName)),
    ];
  }

  private function legacyProjectPrefix(string $tradeName): string {
    $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $tradeName));
    return $prefix !== '' ? substr($prefix, 0, 16) : 'PRJ';
  }

  /** @return array<string, array<string, mixed>> */
  private function defaultNumbering(string $projectPrefix): array {
    return [
      'project' => $this->series($projectPrefix, 1),
      'quotation' => $this->series('OFF', 1),
      'assignment' => $this->series('OPD', 1),
      'sales_invoice' => $this->series('VF', 1),
      'credit_invoice' => $this->series('CR', 1),
      'purchase' => $this->series('INK', 1),
      'contract' => $this->series('CTR', 1),
      'report' => $this->series('RAP', 1),
      'inspection' => $this->series('INS', 1),
      'document' => $this->series('DOC', 1),
    ];
  }

  /** @return array<string, mixed> */
  private function series(string $prefix, int $start): array {
    return ['prefix' => $prefix, 'include_year' => TRUE, 'separator' => '-', 'digits' => 4, 'start_number' => $start, 'reset_yearly' => TRUE];
  }

}
