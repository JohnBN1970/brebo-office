<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface OutboundAttachmentPersistenceInterface {

  /**
   * Makes an uploaded file permanent and registers usage for a communication.
   *
   * @return array{id:int,filename:string,mime_type:string,file_size:int,uri:string,content:string,owner_name:string}|null
   */
  public function promoteUploadedFile(int $communicationId, int $fileId): ?array;

  /**
   * @param list<array{target_id:int,description:string}> $files
   */
  public function attachUploadedFiles(int $communicationId, array $files): void;

  /**
   * @return list<array{filecontent:string,filename:string,filemime:string}>
   */
  public function uploadedFiles(int $communicationId): array;

  public function readLocalUri(string $uri): ?string;

}
