<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\Mail\Domain\NormalizedMailMessage;

final class OfficeIntakeRequestFactory {

  public function __construct(
    private readonly string $keyId,
    private readonly string $secret,
  ) {}

  /** @return array{path:string,body:string,headers:array<string,string>} */
  public function create(NormalizedMailMessage $message, int $timestamp): array {
    $path = '/mail/api/v1/intake';
    $body = json_encode($message->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $signature = GatewayRequestSignature::sign(
      $this->keyId,
      $this->secret,
      $timestamp,
      'POST',
      $path,
      $body,
    );

    return [
      'path' => $path,
      'body' => $body,
      'headers' => $signature->headers(),
    ];
  }
}
