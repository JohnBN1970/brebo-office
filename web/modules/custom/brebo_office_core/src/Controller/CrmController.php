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
      ->condition('field_brebo_org_active', 1)
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
        ->condition('field_brebo_org_email', $search, 'CONTAINS')
        ->condition('field_brebo_org_kvk', $search, 'CONTAINS')
        ->condition('field_brebo_org_vat', $search, 'CONTAINS');
      $organizationQuery->condition($organizationMatch)
        ->sort('title')
        ->range(0, 50);

      $contactPointIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_contact_point')
        ->condition('field_brebo_cp_value', $search, 'CONTAINS')
        ->execute();
      $matchedContactIds = [];
      foreach ($storage->loadMultiple($contactPointIds) as $contactPoint) {
        if ($contactPoint instanceof NodeInterface) {
          $matchedContact = $contactPoint->get('field_brebo_cp_contact_ref')->entity;
          if ($matchedContact instanceof NodeInterface) {
            $matchedContactIds[(int) $matchedContact->id()] = (int) $matchedContact->id();
          }
        }
      }

      $contactMatch = $contactQuery->orConditionGroup()
        ->condition('title', $search, 'CONTAINS')
        ->condition('field_brebo_contact_email', $search, 'CONTAINS');
      if ($matchedContactIds !== []) {
        $contactMatch->condition('nid', array_values($matchedContactIds), 'IN');
      }
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
      $accountOwner = $organization->hasField('field_brebo_org_account_owner')
        ? $organization->get('field_brebo_org_account_owner')->entity
        : NULL;
      $organizationRows[] = [
        ['data' => Link::fromTextAndUrl(
          $organization->label(),
          Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
        )->toRenderable()],
        $organization->hasField('field_brebo_org_customer') && (bool) $organization->get('field_brebo_org_customer')->value ? $this->t('Ja') : $this->t('Nee'),
        $organization->hasField('field_brebo_org_supplier') && (bool) $organization->get('field_brebo_org_supplier')->value ? $this->t('Ja') : $this->t('Nee'),
        $accountOwner !== NULL ? $accountOwner->label() : '—',
        $organization->hasField('field_brebo_org_active') && (bool) $organization->get('field_brebo_org_active')->value ? $this->t('Ja') : $this->t('Nee'),
        ['data' => $this->emailLink($organization, 'field_brebo_org_email')],
      ];
    }

    $contactRows = [];
    foreach ($storage->loadMultiple($contactIds) as $contact) {
      if (!$contact instanceof NodeInterface) {
        continue;
      }
      $affiliationIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_contact_affiliation')
        ->condition('field_brebo_aff_contact_ref.target_id', $contact->id())
        ->sort('field_brebo_aff_primary', 'DESC')
        ->execute();
      $organizationLinks = [];
      $roleLabels = [];
      foreach ($storage->loadMultiple($affiliationIds) as $affiliation) {
        if (!$affiliation instanceof NodeInterface) {
          continue;
        }
        $organization = $affiliation->get('field_brebo_aff_org_ref')->entity;
        if ($organization instanceof NodeInterface && $organization->bundle() === 'brebo_organization') {
          $organizationLinks[] = Link::fromTextAndUrl(
            $organization->label(),
            Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
          )->toRenderable();
        }
        $role = $this->value($affiliation, 'field_brebo_aff_role');
        if ($role !== '—') {
          $roleLabels[] = $role;
        }
      }
      if ($organizationLinks === []) {
        $legacyOrganization = $contact->hasField('field_brebo_org_ref') ? $contact->get('field_brebo_org_ref')->entity : NULL;
        if ($legacyOrganization instanceof NodeInterface && $legacyOrganization->bundle() === 'brebo_organization') {
          $organizationLinks[] = Link::fromTextAndUrl(
            $legacyOrganization->label(),
            Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $legacyOrganization->id()])
          )->toRenderable();
        }
      }
      $organizationsCell = $organizationLinks !== [] ? [
        '#theme' => 'item_list',
        '#items' => $organizationLinks,
      ] : '—';
      $contactRows[] = [
        ['data' => Link::fromTextAndUrl(
          $contact->label(),
          Url::fromRoute('brebo_office_core.contact_dashboard', ['node' => $contact->id()])
        )->toRenderable()],
        ['data' => $organizationsCell],
        $roleLabels !== [] ? implode(', ', array_unique($roleLabels)) : $this->value($contact, 'field_brebo_contact_role'),
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
            'placeholder' => $this->t('Zoek op naam, e-mail, KvK, btw of contactgegeven'),
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
        'funnel' => [
          '#type' => 'link',
          '#title' => $this->t('Commerciële funnel'),
          '#url' => Url::fromRoute('brebo_office_core.funnel'),
          '#attributes' => ['class' => ['button']],
        ],
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
        [$this->t('Organisatie'), $this->t('Klant'), $this->t('Leverancier'), $this->t('Account van'), $this->t('Actief'), $this->t('E-mail')],
        $organizationRows,
        $search !== '' ? $this->t('Geen organisaties gevonden.') : $this->t('Nog geen organisaties aangemaakt.')
      ),
      'contacts' => $this->section(
        $search !== '' ? $this->t('Gevonden contactpersonen') : $this->t('Recent gewijzigde contactpersonen'),
        [$this->t('Contactpersoon'), $this->t('Organisaties'), $this->t('Rollen'), $this->t('E-mail')],
        $contactRows,
        $search !== '' ? $this->t('Geen contactpersonen gevonden.') : $this->t('Nog geen contactpersonen aangemaakt.')
      ),
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args:q'],
        'tags' => ['node_list:brebo_organization', 'node_list:brebo_contact', 'node_list:brebo_contact_affiliation', 'node_list:brebo_contact_point'],
      ],
    ];
  }

  public function funnel(Request $request): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $mine = (bool) $request->query->get('mine', FALSE);
    $view = in_array($request->query->get('view'), ['list', 'kanban'], TRUE)
      ? (string) $request->query->get('view')
      : 'list';
    $group = in_array($request->query->get('group'), ['none', 'stage', 'organization', 'owner'], TRUE)
      ? (string) $request->query->get('group')
      : 'none';
    $sort = in_array($request->query->get('sort'), ['stage', 'stage_age', 'organization', 'value', 'probability', 'close_date', 'next_date', 'last_contact', 'owner'], TRUE)
      ? (string) $request->query->get('sort')
      : 'next_date';
    $direction = $request->query->get('direction') === 'desc' ? 'desc' : 'asc';

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_opportunity');
    if ($mine) {
      $query->condition('field_brebo_opp_owner.target_id', (int) $this->currentUser()->id());
    }
    $opportunities = $storage->loadMultiple($query->execute());

    $stages = [
      'Marketing lead', 'Lead', 'Kans', 'Afspraak', 'Calculatie/offerte',
      'Onderhandeling', 'Gewonnen', 'Verloren',
    ];
    $stageOrder = array_flip($stages);
    $stageTotals = array_fill_keys($stages, ['count' => 0, 'value' => 0.0, 'weighted' => 0.0, 'age_total' => 0, 'over_norm' => 0]);
    $stageLimits = [
      'Marketing lead' => 7, 'Lead' => 14, 'Kans' => 21, 'Afspraak' => 14,
      'Calculatie/offerte' => 21, 'Onderhandeling' => 14,
      'Gewonnen' => 0, 'Verloren' => 0,
    ];
    $entries = [];
    $totalValue = 0.0;
    $totalWeighted = 0.0;
    $openCount = 0;
    $overdue = 0;
    $todayCount = 0;
    $upcomingCount = 0;
    $staleCount = 0;
    $missingActionCount = 0;
    $unqualifiedCount = 0;
    $offerAttentionCount = 0;
    $stageOverdueCount = 0;
    $offerRows = [];
    $handoverRows = [];
    $handoverPendingCount = 0;
    $wonCount = 0;
    $lostCount = 0;
    $wonValue = 0.0;
    $lostValue = 0.0;
    $ownerTotals = [];
    $forecastTotals = [];
    $overdueCloseRows = [];
    $lossReasons = [];
    $sourceTotals = [];
    $managementRows = [];
    $today = date('Y-m-d', (int) \Drupal::time()->getCurrentTime());
    $weekEnd = date('Y-m-d', strtotime($today . ' +7 days'));
    $staleCutoff = date('Y-m-d', strtotime($today . ' -14 days'));

    foreach ($opportunities as $opportunity) {
      if (!$opportunity instanceof NodeInterface) {
        continue;
      }
      $organization = $opportunity->get('field_brebo_opp_org_ref')->entity;
      $owner = $opportunity->get('field_brebo_opp_owner')->entity;
      $stage = $this->value($opportunity, 'field_brebo_opp_stage');
      $value = (float) ($opportunity->get('field_brebo_opp_value')->value ?? 0);
      $probability = max(0, min(100, (int) ($opportunity->get('field_brebo_opp_probability')->value ?? 0)));
      $weighted = $value * $probability / 100;
      $active = (bool) $opportunity->get('field_brebo_opp_active')->value;
      $closeDate = $this->value($opportunity, 'field_brebo_opp_close_date');
      $nextDate = $this->value($opportunity, 'field_brebo_opp_next_date');
      $organizationLabel = $organization instanceof NodeInterface ? (string) $organization->label() : '—';
      $ownerLabel = $owner !== NULL ? (string) $owner->label() : '—';
      $lastContactIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_communication')
        ->condition('field_brebo_comm_opp_ref.target_id', $opportunity->id())
        ->condition('field_brebo_comm_direction', 'Intern vastgelegd', '<>')
        ->sort('field_brebo_comm_datetime', 'DESC')
        ->range(0, 1)
        ->execute();
      $lastContact = '—';
      if ($lastContactIds !== []) {
        $lastCommunication = $storage->load(reset($lastContactIds));
        if ($lastCommunication instanceof NodeInterface) {
          $lastContact = $this->value($lastCommunication, 'field_brebo_comm_datetime');
        }
      }

      $stageSince = date('Y-m-d', (int) $opportunity->getCreatedTime());
      $eventIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_opportunity_event')
        ->condition('field_brebo_event_opp_ref.target_id', $opportunity->id())
        ->sort('field_brebo_event_datetime', 'DESC')
        ->range(0, 1)
        ->execute();
      if ($eventIds !== []) {
        $event = $storage->load(reset($eventIds));
        if ($event instanceof NodeInterface) {
          $eventDate = $this->value($event, 'field_brebo_event_datetime');
          if ($eventDate !== '—') {
            $stageSince = substr($eventDate, 0, 10);
          }
        }
      }
      $stageAge = max(0, (int) floor((strtotime($today) - strtotime($stageSince)) / 86400));
      $nextAction = $this->value($opportunity, 'field_brebo_opp_next_action');
      $source = $this->value($opportunity, 'field_brebo_opp_source');
      $channel = $this->value($opportunity, 'field_brebo_opp_channel');
      $sourceKey = $source . '|' . $channel;
      if (!isset($sourceTotals[$sourceKey])) {
        $sourceTotals[$sourceKey] = [
          'source' => $source, 'channel' => $channel, 'count' => 0,
          'open' => 0, 'weighted' => 0.0, 'won' => 0, 'won_value' => 0.0,
        ];
      }
      $sourceTotals[$sourceKey]['count']++;

      if ($stage === 'Gewonnen') {
        $wonCount++;
        $wonValue += $value;
        $sourceTotals[$sourceKey]['won']++;
        $sourceTotals[$sourceKey]['won_value'] += $value;
        $handoverMissing = [];
        foreach ([
          'field_brebo_opp_contact_ref' => $this->t('contactpersoon'),
          'field_brebo_opp_offer_ref' => $this->t('offerte'),
          'field_brebo_opp_project_ref' => $this->t('project'),
        ] as $field => $label) {
          if (!$opportunity->hasField($field) || $opportunity->get($field)->isEmpty()) {
            $handoverMissing[] = (string) $label;
          }
        }
        if ($handoverMissing !== []) {
          $handoverPendingCount++;
          $handoverRows[] = [
            ['data' => Link::fromTextAndUrl($opportunity->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $opportunity->id()]))->toRenderable()],
            $organizationLabel,
            '€ ' . number_format($value, 2, ',', '.'),
            implode(', ', $handoverMissing),
            $ownerLabel,
          ];
        }
      }
      elseif ($stage === 'Verloren') {
        $lostCount++;
        $lostValue += $value;
        $lossReason = $this->value($opportunity, 'field_brebo_opp_loss_reason');
        if (!isset($lossReasons[$lossReason])) {
          $lossReasons[$lossReason] = ['count' => 0, 'value' => 0.0];
        }
        $lossReasons[$lossReason]['count']++;
        $lossReasons[$lossReason]['value'] += $value;
      }

      if (!isset($ownerTotals[$ownerLabel])) {
        $ownerTotals[$ownerLabel] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];
      }
      if ($active && !in_array($stage, ['Gewonnen', 'Verloren'], TRUE)) {
        $ownerTotals[$ownerLabel]['count']++;
        $ownerTotals[$ownerLabel]['value'] += $value;
        $ownerTotals[$ownerLabel]['weighted'] += $weighted;
        $sourceTotals[$sourceKey]['open']++;
        $sourceTotals[$sourceKey]['weighted'] += $weighted;
        if ($closeDate !== '—') {
          $forecastMonth = substr($closeDate, 0, 7);
          if (!isset($forecastTotals[$forecastMonth])) {
            $forecastTotals[$forecastMonth] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];
          }
          $forecastTotals[$forecastMonth]['count']++;
          $forecastTotals[$forecastMonth]['value'] += $value;
          $forecastTotals[$forecastMonth]['weighted'] += $weighted;
          if ($closeDate < $today) {
            $overdueCloseRows[] = [
              ['data' => Link::fromTextAndUrl($opportunity->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $opportunity->id()]))->toRenderable()],
              $organizationLabel,
              $stage,
              $closeDate,
              '€ ' . number_format($weighted, 2, ',', '.'),
              $ownerLabel,
            ];
          }
        }
        $alerts = [];
        $stageLimit = $stageLimits[$stage] ?? 0;
        if ($stageLimit > 0 && $stageAge > $stageLimit) {
          $stageOverdueCount++;
          $alerts[] = (string) $this->t('Fase langer dan @days dagen', ['@days' => $stageLimit]);
        }
        $qualificationStages = ['Kans', 'Afspraak', 'Calculatie/offerte', 'Onderhandeling'];
        if (in_array($stage, $qualificationStages, TRUE)) {
          $qualificationMissing = !$opportunity->hasField('field_brebo_opp_requirement')
            || $opportunity->get('field_brebo_opp_requirement')->isEmpty()
            || $opportunity->get('field_brebo_opp_decision_maker')->isEmpty()
            || $opportunity->get('field_brebo_opp_decision_date')->isEmpty();
          if ($qualificationMissing) {
            $unqualifiedCount++;
            $alerts[] = (string) $this->t('Kwalificatie niet compleet');
          }
        }
        if (in_array($stage, ['Calculatie/offerte', 'Onderhandeling'], TRUE)) {
          $linkedOffer = $opportunity->hasField('field_brebo_opp_offer_ref')
            ? $opportunity->get('field_brebo_opp_offer_ref')->entity
            : NULL;
          $offerSignal = NULL;
          if (!$linkedOffer instanceof NodeInterface) {
            $offerSignal = (string) $this->t('Geen offerteversie gekoppeld');
          }
          elseif ($closeDate !== '—' && $closeDate < $today) {
            $offerSignal = (string) $this->t('Besluitdatum verstreken');
          }
          elseif ($closeDate !== '—' && $closeDate <= $weekEnd) {
            $offerSignal = (string) $this->t('Besluit binnen 7 dagen');
          }
          if ($offerSignal !== NULL) {
            $offerAttentionCount++;
            $alerts[] = $offerSignal;
            $offerRows[] = [
              ['data' => Link::fromTextAndUrl($opportunity->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $opportunity->id()]))->toRenderable()],
              $organizationLabel,
              $stage,
              $linkedOffer instanceof NodeInterface ? $linkedOffer->label() : '—',
              $closeDate,
              $offerSignal,
              $ownerLabel,
            ];
          }
        }
        if ($lastContact === '—' || substr($lastContact, 0, 10) < $staleCutoff) {
          $staleCount++;
          $alerts[] = (string) $this->t('Geen klantcontact in 14 dagen');
        }
        if ($nextDate === '—' || $nextAction === '—') {
          $missingActionCount++;
          $alerts[] = (string) $this->t('Geen complete vervolgactie');
        }
        if ($alerts !== []) {
          $managementRows[] = [
            ['data' => Link::fromTextAndUrl($opportunity->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $opportunity->id()]))->toRenderable()],
            $organizationLabel,
            $stage,
            $stageAge,
            $lastContact,
            $nextDate,
            implode(', ', $alerts),
            $ownerLabel,
          ];
        }
      }

      if ($active && $nextDate !== '—' && $nextDate < $today) {
        $overdue++;
      }
      elseif ($active && $nextDate === $today) {
        $todayCount++;
      }
      elseif ($active && $nextDate !== '—' && $nextDate > $today && $nextDate <= $weekEnd) {
        $upcomingCount++;
      }
      if (!isset($stageTotals[$stage])) {
        $stageTotals[$stage] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0, 'age_total' => 0, 'over_norm' => 0];
      }
      $stageTotals[$stage]['count']++;
      $stageTotals[$stage]['value'] += $value;
      $stageTotals[$stage]['weighted'] += $weighted;
      $stageTotals[$stage]['age_total'] += $stageAge;
      $stageLimit = $stageLimits[$stage] ?? 0;
      if ($active && $stageLimit > 0 && $stageAge > $stageLimit) {
        $stageTotals[$stage]['over_norm']++;
      }
      if ($active && !in_array($stage, ['Gewonnen', 'Verloren'], TRUE)) {
        $openCount++;
        $totalValue += $value;
        $totalWeighted += $weighted;
      }

      $organizationCell = '—';
      if ($organization instanceof NodeInterface) {
        $organizationCell = Link::fromTextAndUrl(
          $organization->label(),
          Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()])
        )->toRenderable();
      }
      $row = [
        ['data' => Link::fromTextAndUrl($opportunity->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $opportunity->id()]))->toRenderable()],
        ['data' => $organizationCell],
        $stage,
        '€ ' . number_format($value, 2, ',', '.'),
        $probability . '%',
        '€ ' . number_format($weighted, 2, ',', '.'),
        $closeDate,
        $nextDate,
        $lastContact,
        $stageAge,
        $nextAction,
        $ownerLabel,
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $opportunity->id()]))->toRenderable()],
      ];
      $entries[] = [
        'node' => $opportunity,
        'row' => $row,
        'stage' => $stage,
        'stage_order' => $stageOrder[$stage] ?? 999,
        'organization' => $organizationLabel,
        'value' => $value,
        'probability' => $probability,
        'weighted' => $weighted,
        'close_date' => $closeDate,
        'next_date' => $nextDate,
        'last_contact' => $lastContact,
        'stage_age' => $stageAge,
        'active' => $active,
        'next_action' => $nextAction,
        'owner' => $ownerLabel,
      ];
    }

    usort($entries, static function (array $left, array $right) use ($sort, $direction): int {
      $key = $sort === 'stage' ? 'stage_order' : $sort;
      $a = $left[$key] ?? '';
      $b = $right[$key] ?? '';
      if (in_array($key, ['close_date', 'next_date', 'last_contact'], TRUE)) {
        $a = $a === '—' ? '9999-12-31' : $a;
        $b = $b === '—' ? '9999-12-31' : $b;
      }
      $comparison = is_numeric($a) && is_numeric($b)
        ? ((float) $a <=> (float) $b)
        : strnatcasecmp((string) $a, (string) $b);
      return $direction === 'desc' ? -$comparison : $comparison;
    });

    $attentionGroups = [
      (string) $this->t('Achterstallig') => [],
      (string) $this->t('Vandaag') => [],
      (string) $this->t('Komende 7 dagen') => [],
    ];
    foreach ($entries as $entry) {
      if (!$entry['active'] || $entry['next_date'] === '—') {
        continue;
      }
      $attentionGroup = NULL;
      if ($entry['next_date'] < $today) {
        $attentionGroup = (string) $this->t('Achterstallig');
      }
      elseif ($entry['next_date'] === $today) {
        $attentionGroup = (string) $this->t('Vandaag');
      }
      elseif ($entry['next_date'] <= $weekEnd) {
        $attentionGroup = (string) $this->t('Komende 7 dagen');
      }
      if ($attentionGroup !== NULL) {
        $attentionGroups[$attentionGroup][] = [
          ['data' => Link::fromTextAndUrl($entry['node']->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $entry['node']->id()]))->toRenderable()],
          $entry['organization'],
          $entry['stage'],
          $entry['next_date'],
          $entry['next_action'],
          $entry['last_contact'],
          $entry['owner'],
        ];
      }
    }
    $attentionBuild = ['#type' => 'container'];
    foreach ($attentionGroups as $attentionTitle => $attentionRows) {
      $attentionBuild['group_' . count($attentionBuild)] = $this->section(
        $attentionTitle,
        [$this->t('Kans'), $this->t('Organisatie'), $this->t('Fase'), $this->t('Actiedatum'), $this->t('Volgende actie'), $this->t('Laatste contact'), $this->t('Verantwoordelijke')],
        $attentionRows,
        $this->t('Geen acties in deze categorie.')
      );
    }

    $stageRows = [];
    foreach ($stageTotals as $stage => $totals) {
      $stageRows[] = [
        $stage,
        $totals['count'],
        '€ ' . number_format($totals['value'], 2, ',', '.'),
        '€ ' . number_format($totals['weighted'], 2, ',', '.'),
        $totals['count'] > 0 ? round($totals['age_total'] / $totals['count'], 1) : '—',
        ($stageLimits[$stage] ?? 0) > 0 ? $stageLimits[$stage] : '—',
        $totals['over_norm'],
      ];
    }

    $listGroups = [];
    foreach ($entries as $entry) {
      $groupTitle = match ($group) {
        'stage' => $entry['stage'],
        'organization' => $entry['organization'],
        'owner' => $entry['owner'],
        default => $this->t('Alle kansen'),
      };
      $listGroups[(string) $groupTitle][] = $entry['row'];
    }
    $listBuild = [
      '#type' => 'container',
      '#access' => $view === 'list',
    ];
    foreach ($listGroups as $groupTitle => $groupRows) {
      $listBuild['group_' . count($listBuild)] = $this->section(
        $groupTitle,
        [$this->t('Kans'), $this->t('Organisatie'), $this->t('Fase'), $this->t('Omzet'), $this->t('Kans'), $this->t('Gewogen'), $this->t('Sluitdatum'), $this->t('Volgende datum'), $this->t('Laatste contact'), $this->t('Dagen in fase'), $this->t('Volgende actie'), $this->t('Verantwoordelijke'), $this->t('Actie')],
        $groupRows,
        $this->t('Nog geen commerciële kansen vastgelegd.')
      );
    }
    if ($listGroups === []) {
      $listBuild['empty'] = ['#markup' => '<p>' . $this->t('Nog geen commerciële kansen vastgelegd.') . '</p>'];
    }

    $kanbanBuild = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-kanban']],
      '#access' => $view === 'kanban',
    ];
    foreach ($stages as $stage) {
      $cards = [];
      foreach ($entries as $entry) {
        if ($entry['stage'] !== $stage) {
          continue;
        }
        $cards[] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-kanban-card']],
          'title' => Link::fromTextAndUrl($entry['node']->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $entry['node']->id()]))->toRenderable(),
          'organization' => ['#type' => 'html_tag', '#tag' => 'div', '#value' => $entry['organization']],
          'value' => ['#type' => 'html_tag', '#tag' => 'div', '#value' => '€ ' . number_format($entry['value'], 2, ',', '.') . ' · ' . $entry['probability'] . '%'],
          'owner' => ['#type' => 'html_tag', '#tag' => 'div', '#value' => $entry['owner'] . ' · ' . $entry['stage_age'] . ' dagen in fase'],
          'next' => ['#type' => 'html_tag', '#tag' => 'div', '#value' => $entry['next_date'] . ' · ' . $entry['next_action']],
          'edit' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $entry['node']->id()]))->toRenderable(),
        ];
      }
      $kanbanBuild['stage_' . count($kanbanBuild)] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-kanban-column']],
        'header' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $stage . ' (' . count($cards) . ')'],
        'cards' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-kanban-cards']],
        ] + $cards,
      ];
    }

    $ownerRows = [];
    ksort($ownerTotals, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($ownerTotals as $ownerName => $totals) {
      $ownerRows[] = [
        $ownerName,
        $totals['count'],
        '€ ' . number_format($totals['value'], 2, ',', '.'),
        '€ ' . number_format($totals['weighted'], 2, ',', '.'),
      ];
    }
    $forecastRows = [];
    ksort($forecastTotals);
    foreach ($forecastTotals as $month => $totals) {
      $forecastRows[] = [
        date('m-Y', strtotime($month . '-01')),
        $totals['count'],
        '€ ' . number_format($totals['value'], 2, ',', '.'),
        '€ ' . number_format($totals['weighted'], 2, ',', '.'),
      ];
    }
    $lossReasonRows = [];
    uasort($lossReasons, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
    foreach ($lossReasons as $reason => $totals) {
      $lossReasonRows[] = [
        $reason,
        $totals['count'],
        '€ ' . number_format($totals['value'], 2, ',', '.'),
      ];
    }
    $sourceRows = [];
    uasort($sourceTotals, static fn(array $a, array $b): int => $b['won_value'] <=> $a['won_value']);
    foreach ($sourceTotals as $totals) {
      $sourceRows[] = [
        $totals['source'],
        $totals['channel'],
        $totals['count'],
        $totals['open'],
        '€ ' . number_format($totals['weighted'], 2, ',', '.'),
        $totals['won'],
        '€ ' . number_format($totals['won_value'], 2, ',', '.'),
      ];
    }
    $closedCount = $wonCount + $lostCount;
    $winRate = $closedCount > 0 ? round($wonCount * 100 / $closedCount, 1) . '%' : '—';

    $queryBase = array_filter(['mine' => $mine ? 1 : NULL]);
    return [
      '#attached' => ['library' => ['brebo_office_core/funnel']],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuwe kans'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_opportunity']),
          '#attributes' => ['class' => ['button']],
        ],
        'list' => [
          '#type' => 'link',
          '#title' => $this->t('Lijst'),
          '#url' => Url::fromRoute('brebo_office_core.funnel', [], ['query' => $queryBase + ['view' => 'list', 'group' => $group, 'sort' => $sort, 'direction' => $direction]]),
          '#attributes' => ['class' => ['button']],
        ],
        'kanban' => [
          '#type' => 'link',
          '#title' => $this->t('Kanban'),
          '#url' => Url::fromRoute('brebo_office_core.funnel', [], ['query' => $queryBase + ['view' => 'kanban', 'sort' => $sort, 'direction' => $direction]]),
          '#attributes' => ['class' => ['button']],
        ],
        'all' => [
          '#type' => 'link',
          '#title' => $this->t('Alle kansen'),
          '#url' => Url::fromRoute('brebo_office_core.funnel', [], ['query' => ['view' => $view, 'group' => $group, 'sort' => $sort, 'direction' => $direction]]),
          '#attributes' => ['class' => ['button']],
        ],
        'mine' => [
          '#type' => 'link',
          '#title' => $this->t('Mijn kansen'),
          '#url' => Url::fromRoute('brebo_office_core.funnel', [], ['query' => ['mine' => 1, 'view' => $view, 'group' => $group, 'sort' => $sort, 'direction' => $direction]]),
          '#attributes' => ['class' => ['button']],
        ],
        'crm' => [
          '#type' => 'link',
          '#title' => $this->t('Relaties'),
          '#url' => Url::fromRoute('brebo_office_core.crm'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'controls' => [
        '#type' => 'html_tag',
        '#tag' => 'form',
        '#attributes' => ['method' => 'get', 'action' => Url::fromRoute('brebo_office_core.funnel')->toString(), 'class' => ['brebo-funnel-controls']],
        'mine' => $mine ? ['#type' => 'html_tag', '#tag' => 'input', '#attributes' => ['type' => 'hidden', 'name' => 'mine', 'value' => '1']] : [],
        'view' => ['#type' => 'html_tag', '#tag' => 'input', '#attributes' => ['type' => 'hidden', 'name' => 'view', 'value' => $view]],
        'group' => [
          '#type' => 'select',
          '#title' => $this->t('Groeperen'),
          '#name' => 'group',
          '#default_value' => $group,
          '#options' => ['none' => $this->t('Niet groeperen'), 'stage' => $this->t('Fase'), 'organization' => $this->t('Organisatie'), 'owner' => $this->t('Verantwoordelijke')],
        ],
        'sort' => [
          '#type' => 'select',
          '#title' => $this->t('Sorteren'),
          '#name' => 'sort',
          '#default_value' => $sort,
          '#options' => ['stage' => $this->t('Fase'), 'stage_age' => $this->t('Dagen in fase'), 'organization' => $this->t('Organisatie'), 'value' => $this->t('Omzet'), 'probability' => $this->t('Scoringskans'), 'close_date' => $this->t('Sluitdatum'), 'next_date' => $this->t('Actiedatum'), 'last_contact' => $this->t('Laatste contact'), 'owner' => $this->t('Verantwoordelijke')],
        ],
        'direction' => [
          '#type' => 'select',
          '#title' => $this->t('Volgorde'),
          '#name' => 'direction',
          '#default_value' => $direction,
          '#options' => ['asc' => $this->t('Oplopend'), 'desc' => $this->t('Aflopend')],
        ],
        'submit' => ['#type' => 'html_tag', '#tag' => 'button', '#value' => $this->t('Toepassen'), '#attributes' => ['type' => 'submit', 'class' => ['button']]],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Open kansen'), $this->t('Verwachte omzet'), $this->t('Gewogen omzet'), $this->t('Achterstallig'), $this->t('Vandaag'), $this->t('Komende 7 dagen')],
        '#rows' => [[$openCount, '€ ' . number_format($totalValue, 2, ',', '.'), '€ ' . number_format($totalWeighted, 2, ',', '.'), $overdue, $todayCount, $upcomingCount]],
      ],
      'management_summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Gewonnen'), $this->t('Verloren'), $this->t('Winratio'), $this->t('Gewonnen omzet'), $this->t('Verloren omzet'), $this->t('Zonder recent contact'), $this->t('Zonder vervolgactie'), $this->t('Onvolledig gekwalificeerd'), $this->t('Offerte-aandacht'), $this->t('Overdracht open'), $this->t('Te lang in fase')],
        '#rows' => [[$wonCount, $lostCount, $winRate, '€ ' . number_format($wonValue, 2, ',', '.'), '€ ' . number_format($lostValue, 2, ',', '.'), $staleCount, $missingActionCount, $unqualifiedCount, $offerAttentionCount, $handoverPendingCount, $stageOverdueCount]],
      ],
      'handover' => $this->section(
        $this->t('Overdracht verkoop naar uitvoering'),
        [$this->t('Gewonnen kans'), $this->t('Organisatie'), $this->t('Omzet'), $this->t('Ontbreekt'), $this->t('Verantwoordelijke')],
        $handoverRows,
        $this->t('Alle gewonnen kansen zijn volledig overgedragen.')
      ),
      'offer_monitoring' => $this->section(
        $this->t('Offertebewaking'),
        [$this->t('Kans'), $this->t('Organisatie'), $this->t('Fase'), $this->t('Offerteversie'), $this->t('Verwachte besluitdatum'), $this->t('Signaal'), $this->t('Verantwoordelijke')],
        $offerRows,
        $this->t('Geen offertes die directe aandacht vragen.')
      ),
      'sources' => $this->section(
        $this->t('Resultaat per leadbron'),
        [$this->t('Leadbron'), $this->t('Kanaal'), $this->t('Totaal'), $this->t('Open'), $this->t('Gewogen omzet'), $this->t('Gewonnen'), $this->t('Gewonnen omzet')],
        $sourceRows,
        $this->t('Nog geen leadbronnen vastgelegd.')
      ),
      'forecast' => $this->section(
        $this->t('Verkoopprognose per sluitmaand'),
        [$this->t('Maand'), $this->t('Open kansen'), $this->t('Verwachte omzet'), $this->t('Gewogen omzet')],
        $forecastRows,
        $this->t('Geen open kansen met een verwachte sluitdatum.')
      ),
      'overdue_closes' => $this->section(
        $this->t('Verlopen verwachte sluitdata'),
        [$this->t('Kans'), $this->t('Organisatie'), $this->t('Fase'), $this->t('Sluitdatum'), $this->t('Gewogen omzet'), $this->t('Verantwoordelijke')],
        $overdueCloseRows,
        $this->t('Geen verlopen sluitdata.')
      ),
      'loss_reasons' => $this->section(
        $this->t('Analyse verliesredenen'),
        [$this->t('Verliesreden'), $this->t('Aantal'), $this->t('Verloren omzet')],
        $lossReasonRows,
        $this->t('Nog geen verloren kansen.')
      ),
      'management_alerts' => $this->section(
        $this->t('Managementsignalen'),
        [$this->t('Kans'), $this->t('Organisatie'), $this->t('Fase'), $this->t('Dagen in fase'), $this->t('Laatste contact'), $this->t('Volgende datum'), $this->t('Signaal'), $this->t('Verantwoordelijke')],
        $managementRows,
        $this->t('Geen stilgevallen kansen of ontbrekende vervolgacties.')
      ),
      'owners' => $this->section(
        $this->t('Open funnel per verantwoordelijke'),
        [$this->t('Verantwoordelijke'), $this->t('Open kansen'), $this->t('Omzet'), $this->t('Gewogen omzet')],
        $ownerRows,
        $this->t('Geen open kansen.')
      ),
      'attention' => [
        '#type' => 'details',
        '#title' => $this->t('Commerciële opvolging'),
        '#open' => TRUE,
        'content' => $attentionBuild,
      ],
      'stages' => $this->section(
        $this->t('Funnel per fase'),
        [$this->t('Fase'), $this->t('Aantal'), $this->t('Omzet'), $this->t('Gewogen omzet'), $this->t('Gemiddeld dagen'), $this->t('Norm dagen'), $this->t('Boven norm')],
        $stageRows,
        $this->t('Nog geen funnelgegevens.')
      ),
      'list' => $listBuild,
      'kanban' => $kanbanBuild,
      '#cache' => [
        'contexts' => ['user.permissions', 'user', 'url.query_args:mine', 'url.query_args:view', 'url.query_args:group', 'url.query_args:sort', 'url.query_args:direction'],
        'tags' => ['node_list:brebo_opportunity', 'node_list:brebo_opportunity_event', 'node_list:brebo_organization', 'node_list:brebo_communication'],
      ],
    ];
  }

  public function opportunityTitle(NodeInterface $node): string {
    if ($node->bundle() !== 'brebo_opportunity') {
      throw new NotFoundHttpException();
    }
    return (string) $node->label();
  }

  public function opportunity(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_opportunity') {
      throw new NotFoundHttpException();
    }
    $storage = $this->entityTypeManager()->getStorage('node');
    $organization = $node->get('field_brebo_opp_org_ref')->entity;
    $contact = $node->get('field_brebo_opp_contact_ref')->entity;
    $owner = $node->get('field_brebo_opp_owner')->entity;
    $calculation = $node->get('field_brebo_opp_calc_ref')->entity;
    $offer = $node->get('field_brebo_opp_offer_ref')->entity;
    $project = $node->get('field_brebo_opp_project_ref')->entity;
    $value = (float) ($node->get('field_brebo_opp_value')->value ?? 0);
    $probability = max(0, min(100, (int) ($node->get('field_brebo_opp_probability')->value ?? 0)));
    $weighted = $value * $probability / 100;

    $eventIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_opportunity_event')
      ->condition('field_brebo_event_opp_ref.target_id', $node->id())
      ->sort('field_brebo_event_datetime', 'DESC')
      ->execute();
    $eventRows = [];
    foreach ($storage->loadMultiple($eventIds) as $event) {
      if (!$event instanceof NodeInterface) {
        continue;
      }
      $changedBy = $event->get('field_brebo_event_user')->entity;
      $eventRows[] = [
        $this->value($event, 'field_brebo_event_datetime'),
        $this->value($event, 'field_brebo_event_from_stage'),
        $this->value($event, 'field_brebo_event_to_stage'),
        $changedBy !== NULL ? $changedBy->label() : '—',
        $this->value($event, 'field_brebo_event_note'),
      ];
    }

    $communicationIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_communication')
      ->condition('field_brebo_comm_opp_ref.target_id', $node->id())
      ->sort('field_brebo_comm_datetime', 'DESC')
      ->execute();
    $communicationRows = [];
    $externalContactCount = 0;
    $noteCount = 0;
    foreach ($storage->loadMultiple($communicationIds) as $communication) {
      if (!$communication instanceof NodeInterface) {
        continue;
      }
      $communicationContact = $communication->get('field_brebo_comm_contact_ref')->entity;
      if ($this->value($communication, 'field_brebo_comm_direction') === 'Intern vastgelegd') {
        $noteCount++;
      }
      else {
        $externalContactCount++;
      }
      $communicationRows[] = [
        $this->value($communication, 'field_brebo_comm_datetime'),
        $this->value($communication, 'field_brebo_comm_channel'),
        $this->value($communication, 'field_brebo_comm_direction'),
        ['data' => Link::fromTextAndUrl($communication->label(), $communication->toUrl())->toRenderable()],
        $communicationContact instanceof NodeInterface ? $communicationContact->label() : '—',
        $this->value($communication, 'field_brebo_comm_status'),
      ];
    }

    $organizationCell = $organization instanceof NodeInterface
      ? Link::fromTextAndUrl($organization->label(), Url::fromRoute('brebo_office_core.organization_dashboard', ['node' => $organization->id()]))->toRenderable()
      : '—';
    $contactCell = $contact instanceof NodeInterface
      ? Link::fromTextAndUrl($contact->label(), Url::fromRoute('brebo_office_core.contact_dashboard', ['node' => $contact->id()]))->toRenderable()
      : '—';

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'transition' => [
          '#type' => 'link',
          '#title' => $this->t('Fase wijzigen'),
          '#url' => Url::fromRoute('brebo_office_core.opportunity_transition', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'convert' => [
          '#type' => 'link',
          '#title' => $this->t('Project starten'),
          '#url' => Url::fromRoute('brebo_office_core.opportunity_convert', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button', 'button--primary']],
          '#access' => (string) $node->get('field_brebo_opp_stage')->value === 'Gewonnen'
            && !$project instanceof NodeInterface,
        ],
        'note' => [
          '#type' => 'link',
          '#title' => $this->t('Notitie toevoegen'),
          '#url' => Url::fromRoute('brebo_office_core.opportunity_note', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'contact' => [
          '#type' => 'link',
          '#title' => $this->t('Contactmoment vastleggen'),
          '#url' => Url::fromRoute('brebo_office_core.opportunity_contact', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Kans bewerken'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
          '#attributes' => ['class' => ['button']],
        ],
        'funnel' => [
          '#type' => 'link',
          '#title' => $this->t('Terug naar funnel'),
          '#url' => Url::fromRoute('brebo_office_core.funnel'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Fase'), $this->t('Actief'), $this->t('Omzet'), $this->t('Scoringskans'), $this->t('Gewogen omzet'), $this->t('Contactmomenten'), $this->t('Notities')],
        '#rows' => [[
          $this->value($node, 'field_brebo_opp_stage'),
          (bool) $node->get('field_brebo_opp_active')->value ? $this->t('Ja') : $this->t('Nee'),
          '€ ' . number_format($value, 2, ',', '.'),
          $probability . '%',
          '€ ' . number_format($weighted, 2, ',', '.'),
          $externalContactCount,
          $noteCount,
        ]],
      ],
      'details' => [
        '#type' => 'details',
        '#title' => $this->t('Kansgegevens'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#rows' => [
            [$this->t('Organisatie'), ['data' => $organizationCell]],
            [$this->t('Primaire contactpersoon'), ['data' => $contactCell]],
            [$this->t('Verantwoordelijke'), $owner !== NULL ? $owner->label() : '—'],
            [$this->t('Calculatie'), $calculation instanceof NodeInterface ? $calculation->label() : '—'],
            [$this->t('Offerteversie'), $offer instanceof NodeInterface ? $offer->label() : '—'],
            [$this->t('Project'), $project instanceof NodeInterface
              ? ['data' => Link::fromTextAndUrl($project->label(), Url::fromRoute('brebo_office_core.project_dashboard', ['node' => $project->id()]))->toRenderable()]
              : '—'],
            [$this->t('Leadbron'), $this->value($node, 'field_brebo_opp_source')],
            [$this->t('Acquisitiekanaal'), $this->value($node, 'field_brebo_opp_channel')],
            [$this->t('Campagne of actie'), $this->value($node, 'field_brebo_opp_campaign')],
            [$this->t('Klantbehoefte en scope'), $this->value($node, 'field_brebo_opp_requirement')],
            [$this->t('Beslisser'), $this->value($node, 'field_brebo_opp_decision_maker')],
            [$this->t('Budget bevestigd'), $node->hasField('field_brebo_opp_budget_confirmed') && (bool) $node->get('field_brebo_opp_budget_confirmed')->value ? $this->t('Ja') : $this->t('Nee')],
            [$this->t('Beslis- of aanbestedingsdatum'), $this->value($node, 'field_brebo_opp_decision_date')],
            [$this->t('Verwachte sluitdatum'), $this->value($node, 'field_brebo_opp_close_date')],
            [$this->t('Volgende actiedatum'), $this->value($node, 'field_brebo_opp_next_date')],
            [$this->t('Volgende actie'), $this->value($node, 'field_brebo_opp_next_action')],
            [$this->t('Verliesreden'), $this->value($node, 'field_brebo_opp_loss_reason')],
            [$this->t('Notities'), $this->value($node, 'field_brebo_opp_notes')],
          ],
        ],
      ],
      'communications' => $this->section(
        $this->t('Contactmomenten en interne notities'),
        [$this->t('Datum'), $this->t('Kanaal'), $this->t('Richting'), $this->t('Onderwerp'), $this->t('Contactpersoon'), $this->t('Status')],
        $communicationRows,
        $this->t('Nog geen contactmomenten aan deze kans gekoppeld.')
      ),
      'history' => $this->section(
        $this->t('Fasehistorie'),
        [$this->t('Datum'), $this->t('Van'), $this->t('Naar'), $this->t('Door'), $this->t('Toelichting')],
        $eventRows,
        $this->t('Nog geen faseovergangen vastgelegd.')
      ),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), ['node_list:brebo_communication', 'node_list:brebo_opportunity_event']),
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

    $opportunityIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_opportunity')
      ->condition('field_brebo_opp_org_ref.target_id', $node->id())
      ->sort('changed', 'DESC')
      ->execute();
    $opportunities = $storage->loadMultiple($opportunityIds);

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

    $opportunityRows = [];
    $openOpportunityCount = 0;
    $opportunityWeighted = 0.0;
    foreach ($opportunities as $opportunity) {
      if (!$opportunity instanceof NodeInterface) {
        continue;
      }
      $owner = $opportunity->get('field_brebo_opp_owner')->entity;
      $value = (float) ($opportunity->get('field_brebo_opp_value')->value ?? 0);
      $probability = max(0, min(100, (int) ($opportunity->get('field_brebo_opp_probability')->value ?? 0)));
      $weighted = $value * $probability / 100;
      if ((bool) $opportunity->get('field_brebo_opp_active')->value
        && !in_array($this->value($opportunity, 'field_brebo_opp_stage'), ['Gewonnen', 'Verloren'], TRUE)) {
        $openOpportunityCount++;
        $opportunityWeighted += $weighted;
      }
      $opportunityRows[] = [
        ['data' => Link::fromTextAndUrl($opportunity->label(), Url::fromRoute('brebo_office_core.opportunity_dashboard', ['node' => $opportunity->id()]))->toRenderable()],
        $this->value($opportunity, 'field_brebo_opp_stage'),
        '€ ' . number_format($value, 2, ',', '.'),
        $probability . '%',
        '€ ' . number_format($weighted, 2, ',', '.'),
        $this->value($opportunity, 'field_brebo_opp_next_date'),
        $this->value($opportunity, 'field_brebo_opp_next_action'),
        $owner !== NULL ? $owner->label() : '—',
        ['data' => Link::fromTextAndUrl($this->t('Bewerken'), Url::fromRoute('entity.node.edit_form', ['node' => $opportunity->id()]))->toRenderable()],
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

    $accountOwner = $node->hasField('field_brebo_org_account_owner')
      ? $node->get('field_brebo_org_account_owner')->entity
      : NULL;
    $accountOwnerLabel = $accountOwner !== NULL ? $accountOwner->label() : '—';

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
        'opportunity' => [
          '#type' => 'link',
          '#title' => $this->t('Kans toevoegen'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_opportunity'], [
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
        '#header' => [$this->t('Actief'), $this->t('Klant'), $this->t('Leverancier'), $this->t('Locaties'), $this->t('Contactrelaties'), $this->t('Open kansen'), $this->t('Gewogen omzet'), $this->t('Projecten'), $this->t('Gebouwen'), $this->t('Communicatie')],
        '#rows' => [[
          $node->hasField('field_brebo_org_active') && (bool) $node->get('field_brebo_org_active')->value ? $this->t('Ja') : $this->t('Nee'),
          $node->hasField('field_brebo_org_customer') && (bool) $node->get('field_brebo_org_customer')->value ? $this->t('Ja') : $this->t('Nee'),
          $node->hasField('field_brebo_org_supplier') && (bool) $node->get('field_brebo_org_supplier')->value ? $this->t('Ja') : $this->t('Nee'),
          count($locations),
          count($affiliations),
          $openOpportunityCount,
          '€ ' . number_format($opportunityWeighted, 2, ',', '.'),
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
            [$this->t('Betaaltermijn klant'), $this->value($node, 'field_brebo_org_customer_days') !== '—' ? $this->t('@days dagen', ['@days' => $this->value($node, 'field_brebo_org_customer_days')]) : '—'],
            [$this->t('Betaaltermijn leverancier'), $this->value($node, 'field_brebo_org_supplier_days') !== '—' ? $this->t('@days dagen', ['@days' => $this->value($node, 'field_brebo_org_supplier_days')]) : '—'],
            [$this->t('Account van'), $accountOwnerLabel],
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
      'opportunities' => $this->section(
        $this->t('Commerciële kansen'),
        [$this->t('Kans'), $this->t('Fase'), $this->t('Omzet'), $this->t('Kans'), $this->t('Gewogen'), $this->t('Volgende datum'), $this->t('Volgende actie'), $this->t('Verantwoordelijke'), $this->t('Actie')],
        $opportunityRows,
        $this->t('Nog geen commerciële kansen gekoppeld.')
      ),
      'projects' => $this->section($this->t('Projecten'), [$this->t('Project'), $this->t('Status'), $this->t('Gebouw'), $this->t('Soort')], $projectRows, $this->t('Nog geen projecten gekoppeld.')),
      'buildings' => $this->section($this->t('Gebouwen via projecten'), [$this->t('Gebouw'), $this->t('Adres'), $this->t('Status')], $buildingRows, $this->t('Nog geen gebouwen via projecten gevonden.')),
      'communications' => $this->section($this->t('Laatste communicatie'), [$this->t('Onderwerp'), $this->t('Datum'), $this->t('Richting'), $this->t('Status')], $communicationRows, $this->t('Nog geen communicatie gekoppeld.')),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_merge($node->getCacheTags(), [
          'node_list:brebo_contact',
          'node_list:brebo_organization_location',
          'node_list:brebo_contact_affiliation',
          'node_list:brebo_opportunity',
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
