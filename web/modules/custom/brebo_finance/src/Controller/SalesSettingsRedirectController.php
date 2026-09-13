<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** Redirects legacy module settings to the central Office settings page. */
final class SalesSettingsRedirectController extends ControllerBase {

  public function redirect(): RedirectResponse {
    $url = Url::fromRoute('brebo_office_core.settings', [], ['fragment' => 'sales-settings'])->toString();
    return new RedirectResponse($url);
  }

}
