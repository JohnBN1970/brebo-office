<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Infrastructure;

use Drupal\brebo_data_intake\Contract\WebsiteOpportunityGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

final class DrupalWebsiteOpportunityGateway implements WebsiteOpportunityGatewayInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function configuredOwnerUid(): int {
    return (int) Settings::get('brebo_website_lead_owner_uid', 1);
  }

  public function ownerIsActive(int $ownerUid): bool {
    if ($ownerUid <= 0) {
      return FALSE;
    }
    $owner = $this->entityTypeManager->getStorage('user')->load($ownerUid);
    return $owner !== NULL && $owner->isActive();
  }

  public function findOpportunityIdByTitle(string $title): ?int {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_opportunity')
      ->condition('title', $title)
      ->range(0, 1)
      ->execute();

    return $ids === [] ? NULL : (int) reset($ids);
  }

  public function createOpportunity(string $title, int $ownerUid, array $values): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $lead = $storage->create([
      'type' => 'brebo_opportunity',
      'title' => $title,
      'status' => 1,
      'uid' => $ownerUid,
    ]);

    foreach ($values as $fieldName => $value) {
      if ($lead->hasField($fieldName)) {
        $lead->set($fieldName, $value);
      }
    }

    $lead->save();
    return (int) $lead->id();
  }

}
