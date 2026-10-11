<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Contract\MailStackCommandRunnerInterface;
use Brebo\MailGateway\Service\PostfixTransportMapCompiler;

final class CompileRunner implements MailStackCommandRunnerInterface {
  public array $calls = [];

  public function run(string $command, array $arguments = []): array {
    $this->calls[] = [$command, $arguments];
    if (($arguments[0] ?? '') === '-q') {
      return ['exit_code' => 0, 'stdout' => "brebo_inbound:\n", 'stderr' => ''];
    }
    return ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
  }
}

$runner = new CompileRunner();
(new PostfixTransportMapCompiler($runner))->compileAndVerify(
  '/etc/brebo-mail-stack/generated/transport_map',
  ['mail-test.example.nl'],
);

if (($runner->calls[0][1][0] ?? '') !== 'hash:/etc/brebo-mail-stack/generated/transport_map') {
  throw new RuntimeException('Compile must use the same explicit hash map backend as queries.');
}
if (count($runner->calls) !== 2) {
  throw new RuntimeException('Expected postmap compile plus one verification query.');
}
if (($runner->calls[1][1][1] ?? '') !== 'mail-test.example.nl') {
  throw new RuntimeException('Expected test-domain query was not performed.');
}

echo "BREBO_POSTFIX_TRANSPORT_COMPILE=PASS\n";
