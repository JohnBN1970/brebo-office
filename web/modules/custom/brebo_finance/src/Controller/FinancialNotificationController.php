<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialNotificationOutbox;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** In-app notification API for BREBO Finance. */
final class FinancialNotificationController extends ControllerBase {

  public function __construct(private readonly FinancialNotificationOutbox $outbox) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_notification_outbox'));
  }

  public function inbox(Request $request): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $unreadOnly = !$request->query->has('all') || !$request->query->getBoolean('all');
    $items = $this->outbox->forUser($uid, $unreadOnly);
    return new JsonResponse([
      'uid' => $uid,
      'unread_count' => $this->outbox->unreadCount($uid),
      'count' => count($items),
      'items' => $items,
    ]);
  }

  public function markRead(int $notification_id): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $this->outbox->markReadForUser($notification_id, $uid);
    return new JsonResponse([
      'status' => 'read',
      'notification_id' => $notification_id,
      'unread_count' => $this->outbox->unreadCount($uid),
    ]);
  }
}
