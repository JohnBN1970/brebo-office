<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Secure upload endpoint for performance evidence captured on site. */
final class PerformanceEvidenceUploadController extends ControllerBase {
  public function upload(Request $request): JsonResponse {
    $upload=$request->files->get('file'); if($upload===NULL) throw new BadRequestHttpException('Upload field "file" is required.');
    $allowed=['image/jpeg','image/png','image/webp','application/pdf']; if(!in_array((string)$upload->getMimeType(),$allowed,TRUE)) throw new BadRequestHttpException('Only JPG, PNG, WEBP and PDF evidence is allowed.');
    if((int)$upload->getSize()>20*1024*1024) throw new BadRequestHttpException('Evidence file exceeds 20 MB.');
    $directory='private://brebo/performance-evidence/'.date('Y/m'); \Drupal::service('file_system')->prepareDirectory($directory,\Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY|\Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
    $safe=preg_replace('/[^A-Za-z0-9._-]+/','-',basename((string)$upload->getClientOriginalName()))?:'evidence'; $destination=$directory.'/'.time().'-'.bin2hex(random_bytes(6)).'-'.$safe;
    $uri=\Drupal::service('file_system')->saveData((string)file_get_contents($upload->getRealPath()),$destination,\Drupal\Core\File\FileExists::Rename); if(!$uri) throw new BadRequestHttpException('Evidence could not be stored.');
    $file=File::create(['uri'=>$uri,'filename'=>basename($uri),'status'=>1,'uid'=>(int)$this->currentUser()->id()]); $file->save();
    return new JsonResponse(['fid'=>(int)$file->id(),'filename'=>$safe,'mime'=>(string)$upload->getMimeType(),'size'=>(int)$upload->getSize(),'evidence_ref'=>'file:'.$file->id()],201,['Cache-Control'=>'private, no-store']);
  }
}
