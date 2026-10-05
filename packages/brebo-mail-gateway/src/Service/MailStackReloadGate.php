<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Contract\MailStackCommandRunnerInterface;
use RuntimeException;

final class MailStackReloadGate {

  public function __construct(
    private readonly MailStackCommandRunnerInterface $runner,
    private readonly bool $reloadEnabled,
  ) {}

  /** @return array<string,array{exit_code:int,stdout:string,stderr:string}> */
  public function validate(): array {
    $checks = [
      'postfix' => $this->runner->run('postfix', ['check']),
      'dovecot' => $this->runner->run('doveconf', ['-n']),
      'rspamd' => $this->runner->run('rspamadm', ['configtest']),
    ];

    foreach ($checks as $name => $result) {
      if ($result['exit_code'] !== 0) {
        throw new RuntimeException('Mailstack validatie mislukt voor ' . $name . ': ' . trim($result['stderr']));
      }
    }

    return $checks;
  }

  public function reload(): void {
    $this->validate();
    if (!$this->reloadEnabled) {
      throw new RuntimeException('Mailstack reload is niet geactiveerd.');
    }

    foreach ([
      ['systemctl', ['reload', 'postfix']],
      ['systemctl', ['reload', 'dovecot']],
      ['systemctl', ['reload', 'rspamd']],
    ] as [$command, $arguments]) {
      $result = $this->runner->run($command, $arguments);
      if ($result['exit_code'] !== 0) {
        throw new RuntimeException('Mailstack reload mislukt: ' . $command . ' ' . implode(' ', $arguments));
      }
    }
  }
}
