<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Serves private performance evidence to authorised finance users. */
final class PerformanceEvidenceViewController extends ControllerBase {
  public function view(int $fid): BinaryFileResponse {
    $file=$this->entityTypeManager()->getStorage('file')->load($fid);
    if(!$file instanceof FileInterface) throw new NotFoundHttpException('Evidence file does not exist.');
    $uri=(string)$file->getFileUri();
    if(!str_starts_with($uri,'private://brebo/performance-evidence/')) throw new NotFoundHttpException('File is not performance evidence.');
    $path=\Drupal::service('file_system')->realpath($uri); if(!$path||!is_file($path)) throw new NotFoundHttpException('Evidence file is unavailable.');
    $response=new BinaryFileResponse($path); $response->headers->set('Content-Type',(string)$file->getMimeType()); $response->headers->set('Cache-Control','private, no-store');
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE,$file->getFilename()); return $response;
  }
}
