<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\ControlProjectSourceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Drupal entity adapter for active BREBO project ids. */
final class DrupalControlProjectSource implements ControlProjectSourceInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function activeProjectIds(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->execute();

    return array_values(array_map('intval', $ids));
  }

}
