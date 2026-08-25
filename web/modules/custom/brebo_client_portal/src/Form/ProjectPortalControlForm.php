<?php

declare(strict_types=1);

namespace Drupal\brebo_client_portal\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Internal project control surface for the future client portal.
 */
final class ProjectPortalControlForm extends FormBase {

  public function __construct(
    private Connection $database,
    private ConfigFactoryInterface $configFactory,
    private EntityTypeManagerInterface $entityTypeManager,
    private TimeInterface $time,
    private UuidInterface $uuid,
    private DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('uuid'),
      $container->get('date.formatter'),
    );
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
      '#markup' => $this->configFactory->get('brebo_client_portal.settings')->get('global_enabled') ? $this->t('Ingeschakeld') : $this->t('Uitgeschakeld (veilig standaardgedrag)'),
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
    $form['access']['contacts'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Contactpersonen met toegang'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_contact']],
      '#tags' => TRUE,
      '#default_value' => $portalProjectId > 0 ? $this->loadGrantedContacts($portalProjectId) : [],
      '#description' => $this->t('Alleen actieve BREBO-contacten met een e-mailadres kunnen worden opgeslagen. Verwijderen uit deze lijst trekt de projecttoegang in.'),
    ];
    $form['access']['summary'] = [
      '#markup' => $portalProjectId > 0 ? $this->buildAccessSummary($portalProjectId) : '<p>Nog geen toegangsrechten.</p>',
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

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach ($this->selectedContactIds($form_state) as $contactId) {
      $contact = $this->entityTypeManager->getStorage('node')->load($contactId);
      if (!$contact instanceof NodeInterface || $contact->bundle() !== 'brebo_contact') {
        $form_state->setErrorByName('contacts', $this->t('Een geselecteerd item is geen geldig BREBO-contact.'));
        continue;
      }
      $email = $contact->hasField('field_brebo_contact_email') ? trim((string) $contact->get('field_brebo_contact_email')->value) : '';
      $active = !$contact->hasField('field_brebo_contact_active') || $contact->get('field_brebo_contact_active')->isEmpty() || (bool) $contact->get('field_brebo_contact_active')->value;
      if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$active) {
        $form_state->setErrorByName('contacts', $this->t('@contact moet actief zijn en een geldig e-mailadres hebben.', ['@contact' => $contact->label()]));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $now = $this->time->getRequestTime();
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
      $portalProjectId = (int) $existingId;
      $this->database->update('brebo_portal_project')->fields($fields)->condition('id', $portalProjectId)->execute();
    }
    else {
      $fields += ['uuid' => $this->uuid->generate(), 'project_id' => $projectId, 'created' => $now];
      $portalProjectId = (int) $this->database->insert('brebo_portal_project')->fields($fields)->execute();
    }

    $this->synchronizeAccessGrants($portalProjectId, $this->selectedContactIds($form_state), $now);
    $this->messenger()->addStatus($this->t('Klantportaalinstellingen en projecttoegang zijn opgeslagen.'));
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

  private function selectedContactIds(FormStateInterface $form_state): array {
    $ids = [];
    foreach ((array) $form_state->getValue('contacts', []) as $item) {
      if (is_array($item) && isset($item['target_id']) && is_numeric($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    return array_values(array_unique($ids));
  }

  private function synchronizeAccessGrants(int $portalProjectId, array $contactIds, int $now): void {
    $identityIds = [];
    foreach ($contactIds as $contactId) {
      $contact = $this->entityTypeManager->getStorage('node')->load($contactId);
      if (!$contact instanceof NodeInterface) {
        continue;
      }
      $email = strtolower(trim((string) $contact->get('field_brebo_contact_email')->value));
      $identityId = $this->database->select('brebo_portal_identity', 'i')->fields('i', ['id'])->condition('email_normalized', $email)->execute()->fetchField();
      if ($identityId === FALSE) {
        $identityId = $this->database->insert('brebo_portal_identity')->fields([
          'uuid' => $this->uuid->generate(), 'contact_id' => $contactId, 'email_normalized' => $email,
          'status' => 'pending', 'created' => $now, 'changed' => $now,
        ])->execute();
      }
      else {
        $this->database->update('brebo_portal_identity')->fields(['contact_id' => $contactId, 'changed' => $now])->condition('id', (int) $identityId)->execute();
      }
      $identityId = (int) $identityId;
      $identityIds[] = $identityId;
      $grantId = $this->database->select('brebo_portal_access_grant', 'g')->fields('g', ['id'])->condition('portal_project_id', $portalProjectId)->condition('identity_id', $identityId)->execute()->fetchField();
      if ($grantId === FALSE) {
        $this->database->insert('brebo_portal_access_grant')->fields([
          'uuid' => $this->uuid->generate(), 'portal_project_id' => $portalProjectId, 'identity_id' => $identityId,
          'status' => 'pending', 'capabilities_json' => json_encode(['project.view'], JSON_THROW_ON_ERROR),
          'invited_at' => $now, 'created' => $now, 'changed' => $now,
        ])->execute();
      }
      else {
        $this->database->update('brebo_portal_access_grant')->fields(['status' => 'pending', 'revoked_at' => NULL, 'changed' => $now])->condition('id', (int) $grantId)->execute();
      }
    }

    $query = $this->database->select('brebo_portal_access_grant', 'g')->fields('g', ['id'])->condition('portal_project_id', $portalProjectId);
    if ($identityIds !== []) {
      $query->condition('identity_id', $identityIds, 'NOT IN');
    }
    $revokeIds = $query->execute()->fetchCol();
    if ($revokeIds !== []) {
      $this->database->update('brebo_portal_access_grant')->fields(['status' => 'revoked', 'revoked_at' => $now, 'changed' => $now])->condition('id', array_map('intval', $revokeIds), 'IN')->execute();
    }
  }

  private function loadGrantedContacts(int $portalProjectId): array {
    $query = $this->database->select('brebo_portal_access_grant', 'g');
    $query->innerJoin('brebo_portal_identity', 'i', 'i.id = g.identity_id');
    $contactIds = $query->fields('i', ['contact_id'])
      ->condition('g.portal_project_id', $portalProjectId)
      ->condition('g.status', 'revoked', '<>')
      ->execute()->fetchCol();
    return $this->entityTypeManager->getStorage('node')->loadMultiple(array_map('intval', $contactIds));
  }

  private function buildAccessSummary(int $portalProjectId): string {
    $query = $this->database->select('brebo_portal_access_grant', 'g');
    $query->leftJoin('brebo_portal_identity', 'i', 'i.id = g.identity_id');
    $rows = $query->fields('g', ['status', 'valid_until', 'last_activity_at'])
      ->fields('i', ['email_normalized'])
      ->condition('g.portal_project_id', $portalProjectId)
      ->orderBy('i.email_normalized')
      ->execute()->fetchAll();
    if ($rows === []) {
      return '<p>Nog geen toegestane contactpersonen.</p>';
    }
    $items = [];
    foreach ($rows as $row) {
      $items[] = '<li>' . htmlspecialchars((string) $row->email_normalized, ENT_QUOTES, 'UTF-8') . ' — ' . htmlspecialchars((string) $row->status, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    return '<ul>' . implode('', $items) . '</ul>';
  }

  private function formatTimestamp(mixed $timestamp): string {
    if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
      return 'Nog geen activiteit';
    }
    return $this->dateFormatter->format((int) $timestamp, 'short');
  }

}
