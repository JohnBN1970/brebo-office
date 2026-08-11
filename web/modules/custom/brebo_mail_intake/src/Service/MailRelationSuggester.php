<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Suggests building/project relations without establishing canonical truth.
 */
final class MailRelationSuggester {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array{building_id:int|null,project_id:int|null,confidence:float,basis:string}
   */
  public function suggest(string $subject, string $body): array {
    $haystack = $this->normalize($subject . "\n" . $body);
    $project = $this->findUniqueLabelMatch('brebo_project', $haystack);
    $building = $this->findUniqueLabelMatch('brebo_building', $haystack);
    $basis = [];
    $confidence = 0.0;

    if ($project instanceof NodeInterface) {
      $basis[] = sprintf('Unieke projectnaam letterlijk herkend: "%s".', $project->label());
      $confidence = 98.0;

      if (!$building instanceof NodeInterface && $project->hasField('field_brebo_building_refs')) {
        $projectBuildings = $project->get('field_brebo_building_refs')->referencedEntities();
        if (count($projectBuildings) === 1 && $projectBuildings[0] instanceof NodeInterface) {
          $building = $projectBuildings[0];
          $basis[] = 'Gebouw voorgesteld via de unieke permanente gebouwrelatie van het herkende project.';
          $confidence = min($confidence, 95.0);
        }
      }
    }

    if ($building instanceof NodeInterface) {
      $basis[] = sprintf('Unieke gebouwnaam letterlijk herkend of eenduidig via project afgeleid: "%s".', $building->label());
      $confidence = $confidence > 0 ? min($confidence, 98.0) : 98.0;
    }

    if (!$project instanceof NodeInterface && !$building instanceof NodeInterface) {
      return [
        'building_id' => NULL,
        'project_id' => NULL,
        'confidence' => 0.0,
        'basis' => 'Geen unieke letterlijke gebouw- of projectnaam gevonden; geen koppeling voorgesteld.',
      ];
    }

    return [
      'building_id' => $building instanceof NodeInterface ? (int) $building->id() : NULL,
      'project_id' => $project instanceof NodeInterface ? (int) $project->id() : NULL,
      'confidence' => $confidence,
      'basis' => implode(' ', $basis),
    ];
  }

  private function findUniqueLabelMatch(string $bundle, string $haystack): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }

    $matches = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $label = $this->normalize($node->label());
      if (mb_strlen($label) >= 5 && str_contains($haystack, $label)) {
        $matches[] = $node;
      }
    }

    return count($matches) === 1 ? $matches[0] : NULL;
  }

  private function normalize(string $value): string {
    $value = mb_strtolower(trim($value));
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
  }

}
