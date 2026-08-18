<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/** Builds canonical procurement demand from a technically approved glass position. */
final class GlassProcurementBuilder {

  /** @param array<string,mixed> $position @return array<int,array<string,mixed>> */
  public function build(array $position): array {
    if (!in_array((string) ($position['technical_status'] ?? ''), ['approved', 'ordered'], TRUE)) {
      throw new \InvalidArgumentException('Alleen technisch vrijgegeven glasposities mogen naar inkoop.');
    }
    $id = (int) ($position['id'] ?? 0);
    $code = trim((string) ($position['position_code'] ?? ''));
    $quantity = (int) ($position['quantity'] ?? 0);
    if ($id <= 0 || $code === '' || $quantity <= 0) {
      throw new \InvalidArgumentException('Glaspositie mist verplichte inkoopgegevens.');
    }
    return [[
      'source_domain' => 'brebo_glass_position',
      'source_reference' => (string) $id,
      'description' => sprintf('Glaspositie %s - %s - %d x %d mm', $code, (string) ($position['composition'] ?? ''), (int) ($position['width_mm'] ?? 0), (int) ($position['height_mm'] ?? 0)),
      'quantity' => $quantity,
      'unit' => 'st',
      'specification' => [
        'position_code' => $code,
        'location' => (string) ($position['location'] ?? ''),
        'glass_type' => (string) ($position['glass_type'] ?? ''),
        'composition' => (string) ($position['composition'] ?? ''),
        'width_mm' => (int) ($position['width_mm'] ?? 0),
        'height_mm' => (int) ($position['height_mm'] ?? 0),
        'safety_class' => (string) ($position['safety_class'] ?? ''),
        'fire_class' => (string) ($position['fire_class'] ?? ''),
        'performance_declaration_ref' => (string) ($position['performance_declaration_ref'] ?? ''),
        'approval_reference' => (string) ($position['approval_reference'] ?? ''),
        'approval_checksum' => (string) ($position['approval_checksum'] ?? ''),
      ],
    ]];
  }
}
