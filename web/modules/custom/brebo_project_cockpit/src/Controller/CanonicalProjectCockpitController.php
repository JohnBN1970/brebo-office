<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

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
        $this->rowValue($progressRows, 'Uitgevoerde voortgang'),
        $this->rowMeaning($progressRows, 'Voortgang t.o.v. tijd'),
        'brebo_office_core.project_planning',
        ['node' => $projectId],
      ),
      'planning' => $this->metricCard(
        $this->t('Tijdpad'),
        $this->rowValue($progressRows, 'Projecttijd verstreken'),
        $this->rowMeaning($progressRows, 'Geplande periode'),
        'brebo_office_core.project_planning',
        ['node' => $projectId],
      ),
      'costs' => $this->metricCard(
        $this->t('Uitgevoerde kosten'),
        $this->rowValue($progressRows, 'Kosten gerealiseerd'),
        $this->t('Geverifieerde prestatie excl. btw'),
        'brebo_project_cockpit.budget',
        ['node' => $projectId],
      ),
      'result' => $this->metricCard(
        $this->t('Verwacht resultaat'),
        $this->rowValue($progressRows, 'Prognose eindmarge'),
        $this->t('Actuele prognose bij oplevering'),
        'brebo_project_cockpit.budget',
        ['node' => $projectId],
      ),
    ];

    $dashboard['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__grid']],
      'planning' => $this->panel(
        $this->t('Planning & kritisch pad'),
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
      'documents' => $this->panel(
        $this->t('Documenten'),
        $this->t('Projectgebonden stukken horen in één dossier. Open het dossier voor de volledige inhoud en historie.'),
        'brebo_document_data.project_dossier',
        ['node' => $projectId],
        $this->t('Open documenten'),
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

    $build['dashboard'] = $dashboard;
    unset($build['progress']);
    $build['cockpit']['#weight'] = 0;
    $build['tabs']['#weight'] = 10;

    return $build;
  }

  private function metricCard($label, $value, $meaning, string $route, array $parameters): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__metric']],
      'label' => ['#markup' => '<span class="brebo-project-dashboard__eyebrow">' . $label . '</span>'],
      'value' => ['#markup' => '<strong>' . ($value ?: '—') . '</strong>'],
      'meaning' => ['#markup' => '<span>' . ($meaning ?: '—') . '</span>'],
      'link' => ['#type' => 'link', '#title' => $this->t('Openen'), '#url' => Url::fromRoute($route, $parameters), '#attributes' => ['class' => ['brebo-project-dashboard__link']]],
    ];
  }

  private function panel($title, $body, string $route, array $parameters, $linkTitle): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__panel']],
      'title' => ['#markup' => '<h2>' . $title . '</h2>'],
      'body' => ['#markup' => '<p>' . $body . '</p>'],
      'link' => ['#type' => 'link', '#title' => $linkTitle, '#url' => Url::fromRoute($route, $parameters), '#attributes' => ['class' => ['brebo-project-dashboard__link']]],
    ];
  }

  private function projectTeamPanel(NodeInterface $project): array {
    $organization = $project->hasField('field_brebo_project_org_ref') ? $project->get('field_brebo_project_org_ref')->entity : NULL;
    $contacts = $project->hasField('field_brebo_project_contact_refs') ? $project->get('field_brebo_project_contact_refs')->referencedEntities() : [];
    $items = [];
    if ($organization instanceof NodeInterface) {
      $items[] = '<strong>' . $this->t('Opdrachtgever') . ':</strong> ' . $organization->label();
    }
    foreach (array_slice($contacts, 0, 3) as $contact) {
      if (!$contact instanceof NodeInterface) continue;
      $role = $contact->hasField('field_brebo_contact_role') ? trim((string) $contact->get('field_brebo_contact_role')->value) : '';
      $items[] = '<strong>' . $contact->label() . '</strong>' . ($role !== '' ? ' · ' . $role : '');
    }
    if ($items === []) $items[] = (string) $this->t('Nog geen canonieke opdrachtgever of projectcontactpersonen gekoppeld.');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-dashboard__panel']],
      'title' => ['#markup' => '<h2>' . $this->t('Projectorganisatie') . '</h2>'],
      'items' => ['#theme' => 'item_list', '#items' => $items],
      'link' => ['#type' => 'link', '#title' => $this->t('Project bewerken'), '#url' => Url::fromRoute('entity.node.edit_form', ['node' => (int) $project->id()]), '#attributes' => ['class' => ['brebo-project-dashboard__link']]],
    ];
  }

  private function rowValue(array $rows, string $label): string {
    return isset($rows[$label][1]) ? (string) $rows[$label][1] : '—';
  }

  private function rowMeaning(array $rows, string $label): string {
    if (!isset($rows[$label])) return '—';
    $row = $rows[$label];
    return trim(implode(' · ', array_filter([(string) ($row[1] ?? ''), (string) ($row[2] ?? '')])));
  }

  private function legacyController(): ProjectCockpitController {
    return ProjectCockpitController::create(\Drupal::getContainer());
  }

}
