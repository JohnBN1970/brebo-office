<?php
declare(strict_types=1);
namespace Drupal\brebo_finance\Infrastructure;
use Drupal\brebo_finance\Contract\ReceivablesDunningRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use RuntimeException;
final class DrupalReceivablesDunningRepository implements ReceivablesDunningRepositoryInterface {
  private const STORE='brebo_finance.receivables_dunning';
  public function __construct(private readonly Connection $database,private readonly KeyValueFactoryInterface $keyValueFactory){}
  public function invoice(int $id):?array{if(!$this->database->schema()->tableExists('brebo_finance_sales_invoice'))throw new RuntimeException('Verkoopfactuurspiegel ontbreekt. Voer database-updates uit.');$r=$this->database->select('brebo_finance_sales_invoice','i')->fields('i')->condition('id',$id)->execute()->fetchAssoc();return$r===FALSE?NULL:$r;}
  public function state(int $id):array{$v=$this->keyValueFactory->get(self::STORE)->get((string)$id,[]);return is_array($v)?$v:[];}
  public function save(int $id,array $state):void{$this->keyValueFactory->get(self::STORE)->set((string)$id,$state);}
}
