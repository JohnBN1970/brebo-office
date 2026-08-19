<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\InvoiceBlockerActionManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** API for assigning and tracking actions on blocked invoice lines. */
final class InvoiceBlockerActionController extends ControllerBase {
  public function __construct(private readonly InvoiceBlockerActionManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.invoice_blocker_action_manager'));
  }

  public function project(int $project_nid): JsonResponse {
    return new JsonResponse(['project_nid'=>$project_nid,'items'=>$this->manager->forProject($project_nid)], 200, ['Cache-Control'=>'private, no-store']);
  }

  public function owners(): JsonResponse {
    $storage=$this->entityTypeManager()->getStorage('user');
    $ids=$storage->getQuery()->accessCheck(TRUE)->condition('status',1)->sort('name')->execute();
    $items=[];
    foreach($storage->loadMultiple($ids) as $account){
      if(!$account->hasPermission('access brebo finance')&&!$account->hasPermission('manage brebo procurement')&&!$account->hasPermission('approve brebo finance')) continue;
      $items[]=['uid'=>(int)$account->id(),'name'=>$account->getDisplayName(),'roles'=>array_values($account->getRoles(TRUE))];
    }
    return new JsonResponse(['items'=>$items],200,['Cache-Control'=>'private, no-store']);
  }

  public function save(int $project_nid, int $invoice_line_id, Request $request): JsonResponse {
    try { $data=json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new BadRequestHttpException('Invalid JSON body.'); }
    if(!is_array($data)) throw new BadRequestHttpException('JSON body must be an object.');
    foreach(['owner_uid','action','status'] as $field) if(!array_key_exists($field,$data)) throw new BadRequestHttpException('Missing field: '.$field);
    $owner=$this->entityTypeManager()->getStorage('user')->load((int)$data['owner_uid']);
    if(!$owner||!$owner->isActive()) throw new BadRequestHttpException('Verantwoordelijke gebruiker bestaat niet of is niet actief.');
    $due=null;
    if(isset($data['due_date'])&&$data['due_date']!==''){
      if(is_numeric($data['due_date'])) $due=(int)$data['due_date'];
      else { $parsed=strtotime((string)$data['due_date'].' 23:59:59'); if($parsed===false) throw new BadRequestHttpException('Invalid due_date.'); $due=$parsed; }
    }
    $item=$this->manager->save($invoice_line_id,$project_nid,(int)$data['owner_uid'],(string)$data['action'],$due,(string)$data['status'],(int)$this->currentUser()->id());
    $item['owner_name']=$owner->getDisplayName();
    return new JsonResponse($item,200,['Cache-Control'=>'private, no-store']);
  }
}
