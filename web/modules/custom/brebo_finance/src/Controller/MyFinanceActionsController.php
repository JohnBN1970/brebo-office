<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\InvoiceBlockerActionManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Personal financial action work queue for the signed-in BREBO user. */
final class MyFinanceActionsController extends ControllerBase {
  public function __construct(private readonly InvoiceBlockerActionManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.invoice_blocker_action_manager'));
  }

  public function view(): JsonResponse {
    $uid=(int)$this->currentUser()->id();
    $data=$this->manager->ownerSummary($uid);
    $data['owner']=['uid'=>$uid,'name'=>$this->currentUser()->getDisplayName()];
    return new JsonResponse($data,200,['Cache-Control'=>'private, no-store']);
  }
}
