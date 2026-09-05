<?php

declare(strict_types=1);

namespace Drupal\brebo_finance;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\brebo_finance\Routing\PaymentCenterRouteSubscriber;
use Drupal\brebo_finance\Service\MailPurchaseInvoiceRouter;
use Drupal\brebo_finance\Service\PaymentBatchManager;
use Drupal\brebo_finance\Service\SepaPain001Generator;
use Symfony\Component\DependencyInjection\Reference;

/** Registers Finance services that need explicit compatibility wiring. */
final class BreboFinanceServiceProvider extends ServiceProviderBase {

  public function register(ContainerBuilder $container): void {
    if (!$container->hasDefinition('brebo_finance.payment_batch_manager')) {
      $container->register('brebo_finance.payment_batch_manager', PaymentBatchManager::class)
        ->setArguments([
          new Reference('database'),
          new Reference('brebo_finance.purchase_invoice_importer'),
          new Reference('brebo_finance.vat_calculator'),
        ]);
    }

    if (!$container->hasDefinition('brebo_finance.sepa_pain001_generator')) {
      $container->register('brebo_finance.sepa_pain001_generator', SepaPain001Generator::class)
        ->setArguments([
          new Reference('brebo_finance.payment_batch_manager'),
        ]);
    }

    if (!$container->hasDefinition('brebo_finance.payment_center_route_subscriber')) {
      $container->register('brebo_finance.payment_center_route_subscriber', PaymentCenterRouteSubscriber::class)
        ->addTag('event_subscriber');
    }

    // Keep the established mail-intake contract available, but make it only an
    // adapter: classification happens here and canonical Finance creation is
    // delegated to the BREBO-wide source-neutral intake pipeline.
    if (!$container->hasDefinition('brebo_finance.mail_purchase_invoice_router')) {
      $container->register('brebo_finance.mail_purchase_invoice_router', MailPurchaseInvoiceRouter::class)
        ->setArguments([
          new Reference('brebo_data_intake.source_neutral_intake_manager'),
        ]);
    }
  }

}
