<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialDecisionAssignmentResolver;
use Drupal\brebo_finance\Service\FinancialDecisionInbox;
use Drupal\brebo_finance\Service\FinancialNotificationOutbox;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** In-app notification API for BREBO Finance. */
final class FinancialNotificationController extends ControllerBase {

  public function __construct(
    private readonly FinancialNotificationOutbox $outbox,
    private readonly FinancialDecisionInbox $decisionInbox,
    private readonly FinancialDecisionAssignmentResolver $assignmentResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_notification_outbox'),
      $container->get('brebo_finance.financial_decision_inbox'),
      $container->get('brebo_finance.financial_decision_assignment_resolver'),
    );
  }

  public function inbox(Request $request): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $unreadOnly = !$request->query->has('all') || !$request->query->getBoolean('all');
    $items = $this->outbox->forUser($uid, $unreadOnly);

    $counts = ['now' => 0, 'today' => 0, 'this_week' => 0];
    $totalExposure = 0.0;
    $decisions = [];
    foreach ($this->decisionInbox->pending() as $decision) {
      $canAct = $this->assignmentResolver->canAct($this->currentUser(), (string) $decision['gate'], (string) $decision['authorization']['level']);
      if (!$canAct['authorized']) continue;
      $band = (string) ($decision['priority']['band'] ?? 'this_week');
      if (isset($counts[$band])) $counts[$band]++;
      $totalExposure += (float) ($decision['exposure']['exposure_amount'] ?? 0);
      $decisions[] = [
        'exception_id' => (int) $decision['exception_id'],
        'project_nid' => (int) $decision['project_nid'],
        'gate' => (string) $decision['gate'],
        'exposure_amount' => (string) ($decision['exposure']['exposure_amount'] ?? '0.00'),
        'priority' => $decision['priority'],
      ];
    }

    return new JsonResponse([
      'uid' => $uid,
      'unread_count' => $this->outbox->unreadCount($uid),
      'count' => count($items),
      'items' => $items,
      'decision_summary' => [
        'count' => count($decisions),
        'now' => $counts['now'],
        'today' => $counts['today'],
        'this_week' => $counts['this_week'],
        'total_exposure' => number_format($totalExposure, 2, '.', ''),
        'top_decisions' => array_slice($decisions, 0, 5),
      ],
    ]);
  }

  public function markRead(int $notification_id): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $this->outbox->markReadForUser($notification_id, $uid);
    return new JsonResponse(['status' => 'read', 'notification_id' => $notification_id, 'unread_count' => $this->outbox->unreadCount($uid)]);
  }
}
