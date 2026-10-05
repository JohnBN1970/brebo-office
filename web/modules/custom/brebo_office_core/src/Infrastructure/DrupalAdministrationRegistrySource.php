<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\AdministrationRegistrySourceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

final class DrupalAdministrationRegistrySource implements AdministrationRegistrySourceInterface {

  private const CONFIG_NAME = 'brebo_office_core.settings';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function administrations(): array {
    $configured = $this->configFactory->get(self::CONFIG_NAME)->get('administrations');
    return is_array($configured) ? $configured : [];
  }

  public function primaryAdministrationCode(): string {
    return trim((string) ($this->configFactory
      ->get(self::CONFIG_NAME)
      ->get('primary_administration') ?? 'primary'));
  }

  public function legacySettings(): array {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return [
      'trade_name' => (string) ($config->get('organization.trade_name') ?? 'BREBO'),
      'legal_name' => (string) ($config->get('organization.legal_name') ?? 'BREBO Bouw en Advies B.V.'),
      'registration_number' => (string) ($config->get('organization.registration_number') ?? ''),
      'vat_number' => (string) ($config->get('organization.vat_number') ?? ''),
      'address' => (string) ($config->get('organization.address') ?? ''),
      'postal_code' => (string) ($config->get('organization.postal_code') ?? ''),
      'city' => (string) ($config->get('organization.city') ?? ''),
      'country' => (string) ($config->get('organization.country') ?? 'NL'),
      'general_email' => (string) ($config->get('organization.general_email') ?? ''),
      'general_phone' => (string) ($config->get('organization.general_phone') ?? ''),
      'website' => (string) ($config->get('organization.website') ?? ''),
      'logo_uri' => (string) ($config->get('organization.logo_uri') ?? ''),
      'logo_compact_uri' => (string) ($config->get('organization.logo_compact_uri') ?? ''),
      'currency' => (string) ($config->get('organization.currency') ?? 'EUR'),
      'timezone' => (string) ($config->get('project.timezone') ?? 'Europe/Amsterdam'),
      'default_iban' => (string) ($config->get('organization.default_iban') ?? ''),
      'bic' => (string) ($config->get('organization.bic') ?? ''),
      'moneybird_administration_id' => (string) ($config->get('organization.moneybird_administration_id') ?? ''),
    ];
  }

}
