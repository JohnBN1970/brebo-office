<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\EventSubscriber;

use Drupal\brebo_contract_control\Service\ManagementDecisionRecordService;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Shows due 30/90-day decision reviews in the Management Control Center. */
final class ManagementDecisionReviewSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ManagementDecisionRecordService $records,
    private readonly MessengerInterface $messenger,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', -50]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || $this->routeMatch->getRouteName() !== 'brebo_contract_control.management_control_center') {
      return;
    }

    $due = $this->records->dueReviews();
    if ($due === []) {
      return;
    }

    $first = $due[0];
    $count = count($due);
    $days = (int) ($first['review_days'] ?? 30);
    $recordId = (int) ($first['id'] ?? 0);
    $url = Url::fromRoute('brebo_contract_control.management_decision_outcome_review', [
      'record_id' => $recordId,
      'review_days' => $days,
    ])->toString();

    $label = $count === 1 ? '1 managementbesluit moet worden geëvalueerd.' : $count . ' managementbesluiten moeten worden geëvalueerd.';
    $this->messenger->addWarning($label . ' Eerstvolgende: ' . $days . '-dagenreview van Decision Record ' . $recordId . '. ' . $url);
  }
}
