<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface DkimKeyGeneratorInterface {

  /** @return array{selector:string,public_key:string,private_key_reference:string} */
  public function generate(string $domain): array;
}
