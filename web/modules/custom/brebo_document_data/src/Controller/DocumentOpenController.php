<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentStorageLocator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
    private readonly ?object $sourceMailboxReader = NULL,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_document_data.storage_locator'),
      $container->get('file_system'),
      $container->has('brebo_mail_intake.source_mailbox_attachment_reader')
        ? $container->get('brebo_mail_intake.source_mailbox_attachment_reader')
        : NULL,
    );
  }

  public function open(int $document_id): Response {
    $document = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id', 'title', 'original_filename', 'mime_type', 'sha256', 'lifecycle_status'])
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
      return $this->openSourceMailboxDocument($document_id, $document, $location);
    }

    if ($mode === 'object_storage' && $availability === 'provider_config_required') {
      return $this->availabilityResponse('Dit document wijst naar object storage. De providerconfiguratie is nog niet aangesloten op de BREBO open-route.', 409);
    }

    return $this->availabilityResponse('Voor dit document is momenteel geen ondersteunde fysieke opslaglocatie beschikbaar.', 404);
  }

  /** @param array<string, mixed> $document
   *  @param array<string, mixed> $location
   */
  private function openSourceMailboxDocument(int $documentId, array $document, array $location): Response {
    if ($this->sourceMailboxReader === NULL || !method_exists($this->sourceMailboxReader, 'read')) {
      return $this->availabilityResponse('De bronmailbox-reader is momenteel niet beschikbaar.', 409);
    }

    $source = $this->database->select('brebo_document_source', 's')
      ->fields('s', ['source_system', 'source_timestamp_authoritative', 'source_timestamp_unix', 'id'])
      ->condition('document_id', $documentId)
      ->orderBy('source_timestamp_authoritative', 'DESC')
      ->orderBy('source_timestamp_unix', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $sourceSystem = trim((string) ($source['source_system'] ?? ''));
    $storageKey = trim((string) ($location['storage_key'] ?? ''));
    if ($sourceSystem === '' || $storageKey === '') {
      return $this->availabilityResponse('De herkomst van dit bronmailbox-document is niet volledig geregistreerd.', 409);
    }

    /** @var array<string, mixed> $result */
    $result = $this->sourceMailboxReader->read($sourceSystem, $storageKey);
    if (($result['state'] ?? '') !== 'available' || !array_key_exists('content', $result)) {
      $message = trim((string) ($result['message'] ?? 'De oorspronkelijke bronbijlage is momenteel niet beschikbaar.'));
      return $this->availabilityResponse($message !== '' ? $message : 'De oorspronkelijke bronbijlage is momenteel niet beschikbaar.', 409);
    }

    $content = (string) $result['content'];
    $expectedHash = strtolower(trim((string) ($document['sha256'] ?? '')));
    $actualHash = hash('sha256', $content);
    if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
      return $this->availabilityResponse('De bronbijlage wijkt af van de geregistreerde documenthash en wordt daarom niet geopend.', 409);
    }

    $mime = trim((string) ($result['mime_type'] ?? $document['mime_type'] ?? 'application/octet-stream')) ?: 'application/octet-stream';
    $filename = trim((string) ($result['filename'] ?? $document['original_filename'] ?? $document['title'] ?? 'document')) ?: 'document';
    $response = new Response($content, 200, [
      'Content-Type' => $mime,
      'Cache-Control' => 'private, no-store',
      'X-BREBO-Document-Id' => (string) $documentId,
      'X-BREBO-Storage-Provider' => (string) ($location['provider'] ?? 'source_mailbox'),
      'X-BREBO-Content-SHA256' => $actualHash,
    ]);
    $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition('inline', $filename));
    return $response;
  }

  private function availabilityResponse(string $message, int $status): Response {
    return new Response($message, $status, [
      'Content-Type' => 'text/plain; charset=UTF-8',
      'Cache-Control' => 'no-store, private',
    ]);
  }

}
