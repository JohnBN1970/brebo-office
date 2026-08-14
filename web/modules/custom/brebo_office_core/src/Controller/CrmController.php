<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Provides the canonical CRM organization dossier. */
final class CrmController extends ControllerBase {

  public function overview(): array {
    $storage = $this->entityTypeManager()->getStorage('node');

    $organizationCount = (int) $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_organization')
      ->count()
      ->execute();
    $activeOrganizationCount = (int) $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_organization')
      ->condition('field_brebo_org_status', 'Actief')
      ->count()
      ->execute();
    $contactCount = (int) $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact')
      ->count()
      ->execute();
    $activeContactCount = (int) $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact')
      ->condition('field_brebo_contact_active', 1)
      ->count()
      ->execute();

    $organizationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_organization')
      ->sort('changed', 'DESC')
      ->range(0, 10)
      ->execute();
    $contactIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact')
      ->sort('changed', 'DESC')
      ->range(0, 10)
      ->execute();

    $organizationRows = [];
    foreach ($storage->loadMultiple($organizationIds) as $organization) {
      if (!$organization instanceof NodeInterface) {
        continue;
      }
      $organizationRows[] = [
        ['data' => Link::fromTextAndUrl(
          $organization->label(),
          Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
        )->toRenderable()],
        $this->value($organization, 'field_brebo_org_type'),
        $this->value($organization, 'field_brebo_org_status'),
        $this->value($organization, 'field_brebo_org_email'),
      ];
    }

    $contactRows = [];
    foreach ($storage->loadMultiple($contactIds) as $contact) {
      if (!$contact instanceof NodeInterface) {
        continue;
      }
      $organization = $contact->hasField('field_brebo_org_ref') ? $contact->get('field_brebo_org_ref')->entity : NULL;
      $organizationLabel = '—';
      if ($organization instanceof NodeInterface && $organization->bundle() === 'brebo_organization') {
        $organizationLabel = Link::fromTextAndUrl(
          $organization->label(),
          Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
        )->toRenderable();
      }
      $contactRows[] = [
        ['data' => Link::fromTextAndUrl(
          $contact->label(),
          Url::fromRoute('brebo_office_core.contact_dashboard', ['node' => $contact->id()])
        )->toRenderable()],
        ['data' => $organizationLabel],
        $this->value($contact, 'field_brebo_contact_role'),
        $this->value($contact, 'field_brebo_contact_email'),
      ];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'organizations' => [
          '#type' => 'link',
          '#title' => $this->t('Alle organisaties'),
          '#url' => Url::fromRoute('brebo_office_core.organizations'),
          '#attributes' => ['class' => ['button']],
        ],
        'contacts' => [
          '#type' => 'link',
          '#title' => $this->t('Alle contactpersonen'),
          '#url' => Url::fromRoute('brebo_office_core.contacts'),
          '#attributes' => ['class' => ['button']],
        ],
        'add_organization' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuwe organisatie'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_organization']),
          '#attributes' => ['class' => ['button']],
        ],
        'add_contact' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuwe contactpersoon'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact']),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Organisaties'), $this->t('Actieve organisaties'), $this->t('Contactpersonen'), $this->t('Actieve contactpersonen')],
        '#rows' => [[$organizationCount, $activeOrganizationCount, $contactCount, $activeContactCount]],
      ],
      'organizations' => $this->section(
        $this->t('Recent gewijzigde organisaties'),
        [$this->t('Organisatie'), $this->t('Type'), $this->t('Status'), $this->t('E-mail')],
        $organizationRows,
        $this->t('Nog geen organisaties aangemaakt.')
      ),
      'contacts' => $this->section(
        $this->t('Recent gewijzigde contactpersonen'),
        [$this->t('Contactpersoon'), $this->t('Organisatie'), $this->t('Rol'), $this->t('E-mail')],
        $contactRows,
        $this->t('Nog geen contactpersonen aangemaakt.')
      ),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:brebo_organization', 'node_list:brebo_contact'],
      ],
    ];
  }

  public function contactTitle(NodeInterface $node): string {
    $this->assertContact($node);
    return (string) $node->label();
  }

  public function contact(NodeInterface $node): array {
    $this->assertContact($node);
    $storage = $this->entityTypeManager()->getStorage('node');
    $organization = $node->hasField('field_brebo_org_ref')
      ? $node->get('field_brebo_org_ref')->entity
      : NULL;

    $projects = [];
    $buildings = [];
    if ($organization instanceof NodeInterface && $organization->bundle() === 'brebo_organization') {
      $projectIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_project')
        ->condition('field_brebo_client_org_ref.target_id', $organization->id())
        ->sort('changed', 'DESC')
        ->execute();
      $projects = $storage->loadMultiple($projectIds);
      foreach ($projects as $project) {
        if (!$project instanceof NodeInterface || !$project->hasField('field_brebo_building_ref')) {
          continue;
        }
        foreach ($project->get('field_brebo_building_ref')->referencedEntities() as $building) {
          if ($building instanceof NodeInterface && $building->bundle() === 'brebo_building') {
            $buildings[(int) $building->id()] = $building;
          }
        }
      }
    }

    $communicationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_communication')
      ->condition('field_brebo_comm_contact_ref.target_id', $node->id())
      ->sort('field_brebo_comm_datetime', 'DESC')
      ->range(0, 25)
      ->execute();
    $communications = $storage->loadMultiple($communicationIds);

    $projectRows = [];
    foreach ($projects as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }
      $projectRows[] = [
        ['data' => Link::fromTextAndUrl(
          $project->label(),
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project->id()])
        )->toRenderable()],
        $this->value($project, 'field_brebo_project_status'),
        $this->value($project, 'field_brebo_project_kind'),
      ];
    }

    $buildingRows = [];
    foreach ($buildings as $building) {
      $buildingRows[] = [
        ['data' => Link::fromTextAndUrl(
          $building->label(),
          Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()])
        )->toRenderable()],
        $this->value($building, 'field_brebo_address'),
        $this->value($building, 'field_brebo_status'),
      ];
    }

    $communicationRows = [];
    foreach ($communications as $communication) {
      if (!$communication instanceof NodeInterface) {
        continue;
      }
      $communicationRows[] = [
        ['data' => Link::fromTextAndUrl($communication->label(), $communication->toUrl())->toRenderable()],
        $this->value($communication, 'field_brebo_comm_datetime'),
        $this->value($communication, 'field_brebo_comm_direction'),
        $this->value($communication, 'field_brebo_comm_status'),
      ];
    }

    $organizationCell = '—';
    if ($organization instanceof NodeInterface && $organization->bundle() === 'brebo_organization') {
      $organizationCell = Link::fromTextAndUrl(
        $organization->label(),
        Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
      )->toRenderable();
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Contactpersoon bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Status'), $this->t('Organisatie'), $this->t('Projecten via organisatie'), $this->t('Gebouwen via projecten'), $this->t('Communicatie')],
        '#rows' => [[
          $node->hasField('field_brebo_contact_active') && (bool) $node->get('field_brebo_contact_active')->value ? $this->t('Actief') : $this->t('Inactief'),
          ['data' => $organizationCell],
          count($projects),
          count($buildings),
          count($communications),
        ]],
      ],
      'details' => [
        '#type' => 'details',
        '#title' => $this->t('Contactgegevens'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#rows' => [
            [$this->t('Rol/functie'), $this->value($node, 'field_brebo_contact_role')],
            [$this->t('E-mail'), $this->value($node, 'field_brebo_contact_email')],
            [$this->t('Telefoon'), $this->value($node, 'field_brebo_contact_phone')],
            [$this->t('Voorkeurskanaal'), $this->value($node, 'field_brebo_contact_channel')],
          ],
        ],
      ],
      'projects' => $this->section($this->t('Projecten via organisatie'), [$this->t('Project'), $this->t('Status'), $this->t('Soort')], $projectRows, $this->t('Geen projecten via de organisatie gevonden.')),
      'buildings' => $this->section($this->t('Gebouwen via projecten'), [$this->t('Gebouw'), $this->t('Adres'), $this->t('Status')], $buildingRows, $this->t('Geen gebouwen via projecten gevonden.')),
      'communications' => $this->section($this->t('Laatste communicatie met deze contactpersoon'), [$this->t('Onderwerp'), $this->t('Datum'), $this->t('Richting'), $this->t('Status')], $communicationRows, $this->t('Nog geen communicatie gekoppeld.')),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), [
          'node_list:brebo_organization',
          'node_list:brebo_project',
          'node_list:brebo_building',
          'node_list:brebo_communication',
        ]),
      ],
    ];
  }

  public function organizationTitle(NodeInterface $node): string {
    $this->assertOrganization($node);
    return (string) $node->label();
  }

  public function organization(NodeInterface $node): array {
    $this->assertOrganization($node);
    $storage = $this->entityTypeManager()->getStorage('node');

    $contactIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact')
      ->condition('field_brebo_org_ref.target_id', $node->id())
      ->sort('title')
      ->execute();
    $contacts = $storage->loadMultiple($contactIds);

    $projectIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->condition('field_brebo_client_org_ref.target_id', $node->id())
      ->sort('changed', 'DESC')
      ->execute();
    $projects = $storage->loadMultiple($projectIds);

    $communicationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_communication')
      ->condition('field_brebo_comm_org_ref.target_id', $node->id())
      ->sort('field_brebo_comm_datetime', 'DESC')
      ->range(0, 25)
      ->execute();
    $communications = $storage->loadMultiple($communicationIds);

    $buildings = [];
    foreach ($projects as $project) {
      if (!$project instanceof NodeInterface || !$project->hasField('field_brebo_building_ref')) {
        continue;
      }
      foreach ($project->get('field_brebo_building_ref')->referencedEntities() as $building) {
        if ($building instanceof NodeInterface && $building->bundle() === 'brebo_building') {
          $buildings[(int) $building->id()] = $building;
        }
      }
    }

    $contactRows = [];
    foreach ($contacts as $contact) {
      if (!$contact instanceof NodeInterface) {
        continue;
      }
      $contactRows[] = [
        ['data' => Link::fromTextAndUrl(
          $contact->label(),
          Url::fromRoute('brebo_office_core.contact_dashboard', ['node' => $contact->id()])
        )->toRenderable()],
        $this->value($contact, 'field_brebo_contact_role'),
        $this->value($contact, 'field_brebo_contact_email'),
        $this->value($contact, 'field_brebo_contact_phone'),
        $contact->hasField('field_brebo_contact_active') && (bool) $contact->get('field_brebo_contact_active')->value
          ? $this->t('Actief')
          : $this->t('Inactief'),
      ];
    }

    $projectRows = [];
    foreach ($projects as $project) {
      if (!$project instanceof NodeInterface) {
        continue;
      }
      $buildingLabels = [];
      if ($project->hasField('field_brebo_building_ref')) {
        foreach ($project->get('field_brebo_building_ref')->referencedEntities() as $building) {
          $buildingLabels[] = $building->label();
        }
      }
      $projectRows[] = [
        ['data' => Link::fromTextAndUrl(
          $project->label(),
          Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project->id()])
        )->toRenderable()],
        $this->value($project, 'field_brebo_project_status'),
        $buildingLabels !== [] ? implode(', ', $buildingLabels) : '—',
        $this->value($project, 'field_brebo_project_kind'),
      ];
    }

    $buildingRows = [];
    foreach ($buildings as $building) {
      $buildingRows[] = [
        ['data' => Link::fromTextAndUrl(
          $building->label(),
          Url::fromRoute('brebo_office_core.building_dashboard', ['node' => $building->id()])
        )->toRenderable()],
        $this->value($building, 'field_brebo_address'),
        $this->value($building, 'field_brebo_status'),
      ];
    }

    $communicationRows = [];
    foreach ($communications as $communication) {
      if (!$communication instanceof NodeInterface) {
        continue;
      }
      $communicationRows[] = [
        ['data' => Link::fromTextAndUrl($communication->label(), $communication->toUrl())->toRenderable()],
        $this->value($communication, 'field_brebo_comm_datetime'),
        $this->value($communication, 'field_brebo_comm_direction'),
        $this->value($communication, 'field_brebo_comm_status'),
      ];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Relatie bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'contact' => [
          '#type' => 'link',
          '#title' => $this->t('Contactpersoon toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact'], [
            'query' => ['organization' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'project' => [
          '#type' => 'link',
          '#title' => $this->t('Project toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project'], [
            'query' => ['organization' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Status'), $this->t('Type'), $this->t('Contactpersonen'), $this->t('Projecten'), $this->t('Gebouwen'), $this->t('Communicatie')],
        '#rows' => [[
          $this->value($node, 'field_brebo_org_status'),
          $this->value($node, 'field_brebo_org_type'),
          count($contacts),
          count($projects),
          count($buildings),
          count($communications),
        ]],
      ],
      'details' => [
        '#type' => 'details',
        '#title' => $this->t('Relatiegegevens'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#rows' => [
            [$this->t('Relatienummer'), $this->value($node, 'field_brebo_org_number')],
            [$this->t('E-mail'), $this->value($node, 'field_brebo_org_email')],
            [$this->t('Telefoon'), $this->value($node, 'field_brebo_org_phone')],
            [$this->t('Adres'), $this->value($node, 'field_brebo_org_address')],
          ],
        ],
      ],
      'contacts' => $this->section($this->t('Contactpersonen'), [$this->t('Naam'), $this->t('Rol'), $this->t('E-mail'), $this->t('Telefoon'), $this->t('Status')], $contactRows, $this->t('Nog geen contactpersonen gekoppeld.')),
      'projects' => $this->section($this->t('Projecten'), [$this->t('Project'), $this->t('Status'), $this->t('Gebouw'), $this->t('Soort')], $projectRows, $this->t('Nog geen projecten gekoppeld.')),
      'buildings' => $this->section($this->t('Gebouwen via projecten'), [$this->t('Gebouw'), $this->t('Adres'), $this->t('Status')], $buildingRows, $this->t('Nog geen gebouwen via projecten gevonden.')),
      'communications' => $this->section($this->t('Laatste communicatie'), [$this->t('Onderwerp'), $this->t('Datum'), $this->t('Richting'), $this->t('Status')], $communicationRows, $this->t('Nog geen communicatie gekoppeld.')),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), [
          'node_list:brebo_contact',
          'node_list:brebo_project',
          'node_list:brebo_building',
          'node_list:brebo_communication',
        ]),
      ],
    ];
  }

  private function section(mixed $title, array $header, array $rows, mixed $empty): array {
    return [
      '#type' => 'details',
      '#title' => $title,
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $empty,
      ],
    ];
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    $value = trim((string) $node->get($field)->value);
    return $value !== '' ? $value : '—';
  }

  private function assertContact(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_contact') {
      throw new NotFoundHttpException();
    }
  }

  private function assertOrganization(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_organization') {
      throw new NotFoundHttpException();
    }
  }

}
