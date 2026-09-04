<?php

declare(strict_types=1);

namespace Drupal\brebo_finance;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\brebo_finance\Service\PaymentBatchManager;
use Symfony\Component\DependencyInjection\Reference;

/** Registers Finance services that belong to the payment-center completion slice. */
final class BreboFinanceServiceProvider extends ServiceProviderBase {

  public function register(ContainerBuilder $container): void {
    if ($container->hasDefinition('brebo_finance.payment_batch_manager')) {
      return;
    }

    $container->register('brebo_finance.payment_batch_manager', PaymentBatchManager::class)
      ->setArguments([
        new Reference('database'),
        new Reference('brebo_finance.purchase_invoice_importer'),
        new Reference('brebo_finance.vat_calculator'),
      ]);
  }

}
