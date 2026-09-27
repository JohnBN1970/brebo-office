<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** Redirects the legacy Office calculation URL to the BREBO-native workbench. */
final class CalculationWorkspaceRedirectController extends ControllerBase {

  public function redirect(int $node): RedirectResponse {
    if ($node <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    return new RedirectResponse(
      Url::fromRoute('brebo_calculation.workbench', ['calculation' => $node])->toString(),
    );
  }

}
