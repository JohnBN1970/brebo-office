<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Read boundary for canonical active project/building context used by mail intake. */
interface MailContextReadRepositoryInterface {

  /**
   * @return list<array{id:int,label:string,building_ids:int[]}>
   */
  public function activeProjects(): array;

  /**
   * @return list<array{id:int,label:string,address:string,postal_code:string,city:string}>
   */
  public function activeBuildings(): array;

  /**
   * @return array{id:int,label:string,address:string,postal_code:string,city:string}|null
   */
  public function building(int $buildingId): ?array;

}
