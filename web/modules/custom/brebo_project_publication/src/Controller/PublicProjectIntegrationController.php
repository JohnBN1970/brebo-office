<?php

declare(strict_types=1);

namespace Drupal\brebo_project_publication\Controller;

use Drupal\brebo_project_publication\Service\PublicProjectProjection;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Machine-only source endpoint for the BREBO Integration API.
 */
final class PublicProjectIntegrationController extends ControllerBase {

  public function __construct(private readonly PublicProjectProjection $projection) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_project_publication.public_projection'));
  }

  public function list(Request $request): JsonResponse {
    if (!$this->authenticated($request)) {
      return $this->error(401, 'invalid_signature');
    }

    return $this->response(['projects' => $this->projection->all()]);
  }

  public function detail(Request $request, string $public_id): JsonResponse {
    if (!$this->authenticated($request)) {
      return $this->error(401, 'invalid_signature');
    }

    $project = $this->projection->byPublicId($public_id);
    if ($project === NULL) {
      return $this->error(404, 'not_found');
    }

    return $this->response(['project' => $project]);
  }

  private function authenticated(Request $request): bool {
    $secret = trim((string) Settings::get('brebo_shared_secret', getenv('BREBO_SHARED_SECRET') ?: ''));
    $timestamp = $request->headers->get('X-BREBO-Timestamp', '');
    $requestId = $request->headers->get('X-BREBO-Request-Id', '');
    $signatureHeader = $request->headers->get('X-BREBO-Signature', '');

    if ($secret === '' || !preg_match('/^[0-9]+$/', $timestamp) || !preg_match('/^[0-9a-f-]{36}$/i', $requestId) || !preg_match('/^v1=([a-f0-9]{64})$/', $signatureHeader, $match)) {
      return FALSE;
    }

    if (abs(time() - (int) $timestamp) > 300) {
      return FALSE;
    }

    $bodyHash = hash('sha256', '');
    $canonical = implode("\n", ['GET', $request->getPathInfo(), $bodyHash, $timestamp, $requestId]);
    return hash_equals(hash_hmac('sha256', $canonical, $secret), $match[1]);
  }

  private function response(array $payload): JsonResponse {
    $response = new JsonResponse(['status' => 'ok'] + $payload);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function error(int $status, string $code): JsonResponse {
    $response = new JsonResponse(['status' => 'error', 'error' => ['code' => $code]], $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}
