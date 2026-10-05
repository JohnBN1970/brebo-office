<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Service;

use Drupal\brebo_document_data\Contract\DocumentRevisionReadRepositoryInterface;

/**
 * Context-scoped revision grouping and current-revision selection.
 */
final class DocumentRevisionSeries {

  private const CONTEXT_TYPES = ['building', 'project', 'organization', 'contact', 'brebo'];

  public function __construct(
    private readonly DocumentRevisionReadRepositoryInterface $revisionRepository,
  ) {}

  /**
   * Proposes a conservative family key from a filename.
   *
   * This is only a proposal. The caller decides whether to persist it.
   *
   * @return array{family:string,revision_code:?string,confidence:float,reason:string}
   */
  public function proposeIdentity(string $filename): array {
    $name = trim(pathinfo($filename, PATHINFO_FILENAME));
    if ($name === '') {
      return ['family' => '', 'revision_code' => NULL, 'confidence' => 0.0, 'reason' => 'Geen bruikbare bestandsnaam.'];
    }

    $revision = NULL;
    $family = $name;
    $patterns = [
      '/(?:^|[\s_\-])(?:rev(?:isie)?)[\s_\-]*([a-z0-9.]+)$/iu',
      '/(?:^|[\s_\-])(?:versie|version|v)[\s_\-]*([0-9]+(?:\.[0-9]+)*)$/iu',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $family, $match, PREG_OFFSET_CAPTURE) === 1) {
        $revision = trim((string) $match[1][0]);
        $family = trim(substr($family, 0, (int) $match[0][1]), " \t\n\r\0\x0B_-.");
        break;
      }
    }

    $family = preg_replace('/[\s_\-]+/u', ' ', mb_strtolower($family)) ?? '';
    $family = trim($family);
    if ($family === '') {
      return ['family' => '', 'revision_code' => $revision, 'confidence' => 0.0, 'reason' => 'Na normalisatie resteert geen documentfamilie.'];
    }

    return [
      'family' => $family,
      'revision_code' => $revision,
      'confidence' => $revision !== NULL ? 0.85 : 0.55,
      'reason' => $revision !== NULL
        ? 'Expliciete revisie-/versiemarkering in bestandsnaam gevonden.'
        : 'Familie alleen uit genormaliseerde bestandsnaam afgeleid; beoordeling gewenst.',
    ];
  }

  /** @return array<int, array<string, mixed>> */
  public function revisions(string $contextType, int $contextId, string $documentFamily): array {
    $type = strtolower(trim($contextType));
    $family = trim($documentFamily);
    if (!in_array($type, self::CONTEXT_TYPES, TRUE) || $family === '' || ($type !== 'brebo' && $contextId <= 0)) {
      return [];
    }
    if ($type === 'brebo') {
      $contextId = max(0, $contextId);
    }

    $rows = $this->revisionRepository->revisions($type, $contextId, $family);
    foreach ($rows as $index => &$row) {
      $row['is_current_revision'] = $index === 0;
    }
    unset($row);
    return $rows;
  }

  /** @return array<string, mixed>|null */
  public function current(string $contextType, int $contextId, string $documentFamily): ?array {
    $rows = $this->revisions($contextType, $contextId, $documentFamily);
    return $rows[0] ?? NULL;
  }

}
