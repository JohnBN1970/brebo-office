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
    $requestId = trim((string) ($envelope['source_record_id'] ?? ''));
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

    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
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
    foreach ($values as $fieldName => $value) {
      if ($lead->hasField($fieldName)) {
        $lead->set($fieldName, $value);
      }
    }

    $lead->save();

    // The lead is created immediately, while the source envelope remains in the
    // central intake workbench until the machine result has been reviewed.
    return new IntakeDestinationResult(
      IntakeDestinationResult::REVIEW_REQUIRED,
      'website_project_request_requires_review',
      [
        'opportunity_id' => (int) $lead->id(),
        'lead_duplicate' => FALSE,
      ],
    );
  }

}
