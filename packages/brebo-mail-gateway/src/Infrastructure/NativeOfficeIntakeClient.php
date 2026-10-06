<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\Mail\Domain\NormalizedMailMessage;
use Brebo\MailGateway\Contract\OfficeIntakeClientInterface;
use RuntimeException;

final class NativeOfficeIntakeClient implements OfficeIntakeClientInterface {

  public function __construct(
    private readonly string $baseUrl,
    private readonly string $keyId,
    private readonly string $secret,
  ) {}

  public function deliver(NormalizedMailMessage $message): array {
    $baseUrl = rtrim(trim($this->baseUrl), '/');
    if (!str_starts_with($baseUrl, 'https://')) {
      throw new RuntimeException('Office intake callback vereist HTTPS.');
    }
    if (trim($this->keyId) === '' || $this->secret === '') {
      throw new RuntimeException('Office intake callback credentials ontbreken.');
    }

    $path = '/mail/api/v1/intake';
    $body = json_encode($message->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = GatewayRequestSignature::sign(
      $this->keyId,
      $this->secret,
      $timestamp,
      'POST',
      $path,
      $body,
    );

    $headers = [
      'Content-Type: application/json',
      'Accept: application/json',
    ];
    foreach ($signature->headers() as $name => $value) {
      $headers[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'timeout' => 10,
        'ignore_errors' => TRUE,
      ],
    ]);

    $response = @file_get_contents($baseUrl . $path, FALSE, $context);
    if ($response === FALSE) {
      throw new RuntimeException('Office intake callback kon niet worden bereikt.');
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
      if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
        $status = (int) $matches[1];
        break;
      }
    }

    $decoded = json_decode($response, TRUE);
    if (!is_array($decoded)) {
      $decoded = [];
    }
    if ($status < 200 || $status >= 300) {
      throw new RuntimeException('Office intake callback antwoordde met HTTP ' . $status . '.');
    }

    return $decoded;
  }
}
