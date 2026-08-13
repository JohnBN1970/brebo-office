<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_building_data\Service\BuildingRelationRepository;
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
    private readonly ?BuildingRelationRepository $buildingRelations = NULL,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function resolve(string $subject, string $body): array {
    $text = $this->normalize($subject . "\n" . $body);
    $project = $this->findBestProject($text);
    $building = $this->findBestBuilding($text);
    $basis = [];
    $buildingCandidates = [];

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
            $relationResolution = $this->resolveFromBuildingRelations($pdokCandidates);
            if (($relationResolution['state'] ?? '') === 'matched') {
              $candidateId = (int) ($relationResolution['building_id'] ?? 0);
              $candidate = $candidateId > 0 ? $this->entityTypeManager->getStorage('node')->load($candidateId) : NULL;
              if ($candidate instanceof NodeInterface && $candidate->bundle() === 'brebo_building') {
                $building = $candidate;
                $basis[] = sprintf('Bestaand gebouw herkend via %s', (string) ($relationResolution['basis'] ?? 'BAG-/adresidentiteit.'));
              }
            }
            elseif (($relationResolution['state'] ?? '') === 'ambiguous') {
              $buildingCandidates = array_values(array_unique(array_map('intval', $relationResolution['candidate_building_ids'] ?? [])));
              $basis[] = 'Meerdere bestaande gebouwen passen bij de gevonden BAG-/adresidentiteit; geen automatische keuze gemaakt.';
            }
            else {
              $basis[] = 'Geen bestaand gebouw gevonden; officiële PDOK-adreskandidaten beschikbaar voor menselijke beoordeling.';
            }
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
      'building_state' => $building instanceof NodeInterface ? 'existing' : ($buildingCandidates !== [] ? 'ambiguous' : 'provisional_required'),
      'building_candidate_ids' => $buildingCandidates,
      'pdok_address_candidates' => $pdokCandidates,
      'requires_human_review' => !($project instanceof NodeInterface) || !($building instanceof NodeInterface),
      'basis' => implode(' ', $basis) ?: 'Geen bestaande canonieke project- of gebouwcontext herkend.',
    ];
  }

  /**
   * @param array<int, array<string, mixed>> $pdokCandidates
   *
   * @return array{state:string,building_id:?int,candidate_building_ids:int[],basis:string}
   */
  private function resolveFromBuildingRelations(array $pdokCandidates): array {
    if (!$this->buildingRelations instanceof BuildingRelationRepository) {
      return ['state' => 'unavailable', 'building_id' => NULL, 'candidate_building_ids' => [], 'basis' => 'Gebouwrelatie-opslag niet beschikbaar.'];
    }

    $matched = [];
    $ambiguous = [];
    $basis = [];
    foreach ($pdokCandidates as $candidate) {
      if (!is_array($candidate)) {
        continue;
      }
      $result = $this->buildingRelations->resolveBuildingCandidate($candidate);
      $candidateIds = array_values(array_unique(array_map('intval', $result['candidate_building_ids'] ?? [])));
      if (($result['state'] ?? '') === 'matched' && isset($result['building_id'])) {
        $matched[(int) $result['building_id']] = TRUE;
        $basis[] = (string) ($result['basis'] ?? '');
      }
      elseif (($result['state'] ?? '') === 'ambiguous') {
        foreach ($candidateIds as $candidateId) {
          $ambiguous[$candidateId] = TRUE;
        }
      }
    }

    $matchedIds = array_keys($matched);
    if (count($matchedIds) === 1 && $ambiguous === []) {
      return ['state' => 'matched', 'building_id' => (int) $matchedIds[0], 'candidate_building_ids' => array_map('intval', $matchedIds), 'basis' => trim(implode(' ', array_filter($basis))) ?: 'Exacte BAG-/adresidentiteit.'];
    }

    $candidateIds = array_values(array_unique(array_merge(array_map('intval', $matchedIds), array_map('intval', array_keys($ambiguous)))));
    if (count($candidateIds) > 1 || $ambiguous !== []) {
      return ['state' => 'ambiguous', 'building_id' => NULL, 'candidate_building_ids' => $candidateIds, 'basis' => 'Meerdere BREBO-gebouwen passen bij de PDOK-kandidaten.'];
    }

    return ['state' => 'unmatched', 'building_id' => NULL, 'candidate_building_ids' => [], 'basis' => 'Geen bekende BAG-/adresidentiteit.'];
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

    if (preg_match('/\b(?:werkadres|adres|locatie)\s*[:\-]?\s*([\p{L}][\p{L}\s\.\'’\-]{1,70}?)\s+(\d+[A-Za-z0-9\-]*)\s*,?\s*([1-9][0-9]{3}\s?[A-Z]{2})\s+([\p{L}][\p{L}\s\.\'’\-]{1,40}?)(?=[\.,;]|$)/iu', $combined, $match)) {
      $postcode = strtoupper(preg_replace('/\s+/u', ' ', trim($match[3])) ?? trim($match[3]));
      return trim(sprintf('%s %s %s %s', trim($match[1]), trim($match[2]), $postcode, trim($match[4])));
    }

    if (preg_match('/(?:^|[\.,;:]\s)([\p{L}][\p{L}\s\.\'’\-]{1,70}?)\s+(\d+[A-Za-z0-9\-]*)\s*,?\s*([1-9][0-9]{3}\s?[A-Z]{2})\s+([\p{L}][\p{L}\s\.\'’\-]{1,40}?)(?=[\.,;]|$)/iu', $combined, $match)) {
      $postcode = strtoupper(preg_replace('/\s+/u', ' ', trim($match[3])) ?? trim($match[3]));
      return trim(sprintf('%s %s %s %s', trim($match[1]), trim($match[2]), $postcode, trim($match[4])));
    }

    if (preg_match('/\b[1-9][0-9]{3}\s?[A-Z]{2}\b/iu', $combined, $postcodeMatch, PREG_OFFSET_CAPTURE)) {
      $offset = (int) $postcodeMatch[0][1];
      $start = max(0, $offset - 45);
      return trim(mb_substr($combined, $start, 100));
    }

    return mb_substr($combined, 0, 160);
  }

  private function normalize(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
  }

}
