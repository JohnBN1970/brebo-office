<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\InvoiceBlockerActionManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Personal work queue API for the signed-in user. */
final class MyFinanceActionsController extends ControllerBase {
  public function __construct(private readonly InvoiceBlockerActionManager $actions) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.invoice_blocker_action_manager'));
  }

  public function view(): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $data = $this->actions->ownerSummary($uid);
    $data['owner'] = ['uid' => $uid, 'name' => $this->currentUser()->getDisplayName()];
    return new JsonResponse($data, 200, ['Cache-Control' => 'private, no-store']);
  }
}
