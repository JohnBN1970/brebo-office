<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailContextReadRepositoryInterface;

/**
 * Suggests building/project relations without establishing canonical truth.
 */
final class MailRelationSuggester {

  public function __construct(
    private readonly MailContextReadRepositoryInterface $contextRepository,
  ) {}

  /**
   * @return array{building_id:int|null,project_id:int|null,confidence:float,basis:string}
   */
  public function suggest(string $subject, string $body): array {
    $haystack = $this->normalize($subject . "\n" . $body);
    $project = $this->findUniqueLabelMatch($this->contextRepository->activeProjects(), $haystack);
    $building = $this->findUniqueLabelMatch($this->contextRepository->activeBuildings(), $haystack);
    $basis = [];
    $confidence = 0.0;

    if ($project !== NULL) {
      $basis[] = sprintf('Unieke projectnaam letterlijk herkend: "%s".', $project['label']);
      $confidence = 98.0;

      if ($building === NULL && count($project['building_ids']) === 1) {
        $building = $this->contextRepository->building((int) $project['building_ids'][0]);
        if ($building !== NULL) {
          $basis[] = 'Gebouw voorgesteld via de unieke permanente gebouwrelatie van het herkende project.';
          $confidence = min($confidence, 95.0);
        }
      }
    }

    if ($building !== NULL) {
      $basis[] = sprintf('Unieke gebouwnaam letterlijk herkend of eenduidig via project afgeleid: "%s".', $building['label']);
      $confidence = $confidence > 0 ? min($confidence, 98.0) : 98.0;
    }

    if ($project === NULL && $building === NULL) {
      return [
        'building_id' => NULL,
        'project_id' => NULL,
        'confidence' => 0.0,
        'basis' => 'Geen unieke letterlijke gebouw- of projectnaam gevonden; geen koppeling voorgesteld.',
      ];
    }

    return [
      'building_id' => $building['id'] ?? NULL,
      'project_id' => $project['id'] ?? NULL,
      'confidence' => $confidence,
      'basis' => implode(' ', $basis),
    ];
  }

  /**
   * @param list<array<string,mixed>> $items
   * @return array<string,mixed>|null
   */
  private function findUniqueLabelMatch(array $items, string $haystack): ?array {
    $matches = [];
    foreach ($items as $item) {
      $label = $this->normalize((string) ($item['label'] ?? ''));
      if (mb_strlen($label) >= 5 && str_contains($haystack, $label)) {
        $matches[] = $item;
      }
    }
    return count($matches) === 1 ? $matches[0] : NULL;
  }

  private function normalize(string $value): string {
    $value = mb_strtolower(trim($value));
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
  }

}
