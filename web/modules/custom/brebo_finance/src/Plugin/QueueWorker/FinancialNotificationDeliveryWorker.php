<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Plugin\QueueWorker;

use Drupal\brebo_finance\Service\FinancialNotificationOutbox;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Prepares durable BREBO Finance notifications for in-app delivery. */
#[QueueWorker(
  id: 'brebo_finance_notification_delivery',
  title: new TranslatableMarkup('BREBO Finance notification delivery'),
  cron: ['time' => 30],
)]
final class FinancialNotificationDeliveryWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FinancialNotificationOutbox $outbox,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('brebo_finance.financial_notification_outbox'),
    );
  }

  public function processItem($data): void {
    $outboxId = is_array($data) ? (int) ($data['outbox_id'] ?? 0) : 0;
    if ($outboxId <= 0) return;

    $row = $this->outbox->load($outboxId);
    if ($row === NULL || !in_array((string) $row['status'], ['queued', 'retry'], TRUE)) return;

    try {
      // The first adapter is intentionally in-app. Marking ready exposes the
      // durable outbox record to the BREBO Office notification API/UI. Mail and
      // push adapters can consume the same record later without changing the
      // financial decision workflow.
      $this->outbox->markReady($outboxId);
    }
    catch (\Throwable $exception) {
      $this->outbox->markRetry($outboxId, $exception->getMessage());
      throw $exception;
    }
  }
}
