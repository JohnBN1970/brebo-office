<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface MailStackCommandRunnerInterface {
  /** @param string[] $arguments
   *  @return array{exit_code:int,stdout:string,stderr:string}
   */
  public function run(string $command, array $arguments = []): array;
}
