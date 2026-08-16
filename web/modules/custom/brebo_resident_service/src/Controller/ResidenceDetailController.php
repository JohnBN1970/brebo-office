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
      $residentRows[] = [$resident->display_name, $resident->email ?: '—', $resident->phone ?: '—', ucfirst((string) $resident->preferred_channel), $resident->contact_allowed ? $this->t('Ja') : $this->t('Nee')];
    }

    $caseRows = [];
    $caseIds = [];
    $openCases = 0;
    $cases = $this->database->select('brebo_resident_case', 'c')->fields('c', ['id', 'case_number', 'case_type', 'title', 'priority', 'status', 'reported_at', 'due_at', 'resolved_at'])->condition('residence_id', $residence_id)->orderBy('reported_at', 'DESC')->execute();
    foreach ($cases as $case) {
      $caseIds[] = (int) $case->id;
      if (!in_array((string) $case->status, ['closed', 'cancelled'], TRUE)) {
        $openCases++;
      }
      $caseRows[] = [$case->case_number, ucfirst((string) $case->case_type), $case->title, ucfirst((string) $case->priority), ucfirst(str_replace('_', ' ', (string) $case->status)), date('d-m-Y H:i', (int) $case->reported_at), $case->due_at ? date('d-m-Y H:i', (int) $case->due_at) : '—'];
    }

    $timelineRows = [];
    if ($caseIds) {
      $events = $this->database->select('brebo_resident_case_event', 'e')->fields('e', ['case_id', 'event_type', 'actor_uid', 'communication_nid', 'task_id', 'notes', 'created'])->condition('case_id', $caseIds, 'IN')->orderBy('created', 'DESC')->range(0, 100)->execute();
      foreach ($events as $event) {
        $timelineRows[] = [date('d-m-Y H:i', (int) $event->created), ucfirst(str_replace('_', ' ', (string) $event->event_type)), $event->notes ?: '—', $event->communication_nid ?: '—', $event->task_id ?: '—'];
      }
    }

    $photoRows = [];
    $photoCount = $annotationTotal = 0;
    $photos = $this->database->select('brebo_photo', 'p')->fields('p', ['id', 'original_uri', 'captured_at', 'created_by_uid'])->condition('context_type', 'residence')->condition('context_id', $residence_id)->orderBy('captured_at', 'DESC')->execute();
    foreach ($photos as $photo) {
      $photoCount++;
      $annotationCount = (int) $this->database->select('brebo_photo_annotation', 'a')->condition('photo_id', (int) $photo->id)->countQuery()->execute()->fetchField();
      $annotationTotal += $annotationCount;
      $photoRows[] = [$photo->original_uri, $photo->captured_at ? date('d-m-Y H:i', (int) $photo->captured_at) : '—', $annotationCount, $photo->created_by_uid ?: '—'];
    }

    $occupancy = ucfirst(str_replace('_', ' ', (string) $residence['occupancy_status']));
    $access = ucfirst(str_replace('_', ' ', (string) $residence['access_status']));
    $accessProblem = in_array((string) $residence['access_status'], ['blocked', 'no_contact', 'refused'], TRUE);

    return [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-cockpit']],
      'header' => ['#markup' => '<div class="brebo-cockpit__header"><div><p class="brebo-cockpit__intro">Permanent woning-/gebruiksobjectdossier met BAG-identiteit, bewonerscontext, servicehistorie en bewijs. Technische projectinformatie wordt alleen toegevoegd wanneer de scope dit vereist.</p></div></div>'],
      'kpis' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-kpis']],
        'occupancy' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . htmlspecialchars($occupancy, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-kpi__label">Bewoning</span></div>'],
        'access' => ['#markup' => '<div class="brebo-kpi ' . ($accessProblem ? 'brebo-kpi--critical' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . htmlspecialchars($access, ENT_QUOTES, 'UTF-8') . '</span><span class="brebo-kpi__label">Toegang</span></div>'],
        'residents' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . count($residentRows) . '</span><span class="brebo-kpi__label">Bewoners / contacten</span></div>'],
        'cases' => ['#markup' => '<div class="brebo-kpi ' . ($openCases ? 'brebo-kpi--attention' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . $openCases . '</span><span class="brebo-kpi__label">Open dossiers</span></div>'],
      ],
      'identity' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">Woning / gebruiksobject</h2></div>'],
        'items' => ['#theme' => 'item_list', '#items' => [
          $this->t('Adres: @value', ['@value' => $residence['address_line']]),
          $this->t('BAG verblijfsobject: @value', ['@value' => $residence['bag_verblijfsobject_id'] ?: '—']),
          $this->t('BAG nummeraanduiding: @value', ['@value' => $residence['bag_nummeraanduiding_id'] ?: '—']),
          $this->t('Gebouw node: @value', ['@value' => $residence['building_nid']]),
          $this->t('Project: @value', ['@value' => $residence['project_id'] ?: '—']),
          $this->t('Toegangsnotitie: @value', ['@value' => $residence['access_notes'] ?: '—']),
        ]],
      ],
      'residents' => $this->sectionTable('Bewoners / contactpersonen', ['Naam', 'E-mail', 'Telefoon', 'Voorkeurskanaal', 'Contact toegestaan'], $residentRows, 'Nog geen bewoners gekoppeld.'),
      'cases' => $this->sectionTable('Meldingen, klachten, schade & service', ['Nummer', 'Type', 'Onderwerp', 'Prioriteit', 'Status', 'Gemeld', 'Deadline'], $caseRows, 'Geen dossiers voor deze woning.'),
      'photos' => $this->sectionTable('Foto’s & markeringen (' . $photoCount . ' foto’s · ' . $annotationTotal . ' markeringen)', ['Origineel', 'Opnamedatum', 'Markeringen', 'Maker'], $photoRows, 'Nog geen woningfoto’s gekoppeld.'),
      'timeline' => $this->sectionTable('Dossierhistorie', ['Datum', 'Gebeurtenis', 'Notitie', 'Communicatie', 'Taak'], $timelineRows, 'Nog geen dossierhistorie.'),
      '#cache' => ['max-age' => 0],
    ];
  }

  private function sectionTable(string $title, array $header, array $rows, string $empty): array {
    return [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
      'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2></div>'],
      'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-table-wrap']], 'table' => ['#type' => 'table', '#header' => $header, '#rows' => $rows, '#empty' => $this->t($empty)]],
    ];
  }
}
