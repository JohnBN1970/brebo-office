<?php

declare(strict_types=1);

use Drupal\brebo_office_core\Service\IntegrationApiClientInterface;
use Drupal\Core\Site\Settings;

/**
 * vendor/bin/drush php:script scripts/test-worker-healthcheck.php
 */

const TEST_WORKER_ENDPOINT = 'https://brebo-integration-api.john-boon.workers.dev';

$fail = static function (string $message): never {
  fwrite(STDERR, $message . PHP_EOL);
  exit(1);
};

$originalSharedSecret = getenv('BREBO_SHARED_SECRET');
if (!is_string($originalSharedSecret) || $originalSharedSecret === '' || trim($originalSharedSecret) === '') {
  $fail('BREBO_SHARED_SECRET is niet geldig ingesteld voor dit proces.');
}

$effectiveSharedSecret = Settings::get('brebo_shared_secret', $originalSharedSecret);
if (!is_string($effectiveSharedSecret) || !hash_equals($originalSharedSecret, $effectiveSharedSecret)) {
  $fail('De effectieve Drupal-secret komt niet overeen met de procesconfiguratie.');
}

if (!putenv('BREBO_INTEGRATION_API_URL=' . TEST_WORKER_ENDPOINT)) {
  $fail('De test-URL kon niet voor dit proces worden ingesteld.');
}

$effectiveUrl = Settings::get('brebo_integration_api_url', getenv('BREBO_INTEGRATION_API_URL'));
if (!is_string($effectiveUrl)) {
  $fail('De effectieve Drupal-URL is ongeldig.');
}

$normalizedUrl = rtrim(trim($effectiveUrl), '/');
if ($normalizedUrl !== TEST_WORKER_ENDPOINT) {
  $fail('De effectieve Drupal-URL wijst niet naar het vaste testendpoint.');
}

$container = \Drupal::getContainer();
if (!$container->has('brebo_office_core.integration_api_client')) {
  $fail('De Integration API-clientservice is niet beschikbaar.');
}

$client = $container->get('brebo_office_core.integration_api_client');
if (!$client instanceof IntegrationApiClientInterface) {
  $fail('De Integration API-clientservice heeft een ongeldig type.');
}

$result = $client->status();
$requiredKeys = ['state', 'http_status', 'response_time_ms', 'checked_at'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $result)) {
    $fail('De healthcheck gaf een ongeldig resultaat terug.');
  }
}

$output = array_intersect_key($result, array_flip($requiredKeys));
$json = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
fwrite(STDOUT, $json . PHP_EOL);

if ($result['state'] !== 'healthy' || $result['http_status'] !== 200) {
  $fail('De Worker-healthcheck is niet gezond.');
}

exit(0);
