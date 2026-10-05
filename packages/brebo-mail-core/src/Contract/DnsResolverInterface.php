<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface DnsResolverInterface {
  /** @return string[] */
  public function txt(string $name): array;

  /** @return array<int,array{host:string,priority:int}> */
  public function mx(string $name): array;
}
