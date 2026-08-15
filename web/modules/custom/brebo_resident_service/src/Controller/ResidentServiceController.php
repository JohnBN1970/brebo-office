<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ResidentServiceController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function dashboard(): array {
    $now = time();
    $open = (int) $this->database->select('brebo_resident_case', 'c')->condition('status', ['closed', 'cancelled'], 'NOT IN')->countQuery()->execute()->fetchField();
    $overdue = (int) $this->database->select('brebo_resident_case', 'c')->condition('status', ['closed', 'cancelled'], 'NOT IN')->isNotNull('due_at')->condition('due_at', $now, '<')->countQuery()->execute()->fetchField();
    $complaints = (int) $this->database->select('brebo_resident_case', 'c')->condition('case_type', 'klacht')->condition('status', ['closed', 'cancelled'], 'NOT IN')->countQuery()->execute()->fetchField();
    $access = (int) $this->database->select('brebo_residence', 'r')->condition('access_status', ['blocked', 'no_contact', 'refused'], 'IN')->countQuery()->execute()->fetchField();
    $pendingScopes = (int) $this->database->select('brebo_address_scope_intake', 'i')->condition('status', 'resolved')->countQuery()->execute()->fetchField();

    $query = $this->database->select('brebo_resident_case', 'c');
    $query->leftJoin('brebo_residence', 'r', 'r.id = c.residence_id');
    $query->fields('c', ['case_number', 'case_type', 'title', 'priority', 'status', 'due_at']);
    $query->addField('r', 'address_line');
    $query->condition('c.status', ['closed', 'cancelled'], 'NOT IN')->orderBy('c.due_at', 'ASC')->orderBy('c.reported_at', 'ASC')->range(0, 50);
    $rows = [];
    foreach ($query->execute() as $case) {
      $rows[] = [$case->case_number, ucfirst((string) $case->case_type), $case->address_line ?: '—', $case->title, ucfirst((string) $case->priority), ucfirst((string) $case->status), $case->due_at ? date('d-m-Y H:i', (int) $case->due_at) : '—'];
    }

    return [
      'intro' => ['#markup' => '<p>Centraal overzicht van bewonersmeldingen, klachten, schade, toegang en nazorg.</p>'],
      'summary' => ['#theme' => 'item_list', '#items' => [
        $this->t('Open dossiers: @count', ['@count' => $open]), $this->t('Te laat: @count', ['@count' => $overdue]),
        $this->t('Open klachten: @count', ['@count' => $complaints]), $this->t('Toegangsproblemen: @count', ['@count' => $access]),
        Link::fromTextAndUrl($this->t('Adresvoorstellen uit communicatie: @count', ['@count' => $pendingScopes]), Url::fromRoute('brebo_resident_service.address_scopes'))->toRenderable(),
      ]],
      'cases' => ['#type' => 'table', '#header' => ['Nummer', 'Type', 'Adres', 'Onderwerp', 'Prioriteit', 'Status', 'Deadline'], '#rows' => $rows, '#empty' => $this->t('Geen open bewonersdossiers.')],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function addressScopes(): array {
    $query = $this->database->select('brebo_address_scope_intake', 'i')->fields('i', ['id', 'source_type', 'source_id', 'matched_text', 'city', 'result_count', 'building_nid', 'project_id', 'status', 'created']);
    $query->orderBy('created', 'DESC')->range(0, 100);
    $rows = [];
    foreach ($query->execute() as $scope) {
      $action = '—';
      if ($scope->status === 'resolved') {
        $action = Link::fromTextAndUrl($this->t('Toevoegen aan gebouw'), Url::fromRoute('brebo_resident_service.address_scope_materialize', ['intake_id' => $scope->id]))->toRenderable();
      }
      elseif ($scope->status === 'materialized') {
        $action = $this->t('Toegevoegd');
      }
      $source = $scope->source_type . ($scope->source_id ? ' #' . $scope->source_id : '');
      $rows[] = [$source, $scope->matched_text, $scope->city ?: '—', (int) $scope->result_count, $scope->building_nid ?: '—', $scope->project_id ?: '—', ucfirst((string) $scope->status), date('d-m-Y H:i', (int) $scope->created), $action];
    }
    return [
      'intro' => ['#markup' => '<p>Adres- en huisnummerranges die automatisch uit communicatie zijn herkend en tegen PDOK/BAG zijn gevalideerd. Alleen bevestigde voorstellen worden als gebouwadressen en woningen opgeslagen.</p>'],
      'table' => ['#type' => 'table', '#header' => ['Bron', 'Gevonden scope', 'Plaats', 'BAG-adressen', 'Gebouw', 'Project', 'Status', 'Gevonden', 'Actie'], '#rows' => $rows, '#empty' => $this->t('Nog geen adresvoorstellen gevonden.')],
      '#cache' => ['max-age' => 0],
    ];
  }
}
