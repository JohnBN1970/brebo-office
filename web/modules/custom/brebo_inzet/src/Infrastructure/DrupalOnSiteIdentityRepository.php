<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteIdentityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/** Drupal user adapter for OnSite identity reads. */
final class DrupalOnSiteIdentityRepository implements OnSiteIdentityRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function activeByMobile(string $normalizedMobile): array {
    $storage = $this->entityTypeManager->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('field_brebo_mobile', $normalizedMobile)
      ->range(0, 2)
      ->execute();

    $identities = [];
    foreach ($storage->loadMultiple($ids) as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $language = '';
      if ($user->hasField('field_brebo_onsite_language')) {
        $language = trim((string) $user->get('field_brebo_onsite_language')->value);
      }
      if ($language === '') {
        $language = trim((string) $user->getPreferredLangcode());
      }
      $identities[] = [
        'uid' => (int) $user->id(),
        'mobile' => $normalizedMobile,
        'language' => $language !== '' ? $language : 'nl',
      ];
    }

    return $identities;
  }

}
