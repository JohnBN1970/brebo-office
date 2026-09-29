<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationNormRepositoryInterface;

/** Resolves active BREBO productivity and material norms. */
final class CalculationNormLibrary {
  public function __construct(private readonly CalculationNormRepositoryInterface $repository) {}

  /** @param array<string,mixed> $context */
  public function value(string $domain,string $normKey,array $context,float $fallback): float {
    $rows = $this->repository->activeNorms($domain, $normKey);
    foreach($rows as$row){$conditions=json_decode((string)($row['conditions_json']??''),TRUE);if(!is_array($conditions))$conditions=[];if($this->matches($conditions,$context))return(float)$row['value'];}
    return $fallback;
  }

  /** @param array<string,mixed> $conditions @param array<string,mixed> $context */
  private function matches(array $conditions,array $context): bool {
    foreach($conditions as$key=>$expected){
      if(str_ends_with((string)$key,'_min')){$field=substr((string)$key,0,-4);if(!isset($context[$field])||(float)$context[$field]<(float)$expected)return FALSE;continue;}
      if(str_ends_with((string)$key,'_max')){$field=substr((string)$key,0,-4);if(!isset($context[$field])||(float)$context[$field]>(float)$expected)return FALSE;continue;}
      if(!array_key_exists($key,$context))return FALSE;
      if(is_array($expected)){if(!in_array($context[$key],$expected,TRUE))return FALSE;}elseif((string)$context[$key] !== (string)$expected)return FALSE;
    }
    return TRUE;
  }
}
