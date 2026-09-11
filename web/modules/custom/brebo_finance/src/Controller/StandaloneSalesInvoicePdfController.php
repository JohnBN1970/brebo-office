<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\SalesInvoiceOutputBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/** Streams a generated sales-invoice concept PDF. */
final class StandaloneSalesInvoicePdfController extends ControllerBase {

  public function __construct(private readonly SalesInvoiceOutputBuilder $outputBuilder) {}

  public static function create(ContainerInterface $container): static {
    return new static(new SalesInvoiceOutputBuilder(
      $container->get('database'),
      $container->get('keyvalue'),
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.simple_pdf_renderer'),
    ));
  }

  public function preview(int $draft): Response {
    $pdf = $this->outputBuilder->conceptPdf($draft);
    return new Response(
      $pdf['content'],
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $pdf['filename'] . '"',
        'Cache-Control' => 'private, no-store, max-age=0',
        'X-Content-Type-Options' => 'nosniff',
      ],
    );
  }

}
