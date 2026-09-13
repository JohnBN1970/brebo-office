<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\node\NodeInterface;

/** Resolves the legal/financial administration for project-derived content. */
final class AdministrationContextResolver {

  private const COLLECTION = 'brebo_office_core.project_administration';

  public function __construct(
    private readonly AdministrationRegistry $registry,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  public function projectCode(NodeInterface $project): string {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Administration can only be assigned directly to a BREBO project.');
    }
    $stored = trim((string) $this->keyValueFactory->get(self::COLLECTION)->get((string) $project->id(), ''));
    if ($stored !== '') {
      try {
        $this->registry->get($stored);
        return $stored;
      }
      catch (\InvalidArgumentException) {
        // A removed/renamed administration must never break an existing dossier.
      }
    }
    return $this->registry->primaryCode();
  }

  public function assignProject(NodeInterface $project, string $code): void {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Administration can only be assigned directly to a BREBO project.');
    }
    $this->registry->get($code);
    if ($project->isNew() || !$project->id()) {
      throw new \LogicException('Save the project before persisting its administration assignment.');
    }
    $this->keyValueFactory->get(self::COLLECTION)->set((string) $project->id(), $code);
  }

  /** @return array<string, mixed> */
  public function forProject(NodeInterface $project): array {
    return $this->registry->get($this->projectCode($project));
  }

  public function codeForNode(NodeInterface $node): string {
    $project = $this->projectForNode($node);
    return $project instanceof NodeInterface ? $this->projectCode($project) : $this->registry->primaryCode();
  }

  /** @return array<string, mixed> */
  public function forNode(NodeInterface $node): array {
    return $this->registry->get($this->codeForNode($node));
  }

  public function projectForNode(NodeInterface $node): ?NodeInterface {
    if ($node->bundle() === 'brebo_project') {
      return $node;
    }

    // Most project-derived entities point directly at the project.
    if ($node->hasField('field_brebo_project_ref')) {
      $project = $node->get('field_brebo_project_ref')->entity;
      if ($project instanceof NodeInterface && $project->bundle() === 'brebo_project') {
        return $project;
      }
    }

    // Calculations typically inherit their project via the work package.
    if ($node->hasField('field_brebo_package_ref')) {
      $package = $node->get('field_brebo_package_ref')->entity;
      if ($package instanceof NodeInterface) {
        $project = $this->projectForNode($package);
        if ($project instanceof NodeInterface) {
          return $project;
        }
      }
    }

    // Offers and other derived records can point back to their calculation.
    if ($node->hasField('field_brebo_calculation_ref')) {
      $calculation = $node->get('field_brebo_calculation_ref')->entity;
      if ($calculation instanceof NodeInterface) {
        return $this->projectForNode($calculation);
      }
    }

    return NULL;
  }

}
