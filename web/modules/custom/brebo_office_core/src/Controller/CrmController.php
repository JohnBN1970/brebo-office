<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Provides the canonical CRM organization dossier. */
final class CrmController extends ControllerBase {

  public function overview(Request $request): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $search = trim((string) $request->query->get('q', ''));

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

    $organizationQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_organization');
    $contactQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact');

    if ($search !== '') {
      $organizationMatch = $organizationQuery->orConditionGroup()
        ->condition('title', $search, 'CONTAINS')
        ->condition('field_brebo_org_email', $search, 'CONTAINS');
      $organizationQuery->condition($organizationMatch)
        ->sort('title')
        ->range(0, 50);

      $contactMatch = $contactQuery->orConditionGroup()
        ->condition('title', $search, 'CONTAINS')
        ->condition('field_brebo_contact_email', $search, 'CONTAINS');
      $contactQuery->condition($contactMatch)
        ->sort('title')
        ->range(0, 50);
    }
    else {
      $organizationQuery->sort('changed', 'DESC')->range(0, 10);
      $contactQuery->sort('changed', 'DESC')->range(0, 10);
    }

    $organizationIds = $organizationQuery->execute();
    $contactIds = $contactQuery->execute();

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
        ['data' => $this->emailLink($organization, 'field_brebo_org_email')],
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
        ['data' => $this->emailLink($contact, 'field_brebo_contact_email')],
      ];
    }

    return [
      'search' => [
        '#type' => 'html_tag',
        '#tag' => 'form',
        '#attributes' => [
          'method' => 'get',
          'action' => Url::fromRoute('brebo_office_core.crm')->toString(),
          'class' => ['brebo-list-actions'],
          'role' => 'search',
        ],
        'query' => [
          '#type' => 'html_tag',
          '#tag' => 'input',
          '#attributes' => [
            'type' => 'search',
            'name' => 'q',
            'value' => $search,
            'placeholder' => $this->t('Zoek op naam of e-mailadres'),
            'aria-label' => $this->t('Relaties zoeken'),
          ],
        ],
        'submit' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Zoeken'),
          '#attributes' => ['type' => 'submit', 'class' => ['button']],
        ],
        'clear' => $search !== '' ? [
          '#type' => 'link',
          '#title' => $this->t('Wissen'),
          '#url' => Url::fromRoute('brebo_office_core.crm'),
          '#attributes' => ['class' => ['button']],
        ] : [],
      ],
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
        $search !== '' ? $this->t('Gevonden organisaties') : $this->t('Recent gewijzigde organisaties'),
        [$this->t('Organisatie'), $this->t('Type'), $this->t('Status'), $this->t('E-mail')],
        $organizationRows,
        $search !== '' ? $this->t('Geen organisaties gevonden.') : $this->t('Nog geen organisaties aangemaakt.')
      ),
      'contacts' => $this->section(
        $search !== '' ? $this->t('Gevonden contactpersonen') : $this->t('Recent gewijzigde contactpersonen'),
        [$this->t('Contactpersoon'), $this->t('Organisatie'), $this->t('Rol'), $this->t('E-mail')],
        $contactRows,
        $search !== '' ? $this->t('Geen contactpersonen gevonden.') : $this->t('Nog geen contactpersonen aangemaakt.')
      ),
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args:q'],
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
    $legacyOrganization = $node->hasField('field_brebo_org_ref')
      ? $node->get('field_brebo_org_ref')->entity
      : NULL;

    $affiliationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact_affiliation')
      ->condition('field_brebo_aff_contact_ref.target_id', $node->id())
      ->sort('field_brebo_aff_primary', 'DESC')
      ->sort('changed', 'DESC')
      ->execute();
    $affiliations = $storage->loadMultiple($affiliationIds);

    $organizations = [];
    $primaryOrganization = NULL;
    foreach ($affiliations as $affiliation) {
      if (!$affiliation instanceof NodeInterface) {
        continue;
      }
      $affiliatedOrganization = $affiliation->get('field_brebo_aff_org_ref')->entity;
      if (!$affiliatedOrganization instanceof NodeInterface || $affiliatedOrganization->bundle() !== 'brebo_organization') {
        continue;
      }
      $organizations[(int) $affiliatedOrganization->id()] = $affiliatedOrganization;
      if (!$primaryOrganization instanceof NodeInterface && (bool) $affiliation->get('field_brebo_aff_primary')->value) {
        $primaryOrganization = $affiliatedOrganization;
      }
    }
    if ($organizations === [] && $legacyOrganization instanceof NodeInterface && $legacyOrganization->bundle() === 'brebo_organization') {
      $organizations[(int) $legacyOrganization->id()] = $legacyOrganization;
      $primaryOrganization = $legacyOrganization;
    }
    if (!$primaryOrganization instanceof NodeInterface && $organizations !== []) {
      $primaryOrganization = reset($organizations);
    }

    $contactPointIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact_point')
      ->condition('field_brebo_cp_contact_ref.target_id', $node->id())
      ->sort('field_brebo_cp_primary', 'DESC')
      ->sort('changed', 'DESC')
      ->execute();
    $contactPoints = $storage->loadMultiple($contactPointIds);

    $projects = [];
    $buildings = [];
    if ($organizations !== []) {
      $projectIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_project')
        ->condition('field_brebo_client_org_ref.target_id', array_keys($organizations), 'IN')
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

    $organizationItems = [];
    foreach ($organizations as $organization) {
      $organizationItems[] = Link::fromTextAndUrl(
        $organization->label(),
        Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
      )->toRenderable();
    }
    $organizationsCell = $organizationItems !== [] ? [
      '#theme' => 'item_list',
      '#items' => $organizationItems,
    ] : '—';

    $affiliationRows = [];
    foreach ($affiliations as $affiliation) {
      if (!$affiliation instanceof NodeInterface) {
        continue;
      }
      $affiliatedOrganization = $affiliation->get('field_brebo_aff_org_ref')->entity;
      $organizationLink = '—';
      if ($affiliatedOrganization instanceof NodeInterface) {
        $organizationLink = Link::fromTextAndUrl(
          $affiliatedOrganization->label(),
          Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $affiliatedOrganization->id()])
        )->toRenderable();
      }
      $affiliationRows[] = [
        ['data' => $organizationLink],
        $this->value($affiliation, 'field_brebo_aff_role'),
        $this->value($affiliation, 'field_brebo_aff_department'),
        $this->value($affiliation, 'field_brebo_aff_status'),
        $affiliation->get('field_brebo_aff_primary')->value ? $this->t('Ja') : $this->t('Nee'),
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $affiliation->id()]))->toRenderable()],
      ];
    }

    $contactPointRows = [];
    foreach ($contactPoints as $contactPoint) {
      if (!$contactPoint instanceof NodeInterface) {
        continue;
      }
      $affiliation = $contactPoint->get('field_brebo_cp_affiliation_ref')->entity;
      $context = $this->t('Algemeen / privé');
      if ($affiliation instanceof NodeInterface) {
        $affiliatedOrganization = $affiliation->get('field_brebo_aff_org_ref')->entity;
        if ($affiliatedOrganization instanceof NodeInterface) {
          $context = $affiliatedOrganization->label();
        }
      }
      $contactPointRows[] = [
        $this->value($contactPoint, 'field_brebo_cp_channel'),
        $this->value($contactPoint, 'field_brebo_cp_label'),
        $this->value($contactPoint, 'field_brebo_cp_value'),
        $context,
        $contactPoint->get('field_brebo_cp_primary')->value ? $this->t('Ja') : $this->t('Nee'),
        $contactPoint->get('field_brebo_cp_active')->value ? $this->t('Actief') : $this->t('Inactief'),
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $contactPoint->id()]))->toRenderable()],
      ];
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
        'affiliation' => [
          '#type' => 'link',
          '#title' => $this->t('Contactrelatie toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact_affiliation'], [
            'query' => ['contact' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'contact_point' => [
          '#type' => 'link',
          '#title' => $this->t('Contactgegeven toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact_point'], [
            'query' => ['contact' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'communication' => [
          '#type' => 'link',
          '#title' => $this->t('Communicatie vastleggen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_communication'], [
            'query' => array_filter([
              'contact' => $node->id(),
              'organization' => $primaryOrganization instanceof NodeInterface ? $primaryOrganization->id() : NULL,
            ]),
          ]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Status'), $this->t('Organisaties'), $this->t('Projecten via organisaties'), $this->t('Gebouwen via projecten'), $this->t('Communicatie')],
        '#rows' => [[
          $node->hasField('field_brebo_contact_active') && (bool) $node->get('field_brebo_contact_active')->value ? $this->t('Actief') : $this->t('Inactief'),
          ['data' => $organizationsCell],
          count($projects),
          count($buildings),
          count($communications),
        ]],
      ],
      'details' => [
        '#type' => 'details',
        '#title' => $this->t('Oude basisgegevens (overgang)'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#rows' => [
            [$this->t('Rol/functie'), $this->value($node, 'field_brebo_contact_role')],
            [$this->t('E-mail'), ['data' => $this->emailLink($node, 'field_brebo_contact_email')]],
            [$this->t('Telefoon'), ['data' => $this->phoneLink($node, 'field_brebo_contact_phone')]],
            [$this->t('Voorkeurskanaal'), $this->value($node, 'field_brebo_contact_channel')],
          ],
        ],
      ],
      'affiliations' => $this->section(
        $this->t('Contactrelaties'),
        [$this->t('Organisatie'), $this->t('Rol/functie'), $this->t('Afdeling'), $this->t('Status'), $this->t('Primair'), $this->t('Actie')],
        $affiliationRows,
        $this->t('Nog geen contactrelaties vastgelegd.')
      ),
      'contact_points' => $this->section(
        $this->t('Contactgegevens'),
        [$this->t('Kanaal'), $this->t('Label'), $this->t('Waarde'), $this->t('Context'), $this->t('Primair'), $this->t('Status'), $this->t('Actie')],
        $contactPointRows,
        $this->t('Nog geen contactgegevens vastgelegd.')
      ),
      'projects' => $this->section($this->t('Projecten via organisaties'), [$this->t('Project'), $this->t('Status'), $this->t('Soort')], $projectRows, $this->t('Geen projecten via de organisaties gevonden.')),
      'buildings' => $this->section($this->t('Gebouwen via projecten'), [$this->t('Gebouw'), $this->t('Adres'), $this->t('Status')], $buildingRows, $this->t('Geen gebouwen via projecten gevonden.')),
      'communications' => $this->section($this->t('Laatste communicatie met deze contactpersoon'), [$this->t('Onderwerp'), $this->t('Datum'), $this->t('Richting'), $this->t('Status')], $communicationRows, $this->t('Nog geen communicatie gekoppeld.')),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), [
          'node_list:brebo_organization',
          'node_list:brebo_contact_affiliation',
          'node_list:brebo_contact_point',
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

    $locationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_organization_location')
      ->condition('field_brebo_loc_org_ref.target_id', $node->id())
      ->sort('field_brebo_loc_primary', 'DESC')
      ->sort('field_brebo_loc_active', 'DESC')
      ->sort('changed', 'DESC')
      ->execute();
    $locations = $storage->loadMultiple($locationIds);

    $affiliationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_contact_affiliation')
      ->condition('field_brebo_aff_org_ref.target_id', $node->id())
      ->sort('field_brebo_aff_primary', 'DESC')
      ->sort('changed', 'DESC')
      ->execute();
    $affiliations = $storage->loadMultiple($affiliationIds);

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
    foreach ($affiliations as $affiliation) {
      if (!$affiliation instanceof NodeInterface) {
        continue;
      }
      $contact = $affiliation->get('field_brebo_aff_contact_ref')->entity;
      if (!$contact instanceof NodeInterface || $contact->bundle() !== 'brebo_contact') {
        continue;
      }
      $contactRows[] = [
        ['data' => Link::fromTextAndUrl(
          $contact->label(),
          Url::fromRoute('brebo_office_core.contact_dashboard', ['node' => $contact->id()])
        )->toRenderable()],
        $this->value($affiliation, 'field_brebo_aff_role'),
        $this->value($affiliation, 'field_brebo_aff_department'),
        $this->value($affiliation, 'field_brebo_aff_status'),
        $affiliation->get('field_brebo_aff_primary')->value ? $this->t('Ja') : $this->t('Nee'),
        ['data' => Link::fromTextAndUrl($this->t('Relatie bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $affiliation->id()]))->toRenderable()],
      ];
    }

    $locationRows = [];
    foreach ($locations as $location) {
      if (!$location instanceof NodeInterface) {
        continue;
      }
      $addressParts = [];
      foreach (['field_brebo_loc_address', 'field_brebo_loc_postal_code', 'field_brebo_loc_city', 'field_brebo_loc_country'] as $addressField) {
        $addressValue = $this->value($location, $addressField);
        if ($addressValue !== '—') {
          $addressParts[] = $addressValue;
        }
      }
      $locationRows[] = [
        $this->value($location, 'field_brebo_loc_type'),
        $this->value($location, 'field_brebo_loc_name'),
        $addressParts !== [] ? implode(', ', $addressParts) : '—',
        $location->get('field_brebo_loc_primary')->value ? $this->t('Ja') : $this->t('Nee'),
        $location->get('field_brebo_loc_active')->value ? $this->t('Ja') : $this->t('Nee'),
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $location->id()]))->toRenderable()],
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
        'location' => [
          '#type' => 'link',
          '#title' => $this->t('Locatie toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_organization_location'], [
            'query' => ['organization' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'communication' => [
          '#type' => 'link',
          '#title' => $this->t('Communicatie vastleggen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_communication'], [
            'query' => ['organization' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'contact' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuwe contactpersoon'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact'], [
            'query' => ['organization' => $node->id()],
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'affiliation' => [
          '#type' => 'link',
          '#title' => $this->t('Bestaande persoon koppelen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact_affiliation'], [
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
        '#header' => [$this->t('Actief'), $this->t('Klant'), $this->t('Leverancier'), $this->t('Locaties'), $this->t('Contactrelaties'), $this->t('Projecten'), $this->t('Gebouwen'), $this->t('Communicatie')],
        '#rows' => [[
          $node->hasField('field_brebo_org_active') && (bool) $node->get('field_brebo_org_active')->value ? $this->t('Ja') : $this->t('Nee'),
          $node->hasField('field_brebo_org_customer') && (bool) $node->get('field_brebo_org_customer')->value ? $this->t('Ja') : $this->t('Nee'),
          $node->hasField('field_brebo_org_supplier') && (bool) $node->get('field_brebo_org_supplier')->value ? $this->t('Ja') : $this->t('Nee'),
          count($locations),
          count($affiliations),
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
            [$this->t('KvK-nummer'), $this->value($node, 'field_brebo_org_kvk')],
            [$this->t('Btw-identificatienummer'), $this->value($node, 'field_brebo_org_vat')],
            [$this->t('Betaaltermijn'), $this->value($node, 'field_brebo_org_payment_days') !== '—' ? $this->t('@days dagen', ['@days' => $this->value($node, 'field_brebo_org_payment_days')]) : '—'],
            [$this->t('Oud KvK-/relatienummer (overgang)'), $this->value($node, 'field_brebo_org_number')],
            [$this->t('E-mail'), ['data' => $this->emailLink($node, 'field_brebo_org_email')]],
            [$this->t('Telefoon'), ['data' => $this->phoneLink($node, 'field_brebo_org_phone')]],
            [$this->t('Oud adres (overgang)'), $this->value($node, 'field_brebo_org_address')],
          ],
        ],
      ],
      'locations' => $this->section(
        $this->t('Locaties'),
        [$this->t('Type'), $this->t('Naam'), $this->t('Adres'), $this->t('Primair'), $this->t('Actief'), $this->t('Actie')],
        $locationRows,
        $this->t('Nog geen locaties aan deze organisatie gekoppeld.')
      ),
      'contacts' => $this->section($this->t('Contactrelaties'), [$this->t('Persoon'), $this->t('Rol/functie'), $this->t('Afdeling'), $this->t('Status'), $this->t('Primair'), $this->t('Actie')], $contactRows, $this->t('Nog geen personen aan deze organisatie gekoppeld.')),
      'projects' => $this->section($this->t('Projecten'), [$this->t('Project'), $this->t('Status'), $this->t('Gebouw'), $this->t('Soort')], $projectRows, $this->t('Nog geen projecten gekoppeld.')),
      'buildings' => $this->section($this->t('Gebouwen via projecten'), [$this->t('Gebouw'), $this->t('Adres'), $this->t('Status')], $buildingRows, $this->t('Nog geen gebouwen via projecten gevonden.')),
      'communications' => $this->section($this->t('Laatste communicatie'), [$this->t('Onderwerp'), $this->t('Datum'), $this->t('Richting'), $this->t('Status')], $communicationRows, $this->t('Nog geen communicatie gekoppeld.')),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), [
          'node_list:brebo_contact',
          'node_list:brebo_organization_location',
          'node_list:brebo_contact_affiliation',
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

  private function emailLink(NodeInterface $node, string $field): mixed {
    $value = $this->value($node, $field);
    if ($value === '—' || filter_var($value, FILTER_VALIDATE_EMAIL) === FALSE) {
      return $value;
    }

    return Link::fromTextAndUrl($value, Url::fromUri('mailto:' . $value))->toRenderable();
  }

  private function phoneLink(NodeInterface $node, string $field): mixed {
    $value = $this->value($node, $field);
    if ($value === '—') {
      return $value;
    }

    $telephone = preg_replace('/[^0-9+]/', '', $value);
    if (!is_string($telephone) || $telephone === '') {
      return $value;
    }

    return Link::fromTextAndUrl($value, Url::fromUri('tel:' . $telephone))->toRenderable();
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
