<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/** Thin host page for the BREBO calculation software workspace. */
final class CalculationWorkspaceShellController extends ControllerBase {

  public function workspace(int $calculation): array {
    if ($calculation <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'brebo-calculation-workspace-v2',
        'class' => ['brebo-software-workspace'],
        'data-calculation-id' => (string) $calculation,
        'data-state-url' => Url::fromRoute('brebo_calculation.workspace_v2_state', [
          'calculation' => $calculation,
        ])->toString(),
      ],
      'loading' => [
        '#markup' => '<div class="brebo-software-workspace__loading">Calculatie laden…</div>',
      ],
      '#attached' => [
        'library' => ['brebo_calculation/workspace_v2'],
      ],
    ];
  }

}
