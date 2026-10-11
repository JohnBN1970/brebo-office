<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\MailStackCommandRunnerInterface;
use RuntimeException;

final class PostfixTransportMapCompiler {

  public function __construct(private readonly MailStackCommandRunnerInterface $runner) {}

  /** @param string[] $domains */
  public function compileAndVerify(string $mapPath, array $domains): void {
    $mapPath = trim($mapPath);
    if ($mapPath === '') {
      throw new RuntimeException('Postfix transport map path ontbreekt.');
    }

    $compile = $this->runner->run('postmap', ['hash:' . $mapPath]);
    if ($compile['exit_code'] !== 0) {
      throw new RuntimeException('postmap compilatie mislukt: ' . trim($compile['stderr']));
    }

    foreach ($domains as $domain) {
      $domain = mb_strtolower(trim($domain));
      if ($domain === '') {
        continue;
      }
      $query = $this->runner->run('postmap', ['-q', $domain, 'hash:' . $mapPath]);
      if ($query['exit_code'] !== 0 || trim($query['stdout']) !== 'brebo_inbound:') {
        throw new RuntimeException('Postfix transport map verificatie mislukt voor ' . $domain . '.');
      }
    }
  }
}
