<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Maps website project requests to canonical CRM lead fields.
 *
 * This mapper is framework independent and does not write a shadow CRM record.
 */
final class WebsiteOpportunityMapper {

  /** @param array<string,mixed> $payload
   * @return array<string,mixed>
   */
  public function map(string $requestId, array $payload): array {
    $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
    $selected = is_array($payload['selected'] ?? null) ? $payload['selected'] : [];
    $observed = is_array($payload['observed'] ?? null) ? $payload['observed'] : [];
    $building = is_array($observed['building'] ?? null) ? $observed['building'] : [];
    $label = trim((string) ($metadata['project_name'] ?? $metadata['address'] ?? $selected['project_name'] ?? $observed['project_name'] ?? $building['address'] ?? ''));
    if ($label === '') {
      $label = 'Nieuwe aanvraag';
    }
    $scope = is_array($payload['preliminary_scope'] ?? null)
      ? $payload['preliminary_scope']
      : (is_array($payload['calculated']['preliminary_scope'] ?? null) ? $payload['calculated']['preliminary_scope'] : []);
    $lines = ['Websiteproject: ' . mb_substr($label, 0, 140)];
    $summary = trim((string) ($scope['summary'] ?? ''));
    $items = is_array($scope['items'] ?? null) ? $scope['items'] : [];
    if ($summary !== '' || $items !== []) {
      $lines[] = 'VOORLOPIGE MACHINESCOPE - nog controleren';
      if ($summary !== '') {
        $lines[] = $summary;
      }
      foreach (array_slice($items, 0, 50) as $item) {
        if (!is_array($item)) {
          continue;
        }
        $reference = trim((string) ($item['reference'] ?? ''));
        if ($reference === '') {
          continue;
        }
        $parts = [$reference];
        $quantity = $item['quantity']['value'] ?? null;
        if (is_int($quantity) || (is_string($quantity) && ctype_digit($quantity))) {
          $parts[] = 'aantal ' . (int) $quantity;
        }
        $dimensions = $item['dimensions']['value'] ?? null;
        if (is_array($dimensions) && isset($dimensions['width_mm'], $dimensions['height_mm'])) {
          $parts[] = (int) $dimensions['width_mm'] . ' x ' . (int) $dimensions['height_mm'] . ' mm';
        }
        foreach (['material' => 'materiaal', 'glass' => 'glas', 'state' => 'status'] as $field => $fieldLabel) {
          $value = $item[$field]['value'] ?? null;
          if (is_scalar($value) && trim((string) $value) !== '') {
            $parts[] = $fieldLabel . ' ' . trim((string) $value);
          }
        }
        $missing = is_array($item['missing_fields'] ?? null) ? $item['missing_fields'] : [];
        if ($missing !== []) {
          $parts[] = 'open: ' . implode(', ', array_map('strval', $missing));
        }
        $lines[] = '- ' . implode(' | ', $parts);
      }
      $conflicts = is_array($scope['conflicts'] ?? null) ? $scope['conflicts'] : [];
      if ($conflicts !== []) {
        $lines[] = 'Open conflicten: ' . count($conflicts) . '.';
      }
    }
    return [
      'title' => sprintf('Europakozijn - aanvraag [%s]', substr($requestId, 0, 8)),
      'stage' => 'Lead',
      'probability' => 10,
      'active' => true,
      'lead_source' => 'Website - Europakozijn',
      'acquisition_channel' => 'Portaal',
      'next_action' => 'Projectstukken en automatisch herkende gegevens beoordelen.',
      'requirement_text' => mb_substr(implode("\n", $lines), 0, 10000),
      'preliminary_scope' => $scope,
      'requires_review' => true,
    ];
  }
}
