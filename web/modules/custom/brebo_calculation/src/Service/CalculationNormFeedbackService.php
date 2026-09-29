<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationNormFeedbackRepositoryInterface;

/** Stores actual observations and evaluates norm deviations. */
final class CalculationNormFeedbackService {
  public function __construct(private readonly CalculationNormFeedbackRepositoryInterface $repository) {}

  /** @param array<string,mixed> $context */
  public function record(string $domain,string $normKey,float $plannedValue,float $actualValue,string $unit,string $sourceDomain,string $sourceReference,?int $projectId=NULL,array $context=[]): int {
    foreach([$domain,$normKey,$unit,$sourceDomain,$sourceReference]as$required)if(trim($required)==='')throw new \InvalidArgumentException('Normobservatie mist verplichte bron- of normgegevens.');
    if($plannedValue<0||$actualValue<0)throw new \InvalidArgumentException('Normwaarden mogen niet negatief zijn.');
    $delta=$actualValue-$plannedValue;$deltaPct=$plannedValue>0?($delta/$plannedValue)*100:NULL;
    return $this->repository->insertObservation(['domain'=>$domain,'norm_key'=>$normKey,'planned_value'=>$plannedValue,'actual_value'=>$actualValue,'unit'=>$unit,'delta_value'=>$delta,'delta_pct'=>$deltaPct,'source_domain'=>$sourceDomain,'source_reference'=>$sourceReference,'project_id'=>$projectId,'context_json'=>json_encode($context,JSON_THROW_ON_ERROR),'created'=>time()]);
  }

  /** @return array<string,float|int|null> */
  public function summary(string $domain,string $normKey,int $minimumSamples=3): array {
    $row = $this->repository->summary($domain, $normKey);$samples=(int)($row['samples']??0);$actual=isset($row['actual_avg'])?(float)$row['actual_avg']:NULL;
    return['samples'=>$samples,'planned_avg'=>isset($row['planned_avg'])?(float)$row['planned_avg']:NULL,'actual_avg'=>$actual,'delta_pct_avg'=>isset($row['delta_pct_avg'])?(float)$row['delta_pct_avg']:NULL,'proposed_value'=>$samples>=$minimumSamples?$actual:NULL];
  }
}
