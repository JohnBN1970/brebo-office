<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Compatibility redirect from retired Office workbench URLs to the Office dashboard.
 */
final class CalculationWorkspaceRedirectController extends ControllerBase {

  public function redirect(int $calculation): RedirectResponse {
    if ($calculation <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    return new RedirectResponse(
      Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $calculation])->toString(),
    );
  }

}
