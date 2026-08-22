<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

/**
 * Imports normalized NLBE-SfB terms through the BREBO-wide intake layer.
 */
final class NlbeSfbProvider {

  private const SOURCE_KEY = 'nlbe_sfb';
  private const SYSTEM_KEY = 'NLBE-SfB';

  public function __construct(
    private readonly DataIngestManager $ingestManager,
    private readonly ClassificationRepository $classificationRepository,
  ) {}

  /**
   * @param array<int, array{code:string,label:string,parent_code?:?string,level?:int}> $terms
   *   Already normalized classification terms. Parsing source documents belongs to
   *   a separate adapter, not to this provider.
   *
   * @return array{run_id:int,source_id:int,records:int,terms:int,source_version:string}
   */
  public function import(array $terms, string $sourceVersion, ?string $sourceReference = NULL, ?string $sourceHash = NULL, string $triggerType = 'manual'): array {
    $sourceVersion = trim($sourceVersion);
    if ($sourceVersion === '') {
      throw new \InvalidArgumentException('NLBE-SfB source version is required.');
    }
    if ($terms === []) {
      throw new \InvalidArgumentException('NLBE-SfB import requires at least one term.');
    }

    $sourceId = $this->ingestManager->registerSource(
      self::SOURCE_KEY,
      'NLBE-SfB',
      'classification',
      'ketenstandaard.nlbe_sfb',
    );
    $runId = $this->ingestManager->startRun(
      $sourceId,
      $triggerType,
      $sourceReference,
      $sourceHash,
      ['system_key' => self::SYSTEM_KEY, 'source_version' => $sourceVersion],
    );

    $records = 0;
    $accepted = 0;
    try {
      foreach ($terms as $index => $term) {
        $normalized = $this->normalizeTerm($term, $index);
        $recordId = $this->ingestManager->addRecord(
          $runId,
          'classification_term',
          ['system_key' => self::SYSTEM_KEY, 'source_version' => $sourceVersion] + $normalized,
          $normalized['code'],
          $sourceReference,
          1.0,
        );
        $records++;

        $this->classificationRepository->upsert(
          self::SYSTEM_KEY,
          $sourceVersion,
          $normalized['code'],
          $normalized['label'],
          $normalized['parent_code'],
          $normalized['level'],
          $recordId,
        );
        $accepted++;
      }

      $this->ingestManager->finishRun($runId, 'completed', [
        'record_count' => $records,
        'accepted_count' => $accepted,
      ]);
    }
    catch (\Throwable $exception) {
      $this->ingestManager->finishRun($runId, 'failed', [
        'record_count' => $records,
        'accepted_count' => $accepted,
        'error_count' => 1,
      ]);
      throw $exception;
    }

    return [
      'run_id' => $runId,
      'source_id' => $sourceId,
      'records' => $records,
      'terms' => $accepted,
      'source_version' => $sourceVersion,
    ];
  }

  /**
   * @param array{code?:mixed,label?:mixed,parent_code?:mixed,level?:mixed} $term
   * @return array{code:string,label:string,parent_code:?string,level:int}
   */
  private function normalizeTerm(array $term, int $index): array {
    $code = trim((string) ($term['code'] ?? ''));
    $label = trim((string) ($term['label'] ?? ''));
    if ($code === '' || $label === '') {
      throw new \InvalidArgumentException(sprintf('NLBE-SfB term %d requires code and label.', $index + 1));
    }

    $parentCode = isset($term['parent_code']) ? trim((string) $term['parent_code']) : NULL;
    if ($parentCode === '') {
      $parentCode = NULL;
    }
    $level = isset($term['level']) ? (int) $term['level'] : $this->inferLevel($code);

    return [
      'code' => mb_substr($code, 0, 64),
      'label' => mb_substr($label, 0, 512),
      'parent_code' => $parentCode !== NULL ? mb_substr($parentCode, 0, 64) : NULL,
      'level' => max(0, min(255, $level)),
    ];
  }

  private function inferLevel(string $code): int {
    $compact = preg_replace('/[^0-9A-Za-z]/u', '', $code) ?? $code;
    return max(1, (int) ceil(mb_strlen($compact) / 2));
  }

}
