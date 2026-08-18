<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Plugin\QueueWorker;

use Drupal\brebo_finance\Service\DigitalController;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs scheduled Digital Controller reviews outside web requests.
 */
#[QueueWorker(
  id: 'brebo_finance_digital_controller',
  title: new TranslatableMarkup('BREBO Finance Digital Controller'),
  cron: ['time' => 60],
)]
final class DigitalControllerQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    private readonly DigitalController $digitalController,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition,
  ): self {
    return new self(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('brebo_finance.digital_controller'),
    );
  }

  public function processItem($data): void {
    $projectNid = (int) ($data['project_nid'] ?? 0);
    if ($projectNid <= 0) {
      throw new \InvalidArgumentException('Digital Controller queue item has no valid project.');
    }

    $this->digitalController->createScheduledRun($projectNid);
  }

}
