<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Resolves legal, branding and sender identity for administration documents. */
final class AdministrationDocumentIdentity {

  public function __construct(private readonly AdministrationRegistry $administrations) {}

  /** @return array<string, mixed> */
  public function forAdministration(string $administrationCode): array {
    $administration = $this->administrations->get($administrationCode);
    return [
      'administration_code' => $administrationCode,
      'trade_name' => trim((string) ($administration['trade_name'] ?? '')),
      'legal_name' => trim((string) ($administration['legal_name'] ?? '')),
      'registration_number' => trim((string) ($administration['registration_number'] ?? '')),
      'vat_number' => trim((string) ($administration['vat_number'] ?? '')),
      'address' => trim((string) ($administration['address'] ?? '')),
      'postal_code' => trim((string) ($administration['postal_code'] ?? '')),
      'city' => trim((string) ($administration['city'] ?? '')),
      'country' => trim((string) ($administration['country'] ?? '')),
      'email' => trim((string) ($administration['general_email'] ?? '')),
      'phone' => trim((string) ($administration['general_phone'] ?? '')),
      'website' => trim((string) ($administration['website'] ?? '')),
      'logo_uri' => trim((string) ($administration['logo_uri'] ?? '')),
      'logo_compact_uri' => trim((string) ($administration['logo_compact_uri'] ?? '')),
      'iban' => trim((string) ($administration['default_iban'] ?? '')),
      'bic' => trim((string) ($administration['bic'] ?? '')),
      'currency' => trim((string) ($administration['currency'] ?? 'EUR')),
      'timezone' => trim((string) ($administration['timezone'] ?? 'Europe/Amsterdam')),
    ];
  }

  /** Returns an immutable value suitable for final document snapshots. */
  public function snapshot(string $administrationCode): array {
    return $this->forAdministration($administrationCode) + [
      'snapshot_at' => gmdate(DATE_ATOM),
    ];
  }

}
