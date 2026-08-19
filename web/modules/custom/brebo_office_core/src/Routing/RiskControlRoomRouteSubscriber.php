<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/** Adds Control Room workflow routes without duplicating routing files. */
final class RiskControlRoomRouteSubscriber extends RouteSubscriberBase {
  protected function alterRoutes(RouteCollection $collection): void {
    if(!$collection->get('brebo_office_core.risk_control_room')){$collection->add('brebo_office_core.risk_control_room',new Route('/regie/control-room',['_controller'=>'\\Drupal\\brebo_office_core\\Controller\\RiskControlRoomController::overview','_title'=>'BREBO Control Room'],['_permission'=>'access content']));}
    $collection->add('brebo_office_core.risk_control_room_action',new Route('/regie/control-room/{escalation}/actie',['_form'=>'\\Drupal\\brebo_office_core\\Form\\RiskEscalationCreateActionForm','_title'=>'BREBO actie aanmaken'],['_permission'=>'access content','escalation'=>'\\d+']));
    $collection->add('brebo_office_core.risk_improvement_register',new Route('/regie/control-room/verbetering/{fingerprint}/registreren',['_form'=>'\\Drupal\\brebo_office_core\\Form\\RiskImprovementRegisterForm','_title'=>'Preventief verbeterpunt registreren'],['_permission'=>'access content','fingerprint'=>'[a-f0-9]{64}']));
    $collection->add('brebo_office_core.risk_improvement_decide',new Route('/regie/control-room/verbetering/{improvement}',['_form'=>'\\Drupal\\brebo_office_core\\Form\\RiskImprovementDecisionForm','_title'=>'Preventieve verbetering'],['_permission'=>'access content','improvement'=>'\\d+']));
    $collection->add('brebo_office_core.risk_improvement_embedding_create',new Route('/regie/control-room/verbetering/{improvement}/borgen',['_form'=>'\\Drupal\\brebo_office_core\\Form\\RiskImprovementEmbeddingForm','_title'=>'Verbetering structureel borgen'],['_permission'=>'access content','improvement'=>'\\d+']));
    $collection->add('brebo_office_core.risk_improvement_embedding_review',new Route('/regie/control-room/borging/{embedding}',['_form'=>'\\Drupal\\brebo_office_core\\Form\\RiskImprovementEmbeddingForm','_title'=>'Borgingsvoorstel beoordelen'],['_permission'=>'access content','embedding'=>'\\d+']));
  }
}
