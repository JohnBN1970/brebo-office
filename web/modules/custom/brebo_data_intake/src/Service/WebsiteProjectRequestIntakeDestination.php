<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeDestinationInterface;
use Drupal\brebo_data_intake\ValueObject\IntakeDestinationResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;

/** Creates the canonical CRM lead for a Europakozijn website request. */
final class WebsiteProjectRequestIntakeDestination implements IntakeDestinationInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function supports(string $classification): bool {
    return $classification === 'website_project_request';
  }

  /**
   * @param array<string, mixed> $envelope
   */
  public function route(array $envelope): IntakeDestinationResult {
    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
    $requestId = trim((string) ($payload['request_id'] ?? ''));
    if (!preg_match('/^[0-9a-f-]{36}$/i', $requestId)) {
      return new IntakeDestinationResult(
        IntakeDestinationResult::REVIEW_REQUIRED,
        'invalid_website_request_id',
      );
    }

    $ownerUid = (int) Settings::get('brebo_website_lead_owner_uid', 1);
    $userStorage = $this->entityTypeManager->getStorage('user');
    $owner = $ownerUid > 0 ? $userStorage->load($ownerUid) : NULL;
    if ($owner === NULL || !$owner->isActive()) {
      return new IntakeDestinationResult(
        IntakeDestinationResult::REVIEW_REQUIRED,
        'lead_owner_unavailable',
        ['configured_owner_uid' => $ownerUid],
      );
    }

    $metadata = is_array($payload['metadata'] ?? NULL) ? $payload['metadata'] : [];
    $filename = trim((string) ($payload['filename'] ?? ''));
    $label = trim((string) ($metadata['project_name'] ?? $metadata['address'] ?? ''));
    if ($label === '') {
      $label = $filename !== '' ? pathinfo($filename, PATHINFO_FILENAME) : 'Nieuwe aanvraag';
    }
    $label = mb_substr($label, 0, 140);
    $title = sprintf('Europakozijn - %s [%s]', $label, substr($requestId, 0, 8));

    $storage = $this->entityTypeManager->getStorage('node');
    $existingIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_opportunity')
      ->condition('title', $title)
      ->range(0, 1)
      ->execute();
    if ($existingIds !== []) {
      $existing = $storage->load((int) reset($existingIds));
      if ($existing instanceof NodeInterface) {
        return new IntakeDestinationResult(
          IntakeDestinationResult::REVIEW_REQUIRED,
          'website_project_request_requires_review',
          ['opportunity_id' => (int) $existing->id(), 'lead_duplicate' => TRUE],
        );
      }
    }

    $lead = $storage->create([
      'type' => 'brebo_opportunity',
      'title' => $title,
      'status' => 1,
      'uid' => $ownerUid,
    ]);

    $values = [
      'field_brebo_opp_stage' => 'Lead',
      'field_brebo_opp_probability' => 10,
      'field_brebo_opp_active' => 1,
      'field_brebo_opp_source' => 'Website - Europakozijn',
      'field_brebo_opp_channel' => 'Portaal',
      'field_brebo_opp_next_action' => 'Projectstukken en automatisch herkende gegevens beoordelen.',
      'field_brebo_opp_owner' => ['target_id' => $ownerUid],
    ];

    $scope = is_array($payload['preliminary_scope'] ?? NULL) ? $payload['preliminary_scope'] : [];
    $scopeText = $this->scopeText($scope);
    if ($scopeText !== '') {
      $values['field_brebo_opp_requirement'] = $scopeText;
    }

    foreach ($values as $fieldName => $value) {
      if ($lead->hasField($fieldName)) {
        $lead->set($fieldName, $value);
      }
    }

    $lead->save();

    // The lead is created immediately, while each source attachment remains in
    // the central intake workbench until the machine result has been reviewed.
    return new IntakeDestinationResult(
      IntakeDestinationResult::REVIEW_REQUIRED,
      'website_project_request_requires_review',
      [
        'opportunity_id' => (int) $lead->id(),
        'lead_duplicate' => FALSE,
        'preliminary_scope_available' => $scopeText !== '',
      ],
    );
  }

  /** @param array<string,mixed> $scope */
  private function scopeText(array $scope): string {
    $summary = trim((string) ($scope['summary'] ?? ''));
    $items = is_array($scope['items'] ?? NULL) ? $scope['items'] : [];
    if ($summary === '' && $items === []) {
      return '';
    }

    $lines = ['VOORLOPIGE MACHINESCOPE - nog controleren'];
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
      $quantity = $item['quantity']['value'] ?? NULL;
      if (is_int($quantity) || ctype_digit((string) $quantity)) {
        $parts[] = 'aantal ' . (int) $quantity;
      }
      $dimensions = $item['dimensions']['value'] ?? NULL;
      if (is_array($dimensions) && isset($dimensions['width_mm'], $dimensions['height_mm'])) {
        $parts[] = (int) $dimensions['width_mm'] . ' x ' . (int) $dimensions['height_mm'] . ' mm';
      }
      foreach (['material' => 'materiaal', 'glass' => 'glas', 'state' => 'status'] as $field => $fieldLabel) {
        $value = $item[$field]['value'] ?? NULL;
        if (is_scalar($value) && trim((string) $value) !== '') {
          $parts[] = $fieldLabel . ' ' . trim((string) $value);
        }
      }
      $missing = is_array($item['missing_fields'] ?? NULL) ? $item['missing_fields'] : [];
      if ($missing !== []) {
        $parts[] = 'open: ' . implode(', ', array_map('strval', $missing));
      }
      $lines[] = '- ' . implode(' | ', $parts);
    }

    $conflicts = is_array($scope['conflicts'] ?? NULL) ? $scope['conflicts'] : [];
    if ($conflicts !== []) {
      $lines[] = 'Open conflicten: ' . count($conflicts) . '.';
    }
    return mb_substr(implode("\n", $lines), 0, 10000);
  }

}
