<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/** Keeps one calm steering overview above the canonical project dossier. */
final class CanonicalProjectCockpitController extends ControllerBase {

  public function title(NodeInterface $node): string {
    return $this->legacyController()->title($node);
  }

  public function overview(NodeInterface $node): array {
    $build = $this->legacyController()->overview($node);
    $projectId = (int) $node->id();

    // One fixed dossier navigation. Detail belongs in the dossier pages, not
    // duplicated on the steering overview.
    $build['tabs'] = _brebo_project_cockpit_tabs(
      $projectId,
      'brebo_project_cockpit.overview',
    );

    // The overview only answers four questions:
    // 1. What is the status? 2. What needs attention? 3. Are time and money
    // on course? 4. Which operational action can I take now?
    unset($build['money'], $build['revenue'], $build['steering']);

    if (isset($build['attention']) && is_array($build['attention'])) {
      $build['attention']['#title'] = $this->t('Aandacht');
      $build['attention']['#attributes']['class'][] = 'brebo-project-cockpit__attention';
      $build['attention']['#weight'] = 20;
    }

    if (isset($build['progress']) && is_array($build['progress'])) {
      $build['progress']['#caption'] = $this->t('Kernvoortgang');
      $build['progress']['#attributes']['class'][] = 'brebo-project-cockpit__core-progress';
      $build['progress']['#rows'] = array_values(array_filter(
        $build['progress']['#rows'] ?? [],
        static fn(array $row): bool => in_array((string) ($row[0] ?? ''), [
          'Uitgevoerde voortgang',
          'Projecttijd verstreken',
          'Voortgang t.o.v. tijd',
          'Kosten gerealiseerd',
          'Prognose eindmarge',
        ], TRUE),
      ));
      $build['progress']['#weight'] = 30;
    }

    if (isset($build['quick_actions']) && is_array($build['quick_actions'])) {
      foreach (array_keys($build['quick_actions']) as $key) {
        if (str_starts_with((string) $key, '#')) {
          continue;
        }
        if (!in_array($key, ['planning', 'finance', 'edit'], TRUE)) {
          unset($build['quick_actions'][$key]);
        }
      }
      $build['quick_actions']['#attributes']['class'][] = 'brebo-project-cockpit__primary-actions';
      $build['quick_actions']['#weight'] = 40;
    }

    $build['cockpit']['#weight'] = 0;
    $build['tabs']['#weight'] = 10;

    return $build;
  }

  private function legacyController(): ProjectCockpitController {
    return ProjectCockpitController::create(\Drupal::getContainer());
  }

}
