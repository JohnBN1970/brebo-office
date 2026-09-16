<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/** Keeps the existing steering cockpit while enforcing one project dossier nav. */
final class CanonicalProjectCockpitController extends ControllerBase {

  public function title(NodeInterface $node): string {
    return $this->legacyController()->title($node);
  }

  public function overview(NodeInterface $node): array {
    $build = $this->legacyController()->overview($node);
    $build['tabs'] = _brebo_project_cockpit_tabs(
      (int) $node->id(),
      'brebo_project_cockpit.overview',
    );

    return $build;
  }

  private function legacyController(): ProjectCockpitController {
    return ProjectCockpitController::create(\Drupal::getContainer());
  }

}
