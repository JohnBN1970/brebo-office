<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\OutboundAttachmentPersistenceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\node\NodeInterface;

final class DrupalOutboundAttachmentPersistence implements OutboundAttachmentPersistenceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  public function promoteUploadedFile(int $communicationId, int $fileId): ?array {
    $communication = $this->loadCommunication($communicationId);
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $file->setPermanent();
    $file->save();
    $this->fileUsage->add($file, 'brebo_mail_intake', 'node', (string) $communicationId);

    $content = $this->readLocalUri($file->getFileUri());
    if ($content === NULL) {
      throw new \RuntimeException('De geüploade bijlage kon niet worden gecontroleerd.');
    }

    return [
      'id' => (int) $file->id(),
      'filename' => $file->getFilename(),
      'mime_type' => $file->getMimeType(),
      'file_size' => (int) $file->getSize(),
      'uri' => $file->getFileUri(),
      'content' => $content,
      'owner_name' => $communication->getOwner()->getDisplayName(),
    ];
  }

  public function attachUploadedFiles(int $communicationId, array $files): void {
    if ($files === []) {
      return;
    }

    $communication = $this->loadCommunication($communicationId);
    if (!$communication->hasField('field_brebo_comm_attachments')) {
      return;
    }

    $communication->set('field_brebo_comm_attachments', $files);
    $communication->setNewRevision(TRUE);
    $communication->setRevisionLogMessage('Uitgaande privébijlagen aan mailconcept gekoppeld.');
    $communication->save();
  }

  public function uploadedFiles(int $communicationId): array {
    $communication = $this->loadCommunication($communicationId);
    if (!$communication->hasField('field_brebo_comm_attachments')) {
      return [];
    }

    $attachments = [];
    foreach ($communication->get('field_brebo_comm_attachments')->referencedEntities() as $file) {
      if (!$file instanceof FileInterface) {
        continue;
      }

      $content = $this->readLocalUri($file->getFileUri());
      if ($content === NULL) {
        throw new \RuntimeException('Een geüploade mailbijlage is niet meer leesbaar.');
      }

      $attachments[] = [
        'filecontent' => $content,
        'filename' => $file->getFilename(),
        'filemime' => $file->getMimeType(),
      ];
    }

    return $attachments;
  }

  public function readLocalUri(string $uri): ?string {
    $path = $this->fileSystem->realpath($uri);
    if (!is_string($path) || !is_readable($path)) {
      return NULL;
    }
    return (string) file_get_contents($path);
  }

  private function loadCommunication(int $communicationId): NodeInterface {
    $communication = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$communication instanceof NodeInterface || $communication->bundle() !== 'brebo_communication') {
      throw new \RuntimeException('BREBO Communication kon niet worden geladen.');
    }
    return $communication;
  }

}
