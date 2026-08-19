<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Creates traceable replacement versions of active BREBO norms. */
final class CalculationNormVersionManager {
  public function __construct(private readonly Connection $database) {}

  public function createReplacement(int $normId,float $value,string $reason,AccountInterface $account,?int $embeddingId=NULL): int {
    if(!$this->database->schema()->tableExists('brebo_calculation_norm'))throw new \RuntimeException('Calculatienormenbibliotheek is nog niet geinstalleerd.');
    $current=$this->database->select('brebo_calculation_norm','n')->fields('n')->condition('id',$normId)->execute()->fetchAssoc();
    if(!$current)throw new \RuntimeException('Bronnorm niet gevonden.');
    if(trim($reason)==='')throw new \RuntimeException('Motivering voor de normwijziging is verplicht.');
    if($value<0)throw new \InvalidArgumentException('Normwaarde mag niet negatief zijn.');
    $transaction=$this->database->startTransaction();
    try{
      $this->database->update('brebo_calculation_norm')->fields(['active'=>0,'changed'=>time()])->condition('id',$normId)->execute();
      $source='BREBO verbetering: '.trim($reason);if($embeddingId!==NULL)$source.=' [borging #'.$embeddingId.']';
      return(int)$this->database->insert('brebo_calculation_norm')->fields(['domain'=>$current['domain'],'norm_key'=>$current['norm_key'],'label'=>$current['label'],'value'=>$value,'unit'=>$current['unit'],'conditions_json'=>$current['conditions_json'],'priority'=>$current['priority'],'active'=>1,'source'=>$source,'changed'=>time()])->execute();
    }catch(\Throwable $e){$transaction->rollBack();throw $e;}
  }
}
