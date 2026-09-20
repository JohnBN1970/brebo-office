<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/** Canonical registry for legal entities / financial administrations in Office. */
final class AdministrationRegistry {

  private const CONFIG_NAME = 'brebo_office_core.settings';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /** @return array<string, array<string, mixed>> */
  public function all(): array {
    $configured = $this->configFactory->get(self::CONFIG_NAME)->get('administrations');
    if (is_array($configured) && $configured !== []) return $configured;
    return ['primary' => $this->legacyPrimaryAdministration()];
  }

  public function primaryCode(): string {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $requested = trim((string) ($config->get('primary_administration') ?? 'primary'));
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
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return [
      'code' => 'primary', 'active' => TRUE,
      'trade_name' => (string) ($config->get('organization.trade_name') ?? 'BREBO'),
      'legal_name' => (string) ($config->get('organization.legal_name') ?? 'BREBO Bouw en Advies B.V.'),
      'registration_number' => (string) ($config->get('organization.registration_number') ?? ''),
      'vat_number' => (string) ($config->get('organization.vat_number') ?? ''),
      'address' => (string) ($config->get('organization.address') ?? ''), 'postal_code' => (string) ($config->get('organization.postal_code') ?? ''),
      'city' => (string) ($config->get('organization.city') ?? ''), 'country' => (string) ($config->get('organization.country') ?? 'NL'),
      'general_email' => (string) ($config->get('organization.general_email') ?? ''), 'general_phone' => (string) ($config->get('organization.general_phone') ?? ''),
      'website' => (string) ($config->get('organization.website') ?? ''), 'logo_uri' => (string) ($config->get('organization.logo_uri') ?? ''),
      'logo_compact_uri' => (string) ($config->get('organization.logo_compact_uri') ?? ''), 'currency' => (string) ($config->get('organization.currency') ?? 'EUR'),
      'timezone' => (string) ($config->get('project.timezone') ?? 'Europe/Amsterdam'), 'default_iban' => (string) ($config->get('organization.default_iban') ?? ''),
      'bic' => (string) ($config->get('organization.bic') ?? ''), 'moneybird_administration_id' => (string) ($config->get('organization.moneybird_administration_id') ?? ''),
      'numbering' => $this->defaultNumbering($this->legacyProjectPrefix($config)),
    ];
  }

  private function legacyProjectPrefix($config): string {
    $tradeName = trim((string) ($config->get('organization.trade_name') ?? ''));
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
