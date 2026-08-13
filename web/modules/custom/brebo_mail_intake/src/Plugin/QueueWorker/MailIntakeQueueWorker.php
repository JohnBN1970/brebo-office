<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\brebo_mail_intake\Service\MailIntakePipeline;
use Psr\Container\ContainerInterface;

/**
 * Processes queued normalized mail through the canonical intake pipeline.
 *
 * @QueueWorker(
 *   id = "brebo_mail_intake_process",
 *   title = @Translation("BREBO Mail Intake processing"),
 *   cron = {"time" = 45}
 * )
 */
final class MailIntakeQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MailIntakePipeline $pipeline,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('brebo_mail_intake.pipeline'),
    );
  }

  public function processItem($data): void {
    if (!is_array($data) || !is_array($data['mail'] ?? NULL)) {
      throw new \InvalidArgumentException('Ongeldig Mail Intake queue-item.');
    }

    $this->pipeline->process($data['mail']);
  }

}
