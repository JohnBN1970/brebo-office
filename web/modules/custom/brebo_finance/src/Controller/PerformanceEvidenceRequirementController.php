<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\PerformanceEvidenceRequirementResolver;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Returns the minimum evidence profile for a construction activity. */
final class PerformanceEvidenceRequirementController extends ControllerBase {
  public function __construct(private readonly PerformanceEvidenceRequirementResolver $resolver) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_finance.performance_evidence_requirement_resolver')); }
  public function view(Request $request): JsonResponse {
    $description=trim((string)$request->query->get('description',''));
    return new JsonResponse($this->resolver->resolve($description),200,['Cache-Control'=>'private, no-store']);
  }
}
