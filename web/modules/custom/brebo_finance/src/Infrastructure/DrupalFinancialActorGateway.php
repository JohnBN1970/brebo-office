<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialActorGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Transitional Drupal adapter for Finance actor identity and permissions. */
final class DrupalFinancialActorGateway implements FinancialActorGatewayInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function activeActors(): array {
    $storage = $this->entityTypeManager->getStorage('user');
    $uids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 0, '>')
      ->sort('uid', 'ASC')
      ->execute();

    $actors = [];
    foreach ($storage->loadMultiple($uids) as $user) {
      $actors[] = [
        'uid' => (int) $user->id(),
        'display_name' => (string) $user->getDisplayName(),
        'mail' => (string) $user->getEmail(),
        'roles' => array_values($user->getRoles(TRUE)),
      ];
    }
    return $actors;
  }

  public function hasPermission(int $actorUid, string $permission): bool {
    if ($actorUid <= 0 || $permission === '') {
      return FALSE;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($actorUid);
    return $user !== NULL && $user->isActive() && $user->hasPermission($permission);
  }

}
