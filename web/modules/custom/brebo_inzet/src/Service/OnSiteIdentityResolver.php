<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Resolves an active Office user from the mobile number used by OnSite.
 */
final class OnSiteIdentityResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function normalizeMobile(string $mobile): string {
    $mobile = trim($mobile);
    if ($mobile === '') {
      return '';
    }

    $prefix = str_starts_with($mobile, '+') ? '+' : '';
    $digits = preg_replace('/\D+/', '', $mobile) ?? '';
    return $digits === '' ? '' : $prefix . $digits;
  }

  public function resolveByMobile(string $mobile): ?UserInterface {
    $normalized = $this->normalizeMobile($mobile);
    if ($normalized === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('field_brebo_mobile', $normalized)
      ->range(0, 2)
      ->execute();

    if (count($ids) > 1) {
      throw new \RuntimeException('OnSite mobiel nummer is aan meerdere actieve gebruikers gekoppeld.');
    }
    if ($ids === []) {
      return NULL;
    }

    $user = $storage->load((int) reset($ids));
    return $user instanceof UserInterface ? $user : NULL;
  }

  public function languageFor(UserInterface $user): string {
    if ($user->hasField('field_brebo_onsite_language')) {
      $configured = trim((string) $user->get('field_brebo_onsite_language')->value);
      if ($configured !== '') {
        return $configured;
      }
    }

    $preferred = trim((string) $user->getPreferredLangcode());
    return $preferred !== '' ? $preferred : 'nl';
  }

}
