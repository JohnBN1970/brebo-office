<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Stores and deduplicates cross-domain operational risk escalations. */
final class RiskEscalationManager {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $payload */
  public function escalate(string $domain,string $sourceReference,string $level,string $title,array $payload,array $audiences): int {
    if(!$this->database->schema()->tableExists('brebo_risk_escalation')) return 0;
    $fingerprint=hash('sha256',$domain.'|'.$sourceReference.'|'.$level.'|'.$title.'|'.json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $existing=$this->database->select('brebo_risk_escalation','e')->fields('e',['id'])->condition('fingerprint',$fingerprint)->condition('status','open')->execute()->fetchField();
    if($existing) return (int)$existing;
    return (int)$this->database->insert('brebo_risk_escalation')->fields([
      'domain'=>$domain,'source_reference'=>$sourceReference,'level'=>$level,'title'=>$title,
      'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'audiences_json'=>json_encode(array_values(array_unique($audiences)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'fingerprint'=>$fingerprint,'status'=>'open','created'=>time(),'changed'=>time(),
    ])->execute();
  }
}
