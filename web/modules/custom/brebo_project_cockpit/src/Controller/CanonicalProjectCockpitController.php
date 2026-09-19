<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_office_core\Project\ProjectLifecycleStatus;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/** Keeps one calm project overview above the canonical project dossier. */
final class CanonicalProjectCockpitController extends ControllerBase {

  public function title(NodeInterface $node): string {
    return $this->legacyController()->title($node);
  }

  public function overview(NodeInterface $node): array {
    $build = $this->legacyController()->overview($node);
    $projectId = (int) $node->id();

    $build['tabs'] = _brebo_project_cockpit_tabs($projectId, 'brebo_project_cockpit.overview');
    unset($build['money'], $build['revenue'], $build['steering'], $build['quick_actions']);

    if (isset($build['attention']) && is_array($build['attention'])) {
      $build['attention']['#title'] = $this->t('Dit vraagt aandacht');
      $build['attention']['#attributes']['class'][] = 'brebo-project-cockpit__attention';
      $build['attention']['#weight'] = 20;
    }

    $progressRows = [];
    foreach ($build['progress']['#rows'] ?? [] as $row) {
      $progressRows[(string) ($row[0] ?? '')] = $row;
    }

    $build['project_card'] = $this->projectCard($node);
    $build['project_card']['#weight'] = 15;

    $dashboard = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard']],
      '#weight' => 30,
    ];

    $dashboard['steering'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__steering']],
      'progress' => $this->metricCard(
        $this->t('Voortgang'),
        $this->rowValue($progressRows, ['Uitgevoerde voortgang', 'Werk gereed']),
        $this->rowMeaning($progressRows, ['Voortgang t.o.v. tijd', 'Voor/achter planning']),
        'brebo_office_core.project_planning',
        ['node' => $projectId],
      ),
      'planning' => $this->metricCard(
        $this->t('Tijdpad'),
        $this->rowValue($progressRows, ['Projecttijd verstreken', 'Tijd verstreken']),
        $this->rowMeaning($progressRows, ['Geplande periode']),
        'brebo_office_core.project_planning',
        ['node' => $projectId],
      ),
      'costs' => $this->metricCard(
        $this->t('Uitgevoerde kosten'),
        $this->rowValue($progressRows, ['Kosten gerealiseerd', 'Uitgevoerde kosten']),
        $this->t('Geverifieerde prestatie excl. btw'),
        'brebo_project_cockpit.budget',
        ['node' => $projectId],
      ),
      'result' => $this->metricCard(
        $this->t('Verwacht resultaat'),
        $this->rowValue($progressRows, ['Prognose eindmarge', 'Verwachte marge']),
        $this->t('Actuele prognose bij oplevering'),
        'brebo_project_cockpit.budget',
        ['node' => $projectId],
      ),
    ];

    $dashboard['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__grid']],
      'planning' => $this->panel(
        $this->t('Planning & voortgang'),
        $this->t('Zie direct of het project op koers ligt en open de projectroute, mijlpalen en uitvoeringsplanning.'),
        'brebo_office_core.project_planning',
        ['node' => $projectId],
        $this->t('Open planning'),
      ),
      'finance' => $this->panel(
        $this->t('Financieel'),
        $this->t('Verkoop, inkoop, nog te verwachten kosten en resultaat blijven gebaseerd op de bestaande financiële projectwaarheid.'),
        'brebo_project_cockpit.invoices',
        ['node' => $projectId],
        $this->t('Open facturen'),
      ),
      'quality' => $this->panel(
        $this->t('Tekortkomingen & oplevering'),
        $this->t('Open tekortkomingen en opleverpunten blijven in hun eigen werkruimte; het overzicht signaleert alleen wat aandacht vraagt.'),
        'brebo_project_cockpit.shortcomings',
        ['node' => $projectId],
        $this->t('Open tekortkomingen'),
      ),
      'team' => $this->projectTeamPanel($node),
    ];

    $documentRoutes = $this->routeProvider()->getRoutesByNames(['brebo_document_data.project_dossier']);
    if (!empty($documentRoutes)) {
      $dashboard['grid']['documents'] = $this->panel(
        $this->t('Documenten'),
        $this->t('Projectgebonden stukken horen in één dossier. Open het dossier voor de volledige inhoud en historie.'),
        'brebo_document_data.project_dossier',
        ['node' => $projectId],
        $this->t('Open documenten'),
      );
    }

    $build['dashboard'] = $dashboard;
    unset($build['progress']);
    if (isset($build['cockpit']) && is_array($build['cockpit'])) {
      $build['cockpit']['#weight'] = 0;
    }
    $build['tabs']['#weight'] = 10;

    return $build;
  }

  private function projectCard(NodeInterface $project): array {
    $manager = $project->hasField('field_brebo_project_manager') ? $project->get('field_brebo_project_manager')->entity : NULL;
    $organization = $project->hasField('field_brebo_project_org_ref') ? $project->get('field_brebo_project_org_ref')->entity : NULL;
    $client = $organization instanceof NodeInterface ? $organization->label() : $this->scalar($project, 'field_brebo_client');

    $facts = [
      $this->fact($this->t('Projectnummer'), $this->scalar($project, 'field_brebo_project_code')),
      $this->fact($this->t('Status'), ProjectLifecycleStatus::label($project)),
      $this->fact($this->t('Projectleider'), $manager ? $manager->label() : $this->t('Nog niet toegewezen')),
      $this->fact($this->t('Opdrachtgever'), $client),
      $this->fact($this->t('Locatie'), $this->scalar($project, 'field_brebo_location')),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-identity']],
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-identity__heading']],
        'eyebrow' => ['#type' => 'html_tag', '#tag' => 'span', 'text' => ['#plain_text' => (string) $this->t('Projectoverzicht')], '#attributes' => ['class' => ['brebo-project-dashboard__eyebrow']]],
        'title' => ['#type' => 'html_tag', '#tag' => 'h2', 'text' => ['#plain_text' => (string) $project->label()]],
      ],
      'facts' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-identity__facts']],
      ] + $facts,
      'edit' => [
        '#type' => 'link',
        '#title' => $this->t('Project bewerken'),
        '#url' => Url::fromRoute('entity.node.edit_form', ['node' => (int) $project->id()]),
        '#attributes' => ['class' => ['button']],
      ],
    ];
  }

  private function fact($label, $value): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-identity__fact']],
      'label' => ['#type' => 'html_tag', '#tag' => 'span', 'text' => ['#plain_text' => (string) $label]],
      'value' => ['#type' => 'html_tag', '#tag' => 'strong', 'text' => ['#plain_text' => (string) ($value ?: '—')]],
    ];
  }

  private function scalar(NodeInterface $project, string $field): string {
    if (!$project->hasField($field) || $project->get($field)->isEmpty()) {
      return '—';
    }
    return trim((string) $project->get($field)->value) ?: '—';
  }

  private function metricCard($label, string $value, $meaning, string $route, array $parameters): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__metric']],
      'label' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $label, '#attributes' => ['class' => ['brebo-project-dashboard__eyebrow']]],
      'value' => ['#type' => 'html_tag', '#tag' => 'strong', '#value' => $value !== '' ? $value : '—'],
      'meaning' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $meaning ?: '—'],
      'link' => ['#type' => 'link', '#title' => $this->t('Openen'), '#url' => Url::fromRoute($route, $parameters), '#attributes' => ['class' => ['brebo-project-dashboard__link']]],
    ];
  }

  private function panel($title, $body, string $route, array $parameters, $linkTitle): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__panel']],
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $title],
      'body' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $body],
      'link' => ['#type' => 'link', '#title' => $linkTitle, '#url' => Url::fromRoute($route, $parameters), '#attributes' => ['class' => ['brebo-project-dashboard__link']]],
    ];
  }

  private function projectTeamPanel(NodeInterface $project): array {
    $organization = $project->hasField('field_brebo_project_org_ref') ? $project->get('field_brebo_project_org_ref')->entity : NULL;
    $manager = $project->hasField('field_brebo_project_manager') ? $project->get('field_brebo_project_manager')->entity : NULL;
    $contacts = $project->hasField('field_brebo_project_contact_refs') ? $project->get('field_brebo_project_contact_refs')->referencedEntities() : [];
    $items = [];

    $items[] = [
      '#type' => 'inline_template',
      '#template' => '<strong>{{ label }}:</strong> {{ value }}',
      '#context' => [
        'label' => $this->t('Projectleider'),
        'value' => $manager ? $manager->label() : $this->t('Nog niet toegewezen'),
      ],
    ];

    if ($organization instanceof NodeInterface) {
      $items[] = [
        '#type' => 'inline_template',
        '#template' => '<strong>{{ label }}:</strong> {{ value }}',
        '#context' => ['label' => $this->t('Opdrachtgever'), 'value' => $organization->label()],
      ];
    }

    foreach (array_slice($contacts, 0, 3) as $contact) {
      if (!$contact instanceof NodeInterface) {
        continue;
      }
      $role = $contact->hasField('field_brebo_contact_role') ? trim((string) $contact->get('field_brebo_contact_role')->value) : '';
      $items[] = [
        '#type' => 'inline_template',
        '#template' => '<strong>{{ name }}</strong>{% if role %} · {{ role }}{% endif %}',
        '#context' => ['name' => $contact->label(), 'role' => $role],
      ];
    }

    if (!$organization instanceof NodeInterface && $contacts === []) {
      $items[] = ['#plain_text' => $this->t('Nog geen canonieke opdrachtgever of projectcontactpersonen gekoppeld.')];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__panel']],
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Projectorganisatie')],
      'items' => ['#theme' => 'item_list', '#items' => $items],
      'link' => ['#type' => 'link', '#title' => $this->t('Project bewerken'), '#url' => Url::fromRoute('entity.node.edit_form', ['node' => (int) $project->id()]), '#attributes' => ['class' => ['brebo-project-dashboard__link']]],
    ];
  }

  private function rowValue(array $rows, array $labels): string {
    foreach ($labels as $label) {
      if (isset($rows[$label][1])) {
        return (string) $rows[$label][1];
      }
    }
    return '—';
  }

  private function rowMeaning(array $rows, array $labels): string {
    foreach ($labels as $label) {
      if (!isset($rows[$label])) {
        continue;
      }
      $row = $rows[$label];
      return trim(implode(' · ', array_filter([(string) ($row[1] ?? ''), (string) ($row[2] ?? '')])));
    }
    return '—';
  }

  private function routeProvider() {
    return \Drupal::service('router.route_provider');
  }

  private function legacyController(): ProjectCockpitController {
    return ProjectCockpitController::create(\Drupal::getContainer());
  }

}
