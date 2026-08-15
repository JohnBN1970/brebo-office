<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
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

    $query = $this->database->select('brebo_resident_case', 'c');
    $query->leftJoin('brebo_residence', 'r', 'r.id = c.residence_id');
    $query->fields('c', ['case_number', 'case_type', 'title', 'priority', 'status', 'due_at']);
    $query->addField('r', 'address_line');
    $query->condition('c.status', ['closed', 'cancelled'], 'NOT IN');
    $query->orderBy('c.due_at', 'ASC');
    $query->orderBy('c.reported_at', 'ASC');
    $query->range(0, 50);

    $rows = [];
    foreach ($query->execute() as $case) {
      $due = $case->due_at ? date('d-m-Y H:i', (int) $case->due_at) : '—';
      $rows[] = [
        $case->case_number,
        ucfirst((string) $case->case_type),
        $case->address_line ?: '—',
        $case->title,
        ucfirst((string) $case->priority),
        ucfirst((string) $case->status),
        $due,
      ];
    }

    return [
      'intro' => ['#markup' => '<p>Centraal overzicht van bewonersmeldingen, klachten, schade, toegang en nazorg.</p>'],
      'summary' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Open dossiers: @count', ['@count' => $open]),
          $this->t('Te laat: @count', ['@count' => $overdue]),
          $this->t('Open klachten: @count', ['@count' => $complaints]),
          $this->t('Toegangsproblemen: @count', ['@count' => $access]),
        ],
      ],
      'cases' => [
        '#type' => 'table',
        '#header' => ['Nummer', 'Type', 'Adres', 'Onderwerp', 'Prioriteit', 'Status', 'Deadline'],
        '#rows' => $rows,
        '#empty' => $this->t('Geen open bewonersdossiers.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
