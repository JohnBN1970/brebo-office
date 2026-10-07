<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteInstallLinkBuilderInterface;
use Drupal\Core\Url;

/** Drupal route adapter for the OnSite installation link. */
final class DrupalOnSiteInstallLinkBuilder implements OnSiteInstallLinkBuilderInterface {

  public function build(string $language, string $activationToken): string {
    return Url::fromRoute('brebo_inzet.onsite_install', [], [
      'absolute' => TRUE,
      'query' => [
        'lang' => $language,
        'activation' => $activationToken,
      ],
    ])->toString();
  }

}
