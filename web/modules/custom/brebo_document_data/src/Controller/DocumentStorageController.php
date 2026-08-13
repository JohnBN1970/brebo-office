<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentStorageLocator;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only storage metadata for a BREBO document.
 *
 * Binary delivery remains provider-specific; this endpoint exposes only a
 * stable document access contract and never leaks mutable dossier names into
 * physical storage identity.
 */
final class DocumentStorageController extends ControllerBase {

  public function __construct(
    private readonly DocumentStorageLocator $storageLocator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_document_data.storage_locator'));
  }

  public function view(int $document_id): array {
    $location = $this->storageLocator->locate($document_id);
    if ($location === NULL) {
      throw new NotFoundHttpException();
    }

    return [
      'summary' => [
        '#theme' => 'item_list',
        '#title' => 'Documentopslag',
        '#items' => [
          'Bestand: ' . (string) $location['filename'],
          'Provider: ' . (string) $location['provider'],
          'Beschikbaarheid: ' . (string) $location['availability'],
          'SHA-256: ' . (string) $location['sha256'],
          'Origineel immutable: ja',
        ],
      ],
      'note' => [
        '#markup' => '<p>De dossiernaam en fysieke opslag zijn bewust ontkoppeld. De uiteindelijke binary-delivery wordt per storage-provider afgehandeld.</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
