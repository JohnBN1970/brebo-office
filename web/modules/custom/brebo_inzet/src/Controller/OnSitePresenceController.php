<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\OnSiteDeviceRegistry;
use Drupal\brebo_inzet\Service\OnSitePresenceEvidenceWriter;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OnSitePresenceController extends ControllerBase {

  public function __construct(
    private readonly OnSiteDeviceRegistry $deviceRegistry,
    private readonly OnSitePresenceEvidenceWriter $writer,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_inzet.onsite_device_registry'),
      $container->get('brebo_inzet.onsite_presence_evidence_writer'),
    );
  }

  public function record(Request $request): JsonResponse {
    $token = $this->bearerToken($request);
    $uid = $token === NULL ? NULL : $this->deviceRegistry->resolve($token);
    if ($uid === NULL) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'device_not_linked'], 401);
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_payload'], 400);
    }

    try {
      $event = $this->writer->record(
        $uid,
        (string) ($payload['project_id'] ?? ''),
        (string) ($payload['zone_id'] ?? ''),
        (string) ($payload['kind'] ?? ''),
        (string) ($payload['occurred_at'] ?? ''),
        (string) ($payload['building_id'] ?? ''),
      );
      return new JsonResponse(['ok' => TRUE, 'event' => $event], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_event'], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'presence_context_mismatch'], 403);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'presence_write_failed'], 500);
    }
  }

  private function bearerToken(Request $request): ?string {
    $authorization = trim((string) $request->headers->get('Authorization', ''));
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
      return NULL;
    }
    return trim($matches[1]);
  }

}
