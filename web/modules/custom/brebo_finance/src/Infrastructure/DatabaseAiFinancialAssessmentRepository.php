<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\AiFinancialAssessmentRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseAiFinancialAssessmentRepository implements AiFinancialAssessmentRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function createAssessment(array $fields):int{return (int)$this->database->insert('brebo_finance_ai_assessment')->fields($fields)->execute();}
  public function assessment(int $id):?array{$r=$this->database->select('brebo_finance_ai_assessment','a')->fields('a')->condition('id',$id)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
  public function review(int $assessmentId,array $findingFields,array $assessmentFields,bool $createFinding):?int{$tx=$this->database->startTransaction();try{$findingId=$createFinding?(int)$this->database->insert('brebo_finance_control_finding')->fields($findingFields)->execute():NULL;$assessmentFields['control_finding_id']=$findingId;$this->database->update('brebo_finance_ai_assessment')->fields($assessmentFields)->condition('id',$assessmentId)->execute();return $findingId;}catch(\Throwable $e){$tx->rollBack();throw $e;}}
}
