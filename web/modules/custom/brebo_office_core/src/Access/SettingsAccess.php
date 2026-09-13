<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/** Access callback for the central BREBO Office settings page. */
final class SettingsAccess {

  public static function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf(
      $account->hasPermission('administer site configuration')
      || $account->hasPermission('approve brebo finance executive'),
    )->cachePerPermissions();
  }

}
