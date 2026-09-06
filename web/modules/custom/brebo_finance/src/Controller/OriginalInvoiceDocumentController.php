<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\OriginalInvoiceSourceResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Streams the canonical private source document for a purchase invoice. */
final class OriginalInvoiceDocumentController extends ControllerBase {

  public function __construct(private readonly OriginalInvoiceSourceResolver $resolver) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.original_invoice_source_resolver'));
  }

  public function view(int $invoice_id): BinaryFileResponse {
    $source = $this->resolver->resolve($invoice_id, static fn(int $fid) => File::load($fid));
    if ($source === NULL) {
      throw new NotFoundHttpException('No canonical original invoice document is available.');
    }

    $file = $source['file'];
    $realpath = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (!is_string($realpath) || $realpath === '' || !is_file($realpath) || !is_readable($realpath)) {
      throw new NotFoundHttpException('The canonical original invoice document is unavailable.');
    }

    $mime = strtolower(trim($source['mime_type']));
    $inline = $mime === 'application/pdf' || str_starts_with($mime, 'image/');
    $response = new BinaryFileResponse($realpath);
    $response->headers->set('Content-Type', $mime !== '' ? $mime : 'application/octet-stream');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->setContentDisposition(
      $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $source['filename'],
    );
    return $response;
  }

}
