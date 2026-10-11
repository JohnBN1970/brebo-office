<?php

declare(strict_types=1);

use Brebo\MailGateway\Infrastructure\NativeCommandRunner;
use Brebo\MailGateway\Service\PostfixTransportMapCompiler;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$configDir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($configDir === '') {
  fwrite(STDERR, "BREBO_MAILSTACK_CONFIG_DIR is verplicht.\n");
  exit(2);
}

$mapPath = $configDir . DIRECTORY_SEPARATOR . 'transport_map';
if (!is_file($mapPath)) {
  fwrite(STDERR, "Postfix transport map ontbreekt: {$mapPath}\n");
  exit(2);
}

$domains = [];
foreach (file($mapPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
  [$domain] = preg_split('/\s+/', trim($line), 2) ?: [];
  if ($domain !== '') {
    $domains[] = $domain;
  }
}

$compiler = new PostfixTransportMapCompiler(
  new NativeCommandRunner(['postmap']),
);
$compiler->compileAndVerify($mapPath, $domains);

echo "BREBO_POSTFIX_TEST_TRANSPORT_COMPILED=PASS\n";
