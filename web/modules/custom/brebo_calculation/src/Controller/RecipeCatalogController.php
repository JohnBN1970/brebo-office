<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brebo_calculation\Service\RecipeCatalogService;
use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** HMAC-protected published recipe catalog for external Calc. */
final class RecipeCatalogController extends ControllerBase {

  public function __construct(
    private readonly RecipeCatalogService $catalog,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.recipe_catalog'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function published(Request $request): JsonResponse {
    $this->authenticator->assertSigned($request, '');
    return new JsonResponse([
      'contract' => 'brebo-recipe-catalog-v1',
      'catalog' => $this->catalog->publishedCatalog(),
    ], 200, ['Cache-Control' => 'no-store, private']);
  }

}
