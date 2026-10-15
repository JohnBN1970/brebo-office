<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContext.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContextClient.php';

use Brebo\Office\Project\OfficeCanonicalProjectContextClient;

foreach (['http://example.com', 'https://example.com/path', 'https://user@example.com'] as $url) {
  try {
    new OfficeCanonicalProjectContextClient($url, str_repeat('s', 32));
    throw new RuntimeException('Insecure endpoint accepted.');
  } catch (RuntimeException $expected) {
    if ($expected->getMessage() === 'Insecure endpoint accepted.') throw $expected;
  }
}
$client = new OfficeCanonicalProjectContextClient('https://example.com', str_repeat('s', 32));
try {
  $client->get(0);
  throw new RuntimeException('Invalid project ID accepted.');
} catch (RuntimeException $expected) {
  if ($expected->getMessage() === 'Invalid project ID accepted.') throw $expected;
}
echo "Canonical project context client guards passed.\n";
