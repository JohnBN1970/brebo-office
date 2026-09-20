<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/** Access check for BREBO sales settings. */
final class SalesSettingsAccess {

  public static function access(AccountInterface $account): AccessResultInterface {
    $allowed = $account->hasPermission('approve brebo finance')
      || $account->hasPermission('approve brebo finance executive')
      || $account->hasPermission('administer site configuration');

    return AccessResult::allowedIf($allowed)->cachePerPermissions();
  }

}
