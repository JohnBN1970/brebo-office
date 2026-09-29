<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationNormVersionRepositoryInterface;

/** Creates traceable replacement versions of active BREBO norms. */
final class CalculationNormVersionManager {
  public function __construct(private readonly CalculationNormVersionRepositoryInterface $repository) {}

  public function createReplacement(int $normId,float $value,string $reason,int $actorId,?int $embeddingId=NULL): int {
    $current = $this->repository->norm($normId);
    if(!$current)throw new \RuntimeException('Bronnorm niet gevonden.');
    if(trim($reason)==='')throw new \RuntimeException('Motivering voor de normwijziging is verplicht.');
    if($value<0)throw new \InvalidArgumentException('Normwaarde mag niet negatief zijn.');
    $source='BREBO verbetering: '.trim($reason);if($embeddingId!==NULL)$source.=' [borging #'.$embeddingId.']';
    return $this->repository->replace($normId, [
      'domain'=>$current['domain'],
      'norm_key'=>$current['norm_key'],
      'label'=>$current['label'],
      'value'=>$value,
      'unit'=>$current['unit'],
      'conditions_json'=>$current['conditions_json'],
      'priority'=>$current['priority'],
      'active'=>1,
      'source'=>$source,
      'changed'=>time(),
    ]);
  }
}
