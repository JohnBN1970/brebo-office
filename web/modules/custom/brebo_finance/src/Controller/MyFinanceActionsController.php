<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\PersonalWorkQueue;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Personal BREBO Office work queue for the signed-in user. */
final class MyFinanceActionsController extends ControllerBase {
  public function __construct(private readonly PersonalWorkQueue $workQueue) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.personal_work_queue'));
  }

  public function view(): JsonResponse {
    $uid=(int)$this->currentUser()->id();
    $data=$this->workQueue->build($uid);
    $data['owner']=['uid'=>$uid,'name'=>$this->currentUser()->getDisplayName()];
    return new JsonResponse($data,200,['Cache-Control'=>'private, no-store']);
  }
}
