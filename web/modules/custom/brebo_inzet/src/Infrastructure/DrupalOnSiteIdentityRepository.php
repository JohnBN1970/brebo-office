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

  public function activeByUid(int $uid): ?array {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user instanceof UserInterface || !$user->isActive()) {
      return NULL;
    }

    $mobile = $user->hasField('field_brebo_mobile')
      ? trim((string) $user->get('field_brebo_mobile')->value)
      : '';
    $language = '';
    if ($user->hasField('field_brebo_onsite_language')) {
      $language = trim((string) $user->get('field_brebo_onsite_language')->value);
    }
    if ($language === '') {
      $language = trim((string) $user->getPreferredLangcode());
    }

    return [
      'uid' => (int) $user->id(),
      'mobile' => $mobile,
      'language' => $language !== '' ? $language : 'nl',
    ];
  }

}
