<?php
declare(strict_types=1);
namespace Drupal\brebo_finance\Contract;
interface ReceivablesDunningRepositoryInterface {
  /** @return array<string,mixed>|null */ public function invoice(int $id):?array;
  /** @return array<string,mixed> */ public function state(int $id):array;
  /** @param array<string,mixed> $state */ public function save(int $id,array $state):void;
}
