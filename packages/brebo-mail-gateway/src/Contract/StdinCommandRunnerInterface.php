<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface StdinCommandRunnerInterface {
  /** @param string[] $arguments
   *  @return array{exit_code:int,stdout:string,stderr:string}
   */
  public function runWithInput(string $command, array $arguments, string $input): array;
}
