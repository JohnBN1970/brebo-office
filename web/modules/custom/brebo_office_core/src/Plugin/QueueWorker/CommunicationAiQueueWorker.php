<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Plugin\QueueWorker;

use Drupal\brebo_office_core\Service\CommunicationAiProcessor;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** @QueueWorker(id = "brebo_communication_ai", title = @Translation("BREBO communication AI"), cron = {"time" = 60}) */
final class CommunicationAiQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly EntityTypeManagerInterface $entityTypeManager, private readonly CommunicationAiProcessor $processor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('brebo_office_core.communication_ai_processor'));
  }

  public function processItem($data): void {
    $communication = $this->entityTypeManager->getStorage('node')->load((int) ($data['nid'] ?? 0));
    if ($communication) {
      $this->processor->process($communication);
    }
  }

}
