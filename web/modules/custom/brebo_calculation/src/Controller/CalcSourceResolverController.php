<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Drupal\brebo_calculation\Service\CalcSourceResolver;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Resolves authoritative Office sources for external Calc recipes. */
final class CalcSourceResolverController extends ControllerBase {

  public function __construct(
    private readonly CalcSourceResolver $resolver,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.calc_source_resolver'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function resolve(Request $request): JsonResponse {
    if (!str_starts_with(strtolower((string) $request->headers->get('Content-Type', '')), 'application/json')) {
      return new JsonResponse(['error' => 'content_type', 'message' => 'application/json is required.'], 415);
    }

    try {
      $body = (string) $request->getContent();
      $this->authenticator->assertSigned($request, $body);
      $input = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($input)) {
        throw new \InvalidArgumentException('JSON object expected.');
      }
      $sources = $input['sources'] ?? NULL;
      if (!is_array($sources)) {
        throw new \InvalidArgumentException('sources must be an array.');
      }
      $projectId = isset($input['project_id']) && is_numeric($input['project_id']) ? (int) $input['project_id'] : NULL;
      return new JsonResponse([
        'contract' => 'brebo-office-calc-source-resolution-v1',
        'project_id' => $projectId,
        'results' => $this->resolver->resolve(array_values($sources), $projectId),
      ], 200, ['Cache-Control' => 'no-store, private']);
    }
    catch (\JsonException|\InvalidArgumentException $e) {
      return new JsonResponse(['error' => 'invalid_request', 'message' => $e->getMessage()], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'source_not_available', 'message' => $e->getMessage()], 409);
    }
  }

}
