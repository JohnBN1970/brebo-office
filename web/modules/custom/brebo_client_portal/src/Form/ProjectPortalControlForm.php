<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Internal project control surface for the future client portal.
 */
final class ProjectPortalControlForm extends FormBase {

  public function __construct(
    private Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_client_portal_project_control';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $projectId = (int) $node->id();
    $record = $this->database->select('brebo_portal_project', 'p')
      ->fields('p')
      ->condition('project_id', $projectId)
      ->execute()
      ->fetchAssoc() ?: [];

    $form_state->set('project_id', $projectId);
    $form['notice'] = [
      '#markup' => '<p><strong>Intern beheer.</strong> Deze pagina ontsluit geen publiek klantportaal. Externe toegang blijft bovendien afhankelijk van de globale feature flag.</p>',
    ];
    $form['global_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Globale portalstatus'),
      '#markup' => Settings::get('brebo_client_portal_enabled', FALSE) ? $this->t('Ingeschakeld') : $this->t('Uitgeschakeld (veilig standaardgedrag)'),
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Klantportaal voor dit project voorbereiden'),
      '#default_value' => (bool) ($record['enabled'] ?? FALSE),
      '#description' => $this->t('Dit maakt alleen de projectconfiguratie actief. Het creëert geen publieke route of login.'),
    ];
    $form['public_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Titel voor klant'),
      '#default_value' => (string) ($record['public_title'] ?? $node->label()),
      '#maxlength' => 255,
    ];
    $form['public_status'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Status voor klant'),
      '#default_value' => (string) ($record['public_status'] ?? ''),
      '#maxlength' => 64,
    ];

    $visible = json_decode((string) ($record['visible_sections_json'] ?? '[]'), TRUE);
    $visible = is_array($visible) ? array_values(array_intersect($visible, array_keys(self::sectionOptions()))) : [];
    $form['visible_sections'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Zichtbare onderdelen'),
      '#options' => self::sectionOptions(),
      '#default_value' => $visible,
      '#description' => $this->t('Alleen expliciet geselecteerde, later gepubliceerde klantprojecties mogen extern zichtbaar worden.'),
    ];

    $portalProjectId = isset($record['id']) ? (int) $record['id'] : 0;
    $form['access'] = [
      '#type' => 'details',
      '#title' => $this->t('Toegestane contactpersonen'),
      '#open' => TRUE,
    ];
    $form['access']['summary'] = [
      '#markup' => $portalProjectId > 0 ? $this->buildAccessSummary($portalProjectId) : '<p>Nog geen portalprojectrecord; er zijn geen toegangsrechten.</p>',
    ];
    $form['activity'] = [
      '#type' => 'details',
      '#title' => $this->t('Activiteit'),
      '#open' => TRUE,
      'last_customer' => ['#type' => 'item', '#title' => $this->t('Laatste klantactiviteit'), '#markup' => $this->formatTimestamp($record['last_customer_activity'] ?? NULL)],
      'last_publication' => ['#type' => 'item', '#title' => $this->t('Laatste publicatie'), '#markup' => $this->formatTimestamp($record['last_published_at'] ?? NULL)],
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Instellingen opslaan'), '#button_type' => 'primary'];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $now = $this->time()->getRequestTime();
    $sections = array_values(array_filter($form_state->getValue('visible_sections', []), static fn ($value): bool => is_string($value) && $value !== '0'));
    $sections = array_values(array_intersect($sections, array_keys(self::sectionOptions())));

    $fields = [
      'enabled' => $form_state->getValue('enabled') ? 1 : 0,
      'public_title' => trim((string) $form_state->getValue('public_title')),
      'public_status' => trim((string) $form_state->getValue('public_status')),
      'visible_sections_json' => json_encode($sections, JSON_THROW_ON_ERROR),
      'changed' => $now,
    ];

    $existingId = $this->database->select('brebo_portal_project', 'p')->fields('p', ['id'])->condition('project_id', $projectId)->execute()->fetchField();
    if ($existingId !== FALSE) {
      $this->database->update('brebo_portal_project')->fields($fields)->condition('id', (int) $existingId)->execute();
    }
    else {
      $fields += ['uuid' => $this->uuidGenerator()->generate(), 'project_id' => $projectId, 'created' => $now];
      $this->database->insert('brebo_portal_project')->fields($fields)->execute();
    }

    $this->messenger()->addStatus($this->t('Klantportaalinstellingen voor dit project zijn opgeslagen.'));
  }

  private static function sectionOptions(): array {
    return [
      'overview' => 'Projectoverzicht',
      'planning' => 'Planning en mijlpalen',
      'documents' => 'Gepubliceerde documenten',
      'quality' => 'Kwaliteit en oplevering',
      'messages' => 'Klantcommunicatie',
    ];
  }

  private function buildAccessSummary(int $portalProjectId): string {
    $query = $this->database->select('brebo_portal_access_grant', 'g');
    $query->leftJoin('brebo_portal_identity', 'i', 'i.id = g.identity_id');
    $rows = $query->fields('g', ['status', 'valid_until', 'last_activity_at'])
      ->fields('i', ['contact_id', 'email_normalized'])
      ->condition('g.portal_project_id', $portalProjectId)
      ->orderBy('i.email_normalized')
      ->execute()
      ->fetchAllAssoc('email_normalized');
    if ($rows === []) {
      return '<p>Nog geen toegestane contactpersonen.</p>';
    }

    $items = [];
    foreach ($rows as $row) {
      $email = htmlspecialchars((string) $row->email_normalized, ENT_QUOTES, 'UTF-8');
      $status = htmlspecialchars((string) $row->status, ENT_QUOTES, 'UTF-8');
      $items[] = '<li>' . $email . ' — ' . $status . '</li>';
    }
    return '<ul>' . implode('', $items) . '</ul>';
  }

  private function formatTimestamp(mixed $timestamp): string {
    if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
      return 'Nog geen activiteit';
    }
    return $this->dateFormatter()->format((int) $timestamp, 'short');
  }

}
