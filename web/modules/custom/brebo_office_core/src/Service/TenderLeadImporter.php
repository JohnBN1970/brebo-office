<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Imports normalized tender records into the canonical CRM funnel. */
final class TenderLeadImporter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TenderLeadScorer $scorer,
  ) {}

  /** @return array{created:int,updated:int,skipped:int} */
  public function import(iterable $records, int $minimumScore = 35): array {
    $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($records as $record) {
      $source = trim((string) ($record['source'] ?? 'TenderNed'));
      $externalId = trim((string) ($record['external_id'] ?? ''));
      if ($externalId === '') {
        $result['skipped']++;
        continue;
      }
      $match = $this->scorer->score($record);
      if ($match['score'] < $minimumScore) {
        $result['skipped']++;
        continue;
      }
      $fingerprint = hash('sha256', $source . '|' . $externalId);
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'brebo_opportunity')
        ->condition('field_brebo_opp_external_key', $fingerprint)
        ->range(0, 1)->execute();
      $node = $ids ? $storage->load(reset($ids)) : $storage->create(['type' => 'brebo_opportunity']);
      if (!$node instanceof NodeInterface) {
        $result['skipped']++;
        continue;
      }
      $isNew = $node->isNew();
      $node->setTitle((string) ($record['title'] ?? 'Aanbesteding'));
      $this->set($node, 'field_brebo_opp_stage', 'Marketing lead');
      $this->set($node, 'field_brebo_opp_source', 'Aanbesteding');
      $this->set($node, 'field_brebo_opp_channel', $source);
      $this->set($node, 'field_brebo_opp_external_key', $fingerprint);
      $this->set($node, 'field_brebo_opp_external_id', $externalId);
      $this->set($node, 'field_brebo_opp_source_url', (string) ($record['url'] ?? ''));
      $this->set($node, 'field_brebo_opp_deadline', (string) ($record['deadline'] ?? ''));
      $this->set($node, 'field_brebo_opp_fit_score', $match['score']);
      $this->set($node, 'field_brebo_opp_match_reasons', implode(', ', $match['reasons']));
      $this->set($node, 'field_brebo_opp_buyer', (string) ($record['buyer'] ?? ''));
      $this->set($node, 'field_brebo_opp_region', (string) ($record['region'] ?? ''));
      $this->set($node, 'field_brebo_opp_active', !in_array(($record['status'] ?? 'open'), ['withdrawn', 'expired'], TRUE) ? 1 : 0);
      $node->save();
      $result[$isNew ? 'created' : 'updated']++;
    }
    return $result;
  }

  private function set(NodeInterface $node, string $field, mixed $value): void {
    if ($node->hasField($field)) {
      $node->set($field, $value);
    }
  }

}
