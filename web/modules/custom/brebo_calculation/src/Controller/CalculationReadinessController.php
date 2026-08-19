<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Returns offer-readiness data for the calculation workbench. */
final class CalculationReadinessController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationReadinessInspector $readinessInspector,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_calculation.readiness_inspector'),
    );
  }

  public function status(NodeInterface $node): JsonResponse {
    if ($node->bundle() !== 'brebo_calculation') {
      return new JsonResponse(['status' => 'error', 'message' => 'Calculatie niet gevonden.'], 404);
    }

    $version = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version'])
      ->condition('calculation_id', (int) $node->id())
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!is_string($version) || $version === '') {
      return new JsonResponse([
        'status' => 'blocked',
        'blocking' => 1,
        'warnings' => 0,
        'checks' => [[
          'level' => 'error',
          'code' => 'missing_version',
          'label' => 'Calculatieversie ontbreekt',
          'reference' => 0,
        ]],
      ]);
    }

    $result = $this->readinessInspector->inspect((int) $node->id(), $version);
    $result['version'] = $version;
    return new JsonResponse($result);
  }

}
