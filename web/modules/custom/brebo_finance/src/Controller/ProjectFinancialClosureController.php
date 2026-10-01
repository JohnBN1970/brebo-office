<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brebo_finance\Contract\ProjectReferenceGatewayInterface;
use Drupal\brebo_finance\Service\ProjectFinancialClosureManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectFinancialClosureController extends ControllerBase {
  public function __construct(private readonly ProjectFinancialClosureManager $closureManager, private readonly ProjectReferenceGatewayInterface $projects) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_finance.project_financial_closure_manager'), $container->get('brebo_finance.project_reference_gateway')); }

  public function closureState(int $project_nid): JsonResponse {
    $this->assertProject($project_nid);
    return new JsonResponse(['assessment' => $this->closureManager->assess($project_nid), 'closure' => $this->closureManager->closure($project_nid)]);
  }

  public function close(int $project_nid, Request $request): JsonResponse {
    $this->assertProject($project_nid);
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) throw new BadRequestHttpException('Ongeldige aanvraag.');
    try { $closure = $this->closureManager->close($project_nid, (int) $this->currentUser()->id(), (string) ($data['note'] ?? '')); }
    catch (\RuntimeException $e) { throw new BadRequestHttpException($e->getMessage(), $e); }
    return new JsonResponse(['closure' => $closure], 201);
  }

  private function assertProject(int $projectNid): void {
    if (!$this->projects->exists($projectNid)) throw new NotFoundHttpException('BREBO project does not exist.');
    if (!$this->projects->canView($projectNid)) throw new AccessDeniedHttpException('No access to this BREBO project.');
  }
}
