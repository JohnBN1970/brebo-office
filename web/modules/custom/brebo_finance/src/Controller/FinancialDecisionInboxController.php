<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialDecisionAssignmentResolver;
use Drupal\brebo_finance\Service\FinancialDecisionInbox;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Read-only API for the BREBO financial decision inbox. */
final class FinancialDecisionInboxController extends ControllerBase {

  public function __construct(
    private readonly FinancialDecisionInbox $inbox,
    private readonly FinancialDecisionAssignmentResolver $assignmentResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_decision_inbox'),
      $container->get('brebo_finance.financial_decision_assignment_resolver'),
    );
  }

  public function view(Request $request): JsonResponse {
    $projectNid = $request->query->has('project_nid') ? (int) $request->query->get('project_nid') : NULL;
    $onlyMine = $request->query->getBoolean('mine', FALSE);
    $items = $this->inbox->pending($projectNid);

    if ($onlyMine) {
      $account = $this->currentUser();
      $items = array_values(array_filter($items, function (array $item) use ($account): bool {
        return $this->assignmentResolver->canAct($account, (string) $item['gate'], (string) $item['authorization']['level'])['authorized'];
      }));
    }

    return new JsonResponse([
      'generated_at' => time(),
      'filter' => ['project_nid' => $projectNid, 'mine' => $onlyMine],
      'count' => count($items),
      'items' => $items,
    ]);
  }

}
