<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only, HMAC protected context endpoint for BREBO Calc.
 */
final class CalculationWorkbenchContextController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheBackendInterface $cache,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('cache.default'),
    );
  }

  public function project(Request $request, NodeInterface $node): JsonResponse {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $this->assertSignedRequest($request);

    $buildings = [];
    if ($node->hasField('field_brebo_building_refs')) {
      foreach ($node->get('field_brebo_building_refs')->referencedEntities() as $building) {
        if ($building instanceof NodeInterface) {
          $buildings[] = [
            'id' => (int) $building->id(),
            'title' => (string) $building->label(),
          ];
        }
      }
    }

    $clientOrganization = NULL;
    if ($node->hasField('field_brebo_client_org_ref')) {
      $entity = $node->get('field_brebo_client_org_ref')->entity;
      if ($entity instanceof NodeInterface) {
        $clientOrganization = [
          'id' => (int) $entity->id(),
          'title' => (string) $entity->label(),
        ];
      }
    }

    return new JsonResponse([
      'contract' => 'brebo-office-calc-context-v1',
      'project' => [
        'id' => (int) $node->id(),
        'code' => $this->fieldValue($node, 'field_brebo_project_code'),
        'title' => (string) $node->label(),
        'status' => $this->fieldValue($node, 'field_brebo_status'),
        'client_name' => $this->fieldValue($node, 'field_brebo_client'),
        'client_organization' => $clientOrganization,
        'project_kind' => $this->fieldValue($node, 'field_brebo_project_kind'),
        'disciplines' => $this->fieldValues($node, 'field_brebo_disciplines'),
        'description' => $this->fieldValue($node, 'field_brebo_description'),
        'buildings' => $buildings,
      ],
    ], 200, [
      'Cache-Control' => 'no-store, private',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  private function assertSignedRequest(Request $request): void {
    $secret = trim((string) Settings::get('brebo_calc_shared_secret', getenv('BREBO_CALC_SHARED_SECRET') ?: ''));
    if ($secret === '') {
      throw new AccessDeniedHttpException('Calc integration is not configured.');
    }

    $timestamp = trim((string) $request->headers->get('X-BREBO-Timestamp', ''));
    $requestId = trim((string) $request->headers->get('X-BREBO-Request-Id', ''));
    $signature = trim((string) $request->headers->get('X-BREBO-Signature', ''));
    if (!ctype_digit($timestamp) || !preg_match('/^[0-9a-fA-F-]{36}$/', $requestId) || !str_starts_with($signature, 'v1=')) {
      throw new AccessDeniedHttpException('Invalid authentication headers.');
    }

    $now = time();
    if (abs($now - (int) $timestamp) > 300) {
      throw new AccessDeniedHttpException('Expired request.');
    }

    $replayKey = 'brebo_calc_request:' . hash('sha256', $requestId);
    if ($this->cache->get($replayKey)) {
      throw new AccessDeniedHttpException('Replayed request.');
    }

    $path = $request->getPathInfo();
    $bodyHash = hash('sha256', '');
    $canonical = "GET\n{$path}\n{$bodyHash}\n{$timestamp}\n{$requestId}";
    $expected = 'v1=' . hash_hmac('sha256', $canonical, $secret);
    if (!hash_equals($expected, $signature)) {
      throw new AccessDeniedHttpException('Invalid signature.');
    }

    $this->cache->set($replayKey, TRUE, $now + 600);
  }


  /** @return string[] */
  private function fieldValues(NodeInterface $node, string $field): array {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return [];
    }
    $values = [];
    foreach ($node->get($field) as $item) {
      $value = trim((string) ($item->value ?? ''));
      if ($value !== '') {
        $values[] = $value;
      }
    }
    return array_values(array_unique($values));
  }

  private function fieldValue(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

}
