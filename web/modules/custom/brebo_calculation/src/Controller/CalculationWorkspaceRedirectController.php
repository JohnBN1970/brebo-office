<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Compatibility redirect from retired Office workbench URLs to the signed Calc launch.
 */
final class CalculationWorkspaceRedirectController extends ControllerBase {

  public function redirectToCalc(int $calculation): RedirectResponse {
    if ($calculation <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    return new RedirectResponse(
      Url::fromRoute('brebo_office_core.calc_workbench_launch', ['calculation' => $calculation])->toString(),
    );
  }

}
