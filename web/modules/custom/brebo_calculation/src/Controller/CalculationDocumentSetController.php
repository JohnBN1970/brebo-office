<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brebo_calculation\Service\CalculationDocumentSetService;
use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** API for the digital work preparer's calculation document proposal. */
final class CalculationDocumentSetController extends ControllerBase {

  public function __construct(
    private readonly CalculationDocumentSetService $sets,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.calculation_document_set'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function propose(Request $request, int $calculation): JsonResponse {
    $body = (string) $request->getContent();
    $this->authenticator->assertSigned($request, $body);

    $payload = json_decode($body, TRUE);
    $projectId = (int) ($payload['project_id'] ?? 0);
    if ($projectId <= 0 || $calculation <= 0) {
      throw new BadRequestHttpException('Project en calculatie zijn verplicht.');
    }
    return new JsonResponse([
      'contract' => 'brebo-calculation-document-set-v1',
      'set' => $this->sets->propose($projectId, $calculation),
    ], 201, ['Cache-Control' => 'no-store, private']);
  }

}
