<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentStorageLocator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stable provider-neutral entrypoint for opening immutable BREBO originals.
 */
final class DocumentOpenController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DocumentStorageLocator $storageLocator,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_document_data.storage_locator'),
      $container->get('file_system'),
    );
  }

  public function open(int $document_id): Response {
    $document = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id', 'title', 'original_filename', 'mime_type', 'lifecycle_status'])
      ->condition('id', $document_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$document || ($document['lifecycle_status'] ?? '') === 'deleted') {
      throw new NotFoundHttpException();
    }

    $location = $this->storageLocator->locate($document_id);
    $mode = (string) ($location['access_mode'] ?? 'none');
    $availability = (string) ($location['availability'] ?? 'unavailable');

    if ($mode === 'local_private' && $availability === 'locatable') {
      $uri = (string) ($location['local_uri'] ?? '');
      $path = $uri !== '' ? $this->fileSystem->realpath($uri) : FALSE;
      if (!is_string($path) || $path === '' || !is_file($path)) {
        return $this->availabilityResponse('Het originele document is geregistreerd, maar het fysieke bestand is momenteel niet beschikbaar.', 404);
      }

      $response = new BinaryFileResponse($path);
      $mime = trim((string) ($document['mime_type'] ?? ''));
      if ($mime !== '') {
        $response->headers->set('Content-Type', $mime);
      }
      $filename = trim((string) ($document['original_filename'] ?? $document['title'] ?? 'document')) ?: 'document';
      $response->setContentDisposition('inline', $filename);
      $response->headers->set('X-BREBO-Document-Id', (string) $document_id);
      $response->headers->set('X-BREBO-Storage-Provider', (string) ($location['provider'] ?? 'unknown'));
      return $response;
    }

    if ($mode === 'source_provider' && $availability === 'source_available') {
      return $this->availabilityResponse('Dit origineel staat nog bij de bronmailbox. De vaste BREBO-link werkt al; de bronprovider-adapter wordt in de volgende stap aangesloten.', 409);
    }

    if ($mode === 'object_storage' && $availability === 'provider_config_required') {
      return $this->availabilityResponse('Dit document wijst naar object storage. De providerconfiguratie is nog niet aangesloten op de BREBO open-route.', 409);
    }

    return $this->availabilityResponse('Voor dit document is momenteel geen ondersteunde fysieke opslaglocatie beschikbaar.', 404);
  }

  private function availabilityResponse(string $message, int $status): Response {
    return new Response($message, $status, [
      'Content-Type' => 'text/plain; charset=UTF-8',
      'Cache-Control' => 'no-store, private',
    ]);
  }

}
