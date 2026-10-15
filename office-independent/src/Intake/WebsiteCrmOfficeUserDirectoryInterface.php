<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Implement against the authenticated independent Office identity provider.
 * A legacy Drupal UID is never sufficient evidence of an Office account.
 */
interface WebsiteCrmOfficeUserDirectoryInterface {

  public function isActiveOfficeUser(string $officeUserId): bool;

}
