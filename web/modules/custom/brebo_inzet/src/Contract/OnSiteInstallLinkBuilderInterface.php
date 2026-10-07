<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Builds the personal OnSite installation link. */
interface OnSiteInstallLinkBuilderInterface {

  public function build(string $language, string $activationToken): string;

}
