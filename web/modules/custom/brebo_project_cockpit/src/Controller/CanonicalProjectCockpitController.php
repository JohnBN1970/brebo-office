<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/** Keeps one calm project overview above the canonical project dossier. */
final class CanonicalProjectCockpitController extends ControllerBase {

  public function title(NodeInterface $node): string {
    return $this->legacyController()->title($node);
  }

  public function overview(NodeInterface $node): array {
    $build = $this->legacyController()->overview($node);
    $projectId = (int) $node->id();

    // One fixed dossier navigation. Detail belongs in the dossier pages, not
    // duplicated on the project overview.
    $build['tabs'] = _brebo_project_cockpit_tabs(
      $projectId,
      'brebo_project_cockpit.overview',
    );

    // The overview answers four plain questions:
    // 1. Hoe staat het project ervoor? 2. Wat vraagt aandacht?
    // 3. Lopen tijd en geld volgens plan? 4. Wat kan ik nu doen?
    unset($build['money'], $build['revenue'], $build['steering']);

    if (isset($build['attention']) && is_array($build['attention'])) {
      $build['attention']['#title'] = $this->t('Dit vraagt aandacht');
      $build['attention']['#attributes']['class'][] = 'brebo-project-cockpit__attention';
      $build['attention']['#weight'] = 20;
    }

    if (isset($build['progress']) && is_array($build['progress'])) {
      $build['progress']['#caption'] = $this->t('Hoe staat het project ervoor?');
      $build['progress']['#attributes']['class'][] = 'brebo-project-cockpit__core-progress';

      $plainLabels = [
        'Uitgevoerde voortgang' => 'Werk gereed',
        'Projecttijd verstreken' => 'Tijd verstreken',
        'Voortgang t.o.v. tijd' => 'Voor of achter op planning',
        'Kosten gerealiseerd' => 'Uitgevoerde kosten',
        'Prognose eindmarge' => 'Verwachte marge bij oplevering',
      ];

      $rows = [];
      foreach ($build['progress']['#rows'] ?? [] as $row) {
        $label = (string) ($row[0] ?? '');
        if (!isset($plainLabels[$label])) {
          continue;
        }
        $row[0] = $this->t($plainLabels[$label]);
        $rows[] = $row;
      }
      $build['progress']['#rows'] = $rows;
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

      // project-cockpit.js deliberately removes the old brebo-list-actions
      // menu. This is now the canonical action group, so drop that legacy
      // marker before the behavior runs.
      $classes = $build['quick_actions']['#attributes']['class'] ?? [];
      $build['quick_actions']['#attributes']['class'] = array_values(array_filter(
        $classes,
        static fn(string $class): bool => $class !== 'brebo-list-actions',
      ));
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
