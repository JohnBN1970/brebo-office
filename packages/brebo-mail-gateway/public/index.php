<?php

declare(strict_types=1);

use Brebo\MailGateway\Domain\GatewayRequest;
use Brebo\MailGateway\Infrastructure\FilesystemDkimKeyGenerator;
use Brebo\MailGateway\Infrastructure\FilesystemMailStackAdapter;
use Brebo\MailGateway\Infrastructure\SqliteGatewayProvisioningRepository;
use Brebo\MailGateway\Security\GatewayRequestVerifier;
use Brebo\MailGateway\Service\GatewayApiService;
use Brebo\MailGateway\Service\GatewayRequestRouter;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$keyId = trim((string) getenv('BREBO_MAIL_GATEWAY_SERVER_KEY_ID'));
$secret = (string) getenv('BREBO_MAIL_GATEWAY_SERVER_SECRET');
$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), DIRECTORY_SEPARATOR);

if ($keyId === '' || $secret === '' || $dataDir === '') {
  http_response_code(503);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'gateway_runtime_not_ready']);
  exit;
}

if (!is_dir($dataDir) && !mkdir($dataDir, 0700, TRUE) && !is_dir($dataDir)) {
  http_response_code(503);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'gateway_data_dir_unavailable']);
  exit;
}

$pdo = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'gateway.sqlite');
$repository = new SqliteGatewayProvisioningRepository($pdo);
$dkim = new FilesystemDkimKeyGenerator($dataDir . DIRECTORY_SEPARATOR . 'dkim');
$mailStack = new FilesystemMailStackAdapter($dataDir . DIRECTORY_SEPARATOR . 'mailstack');
$api = new GatewayApiService($repository, $dkim, $mailStack);
$verifier = new GatewayRequestVerifier($keyId, $secret);
$router = new GatewayRequestRouter($api, $verifier);

$headers = [];
foreach (getallheaders() as $name => $value) {
  $headers[(string) $name] = (string) $value;
}

$request = new GatewayRequest(
  strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
  parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
  file_get_contents('php://input') ?: '',
  $headers,
);
$response = $router->dispatch($request, time());

http_response_code($response['status']);
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode($response['body'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
