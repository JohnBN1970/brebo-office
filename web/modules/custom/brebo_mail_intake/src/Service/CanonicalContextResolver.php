<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves mail text to existing canonical project/building context first.
 *
 * The resolver is deliberately side-effect free. Unknown context is reported
 * as a proposal candidate and is not silently established as canonical truth.
 */
final class CanonicalContextResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BagPdokClient $bagPdokClient,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function resolve(string $subject, string $body): array {
    $text = $this->normalize($subject . "\n" . $body);
    $project = $this->findBestProject($text);
    $building = $this->findBestBuilding($text);
    $basis = [];

    if ($project instanceof NodeInterface) {
      $basis[] = sprintf('Bestaand project herkend: "%s".', $project->label());
    }

    if (!($building instanceof NodeInterface) && $project instanceof NodeInterface && $project->hasField('field_brebo_building_refs')) {
      $projectBuildings = array_values(array_filter(
        $project->get('field_brebo_building_refs')->referencedEntities(),
        static fn ($entity): bool => $entity instanceof NodeInterface,
      ));
      if (count($projectBuildings) === 1) {
        $building = $projectBuildings[0];
        $basis[] = 'Gebouw eenduidig afgeleid uit de permanente gebouwrelatie van het project.';
      }
    }

    if ($building instanceof NodeInterface) {
      $basis[] = sprintf('Bestaand canoniek gebouw herkend: "%s".', $building->label());
    }

    $pdokCandidates = [];
    if (!($building instanceof NodeInterface)) {
      $addressQuery = $this->extractAddressQuery($subject, $body);
      if ($addressQuery !== '') {
        try {
          $pdokCandidates = $this->bagPdokClient->searchAddress($addressQuery, 5);
          if ($pdokCandidates !== []) {
            $basis[] = 'Geen bestaand gebouw gevonden; officiële PDOK-adreskandidaten beschikbaar voor menselijke beoordeling.';
          }
        }
        catch (\RuntimeException) {
          $basis[] = 'PDOK kon niet worden geraadpleegd; geen automatisch nieuw gebouw vastgesteld.';
        }
      }
    }

    return [
      'project_id' => $project instanceof NodeInterface ? (int) $project->id() : NULL,
      'building_id' => $building instanceof NodeInterface ? (int) $building->id() : NULL,
      'project_state' => $project instanceof NodeInterface ? 'existing' : 'provisional_required',
      'building_state' => $building instanceof NodeInterface ? 'existing' : 'provisional_required',
      'pdok_address_candidates' => $pdokCandidates,
      'requires_human_review' => !($project instanceof NodeInterface) || !($building instanceof NodeInterface),
      'basis' => implode(' ', $basis) ?: 'Geen bestaande canonieke project- of gebouwcontext herkend.',
    ];
  }

  private function findBestProject(string $text): ?NodeInterface {
    return $this->findUniqueMatch('brebo_project', $text, [
      'title' => 50,
    ], 50);
  }

  private function findBestBuilding(string $text): ?NodeInterface {
    // A city or postal code alone is never enough to establish building truth.
    // They may strengthen a title/address match, but the minimum score requires
    // at least a building label or a full BREBO address to be present.
    return $this->findUniqueMatch('brebo_building', $text, [
      'title' => 50,
      'field_brebo_address' => 50,
      'field_brebo_postal_code' => 10,
      'field_brebo_city' => 10,
    ], 50);
  }

  /**
   * @param array<string, int> $weightedFields
   */
  private function findUniqueMatch(string $bundle, string $text, array $weightedFields, int $minimumScore): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }

    $scores = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $score = 0;
      foreach ($weightedFields as $field => $weight) {
        if ($field === 'title') {
          $value = $node->label();
        }
        elseif ($node->hasField($field) && !$node->get($field)->isEmpty()) {
          $value = (string) $node->get($field)->value;
        }
        else {
          continue;
        }

        $needle = $this->normalize($value);
        if (mb_strlen($needle) >= 4 && str_contains($text, $needle)) {
          $score += $weight;
        }
      }
      if ($score >= $minimumScore) {
        $scores[(int) $node->id()] = $score;
      }
    }

    if ($scores === []) {
      return NULL;
    }
    arsort($scores);
    $idsByScore = array_keys($scores);
    $bestId = (int) $idsByScore[0];
    $bestScore = $scores[$bestId];
    $secondScore = isset($idsByScore[1]) ? $scores[(int) $idsByScore[1]] : -1;

    // Do not guess when two existing records match equally well.
    if ($bestScore === $secondScore) {
      return NULL;
    }

    $match = $storage->load($bestId);
    return $match instanceof NodeInterface ? $match : NULL;
  }

  private function extractAddressQuery(string $subject, string $body): string {
    $combined = preg_replace('/\s+/u', ' ', trim($subject . ' ' . $body)) ?? trim($subject . ' ' . $body);

    // Prefer a Dutch postcode plus nearby text because it is highly selective.
    if (preg_match('/.{0,60}\b[1-9][0-9]{3}\s?[A-Z]{2}\b.{0,40}/iu', $combined, $match)) {
      return trim($match[0]);
    }

    // Otherwise use a bounded part of the subject/body. PDOK performs the
    // actual address interpretation; BREBO does not invent address truth.
    return mb_substr($combined, 0, 220);
  }

  private function normalize(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
  }

}
