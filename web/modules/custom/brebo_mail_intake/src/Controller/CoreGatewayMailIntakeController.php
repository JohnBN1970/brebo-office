<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\Mail\Domain\NormalizedMailMessage;
use Brebo\Mail\Service\MailIntakeAdmissionService;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CoreGatewayMailIntakeController implements ContainerInjectionInterface {

  public function __construct(private readonly MailIntakeAdmissionService $admission) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_mail_intake.core_intake_admission'));
  }

  public function ingest(Request $request): JsonResponse {
    $body = (string) $request->getContent();
    $keyId = trim((string) getenv('BREBO_MAIL_INTAKE_CALLBACK_KEY_ID'));
    $secret = (string) getenv('BREBO_MAIL_INTAKE_CALLBACK_SECRET');
    $receivedKeyId = trim((string) $request->headers->get('X-Brebo-Key-Id', ''));
    $timestampRaw = trim((string) $request->headers->get('X-Brebo-Timestamp', ''));
    $receivedSignature = trim((string) $request->headers->get('X-Brebo-Signature', ''));

    if ($keyId === '' || $secret === '' || $receivedKeyId === '' || $timestampRaw === '' || $receivedSignature === '') {
      return new JsonResponse(['error' => 'unauthorized'], 401);
    }
    if (!hash_equals($keyId, $receivedKeyId) || !ctype_digit($timestampRaw)) {
      return new JsonResponse(['error' => 'unauthorized'], 401);
    }

    $timestamp = (int) $timestampRaw;
    if (abs(time() - $timestamp) > 300) {
      return new JsonResponse(['error' => 'expired_signature'], 401);
    }

    $expected = GatewayRequestSignature::sign(
      $keyId,
      $secret,
      $timestamp,
      'POST',
      '/mail/api/v1/intake',
      $body,
    );
    if (!hash_equals($expected->signature, $receivedSignature)) {
      return new JsonResponse(['error' => 'unauthorized'], 401);
    }

    try {
      $payload = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($payload)) {
        throw new \InvalidArgumentException('Ongeldige mailpayload.');
      }

      $message = new NormalizedMailMessage(
        (string) ($payload['source_id'] ?? ''),
        (string) ($payload['from'] ?? ''),
        (string) ($payload['to'] ?? ''),
        (string) ($payload['subject'] ?? ''),
        (string) ($payload['body'] ?? ''),
        (string) ($payload['body_html'] ?? ''),
        (string) ($payload['received_at'] ?? ''),
        (string) ($payload['thread_id'] ?? ''),
      );
      $result = $this->admission->ingest($message);
      return new JsonResponse(['status' => 'ok', 'result' => $result], 200);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['error' => 'invalid_mail', 'message' => $e->getMessage()], 422);
    }
  }
}
