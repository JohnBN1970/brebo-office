<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Stores actual observations and evaluates norm deviations. */
final class CalculationNormFeedbackService {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $context */
  public function record(string $domain,string $normKey,float $plannedValue,float $actualValue,string $unit,string $sourceDomain,string $sourceReference,?int $projectId=NULL,array $context=[]): int {
    foreach([$domain,$normKey,$unit,$sourceDomain,$sourceReference]as$required)if(trim($required)==='')throw new \InvalidArgumentException('Normobservatie mist verplichte bron- of normgegevens.');
    if($plannedValue<0||$actualValue<0)throw new \InvalidArgumentException('Normwaarden mogen niet negatief zijn.');
    $delta=$actualValue-$plannedValue;$deltaPct=$plannedValue>0?($delta/$plannedValue)*100:NULL;
    return(int)$this->database->insert('brebo_calculation_norm_observation')->fields(['domain'=>$domain,'norm_key'=>$normKey,'planned_value'=>$plannedValue,'actual_value'=>$actualValue,'unit'=>$unit,'delta_value'=>$delta,'delta_pct'=>$deltaPct,'source_domain'=>$sourceDomain,'source_reference'=>$sourceReference,'project_id'=>$projectId,'context_json'=>json_encode($context,JSON_THROW_ON_ERROR),'created'=>time()])->execute();
  }

  /** @return array<string,float|int|null> */
  public function summary(string $domain,string $normKey,int $minimumSamples=3): array {
    if(!$this->database->schema()->tableExists('brebo_calculation_norm_observation'))return['samples'=>0,'planned_avg'=>NULL,'actual_avg'=>NULL,'delta_pct_avg'=>NULL,'proposed_value'=>NULL];
    $query=$this->database->select('brebo_calculation_norm_observation','o');$query->addExpression('COUNT(*)','samples');$query->addExpression('AVG(planned_value)','planned_avg');$query->addExpression('AVG(actual_value)','actual_avg');$query->addExpression('AVG(delta_pct)','delta_pct_avg');$query->condition('domain',$domain)->condition('norm_key',$normKey);$row=$query->execute()->fetchAssoc()?:[];$samples=(int)($row['samples']??0);$actual=isset($row['actual_avg'])?(float)$row['actual_avg']:NULL;
    return['samples'=>$samples,'planned_avg'=>isset($row['planned_avg'])?(float)$row['planned_avg']:NULL,'actual_avg'=>$actual,'delta_pct_avg'=>isset($row['delta_pct_avg'])?(float)$row['delta_pct_avg']:NULL,'proposed_value'=>$samples>=$minimumSamples?$actual:NULL];
  }
}
