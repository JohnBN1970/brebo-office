<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ResidenceDetailController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function title(int $residence_id): string {
    $address = $this->database->select('brebo_residence', 'r')->fields('r', ['address_line'])->condition('id', $residence_id)->execute()->fetchField();
    if (!$address) {
      throw new NotFoundHttpException();
    }
    return $this->t('Woningdossier — @address', ['@address' => $address]);
  }

  public function detail(int $residence_id): array {
    $residence = $this->database->select('brebo_residence', 'r')->fields('r')->condition('id', $residence_id)->execute()->fetchAssoc();
    if (!$residence) {
      throw new NotFoundHttpException();
    }

    $residentRows = [];
    $residents = $this->database->select('brebo_resident', 'p')->fields('p', ['display_name', 'email', 'phone', 'preferred_channel', 'contact_allowed'])->condition('residence_id', $residence_id)->orderBy('display_name')->execute();
    foreach ($residents as $resident) {
      $residentRows[] = [
        $resident->display_name,
        $resident->email ?: '—',
        $resident->phone ?: '—',
        ucfirst((string) $resident->preferred_channel),
        $resident->contact_allowed ? $this->t('Ja') : $this->t('Nee'),
      ];
    }

    $caseRows = [];
    $caseIds = [];
    $cases = $this->database->select('brebo_resident_case', 'c')->fields('c', ['id', 'case_number', 'case_type', 'title', 'priority', 'status', 'reported_at', 'due_at', 'resolved_at'])->condition('residence_id', $residence_id)->orderBy('reported_at', 'DESC')->execute();
    foreach ($cases as $case) {
      $caseIds[] = (int) $case->id;
      $caseRows[] = [
        $case->case_number,
        ucfirst((string) $case->case_type),
        $case->title,
        ucfirst((string) $case->priority),
        ucfirst(str_replace('_', ' ', (string) $case->status)),
        date('d-m-Y H:i', (int) $case->reported_at),
        $case->due_at ? date('d-m-Y H:i', (int) $case->due_at) : '—',
      ];
    }

    $timelineRows = [];
    if ($caseIds) {
      $events = $this->database->select('brebo_resident_case_event', 'e')->fields('e', ['case_id', 'event_type', 'actor_uid', 'communication_nid', 'task_id', 'notes', 'created'])->condition('case_id', $caseIds, 'IN')->orderBy('created', 'DESC')->range(0, 100)->execute();
      foreach ($events as $event) {
        $timelineRows[] = [
          date('d-m-Y H:i', (int) $event->created),
          ucfirst(str_replace('_', ' ', (string) $event->event_type)),
          $event->notes ?: '—',
          $event->communication_nid ?: '—',
          $event->task_id ?: '—',
        ];
      }
    }

    $photoRows = [];
    $photos = $this->database->select('brebo_photo', 'p')->fields('p', ['id', 'original_uri', 'captured_at', 'created_by_uid'])->condition('context_type', 'residence')->condition('context_id', $residence_id)->orderBy('captured_at', 'DESC')->execute();
    foreach ($photos as $photo) {
      $annotationCount = (int) $this->database->select('brebo_photo_annotation', 'a')->condition('photo_id', (int) $photo->id)->countQuery()->execute()->fetchField();
      $photoRows[] = [
        $photo->original_uri,
        $photo->captured_at ? date('d-m-Y H:i', (int) $photo->captured_at) : '—',
        $annotationCount,
        $photo->created_by_uid ?: '—',
      ];
    }

    return [
      'identity' => [
        '#type' => 'details', '#title' => $this->t('Woning / gebruiksobject'), '#open' => TRUE,
        'items' => ['#theme' => 'item_list', '#items' => [
          $this->t('Adres: @value', ['@value' => $residence['address_line']]),
          $this->t('BAG verblijfsobject: @value', ['@value' => $residence['bag_verblijfsobject_id'] ?: '—']),
          $this->t('BAG nummeraanduiding: @value', ['@value' => $residence['bag_nummeraanduiding_id'] ?: '—']),
          $this->t('Gebouw node: @value', ['@value' => $residence['building_nid']]),
          $this->t('Project: @value', ['@value' => $residence['project_id'] ?: '—']),
          $this->t('Bewoning: @value', ['@value' => $residence['occupancy_status']]),
          $this->t('Toegang: @value', ['@value' => $residence['access_status']]),
          $this->t('Toegangsnotitie: @value', ['@value' => $residence['access_notes'] ?: '—']),
        ]],
      ],
      'residents' => ['#type' => 'table', '#caption' => $this->t('Bewoners / contactpersonen'), '#header' => ['Naam', 'E-mail', 'Telefoon', 'Voorkeurskanaal', 'Contact toegestaan'], '#rows' => $residentRows, '#empty' => $this->t('Nog geen bewoners gekoppeld.')],
      'cases' => ['#type' => 'table', '#caption' => $this->t('Meldingen, klachten, schade & service'), '#header' => ['Nummer', 'Type', 'Onderwerp', 'Prioriteit', 'Status', 'Gemeld', 'Deadline'], '#rows' => $caseRows, '#empty' => $this->t('Geen dossiers voor deze woning.')],
      'photos' => ['#type' => 'table', '#caption' => $this->t('Foto’s & markeringen'), '#header' => ['Origineel', 'Opnamedatum', 'Markeringen', 'Maker'], '#rows' => $photoRows, '#empty' => $this->t('Nog geen woningfoto’s gekoppeld.')],
      'timeline' => ['#type' => 'table', '#caption' => $this->t('Dossierhistorie'), '#header' => ['Datum', 'Gebeurtenis', 'Notitie', 'Communicatie', 'Taak'], '#rows' => $timelineRows, '#empty' => $this->t('Nog geen dossierhistorie.')],
      '#cache' => ['max-age' => 0],
    ];
  }
}
