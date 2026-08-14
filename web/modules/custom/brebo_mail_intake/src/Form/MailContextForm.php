<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Connects a mail communication to one canonical BREBO business context. */
final class MailContextForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly ?DocumentRepository $documents,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->has('brebo_document_data.repository') ? $container->get('brebo_document_data.repository') : NULL,
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mail_context_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('communication_nid', (int) $node->id());
    $project = $node->hasField('field_brebo_project_ref') ? $node->get('field_brebo_project_ref')->entity : NULL;
    $organization = $node->hasField('field_brebo_comm_org_ref') ? $node->get('field_brebo_comm_org_ref')->entity : NULL;
    $contact = $node->hasField('field_brebo_comm_contact_ref') ? $node->get('field_brebo_comm_contact_ref')->entity : NULL;

    $defaultTarget = $project instanceof NodeInterface ? 'project'
      : ($organization instanceof NodeInterface ? 'organization'
        : ($contact instanceof NodeInterface ? 'contact' : 'project'));

    $return = Url::fromRoute('brebo_mail_intake.mail_context', ['node' => $node->id()])->toString();

    $form['intro'] = [
      '#markup' => '<p><strong>' . $this->t('Waar hoort deze e-mail zakelijk thuis?') . '</strong><br>'
        . $this->t('Koppel aan één primaire context. Bij projectmail loopt het gebouw via het project; het gebouw wordt hier dus niet nogmaals handmatig gekoppeld.') . '</p>',
    ];

    $form['target_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Koppelen aan'),
      '#options' => [
        'project' => $this->t('Project'),
        'organization' => $this->t('Organisatie'),
        'contact' => $this->t('Contactpersoon'),
        'brebo' => $this->t('BREBO algemeen / intern'),
      ],
      '#default_value' => $defaultTarget,
    ];

    $form['project_wrap'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="target_type"]' => ['value' => 'project']]],
    ];
    $form['project_wrap']['project'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Project'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_project']],
      '#default_value' => $project,
      '#description' => $this->t('Zoek eerst naar een bestaand project. Het gebouw volgt uit de projecthiërarchie.'),
    ];
    $form['project_wrap']['new_project'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Nieuw project aanmaken'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project'], [
        'query' => ['destination' => $return],
      ]),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];
    $form['project_wrap']['project_note'] = [
      '#markup' => '<p class="description">' . $this->t('Bestaat ook het gebouw nog niet, maak dat aan vanuit de projectaanmaak. Zo blijft de gebouwrelatie op één centrale plek.') . '</p>',
    ];

    $form['organization_wrap'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="target_type"]' => ['value' => 'organization']]],
    ];
    $form['organization_wrap']['organization'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Organisatie'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_organization']],
      '#default_value' => $organization,
    ];
    $form['organization_wrap']['new_organization'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Nieuwe organisatie aanmaken'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_organization'], [
        'query' => ['destination' => $return],
      ]),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    $form['contact_wrap'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="target_type"]' => ['value' => 'contact']]],
    ];
    $form['contact_wrap']['contact'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Contactpersoon'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_contact']],
      '#default_value' => $contact,
    ];
    $form['contact_wrap']['new_contact'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Nieuw contact aanmaken'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_contact'], [
        'query' => ['destination' => $return],
      ]),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    $documentCount = 0;
    if ($this->database->schema()->tableExists('brebo_document_source')) {
      $documentCount = (int) $this->database->select('brebo_document_source', 's')
        ->condition('communication_nid', (int) $node->id())
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    $form['documents'] = [
      '#type' => 'item',
      '#title' => $this->t('Geregistreerde bijlagen/documenten'),
      '#markup' => $this->documents === NULL
        ? $this->t('Documentregistratie is niet beschikbaar; de communicatiekoppeling kan wel worden opgeslagen.')
        : $this->formatPlural(
          $documentCount,
          '1 document krijgt bij bevestiging dezelfde primaire context.',
          '@count documenten krijgen bij bevestiging dezelfde primaire context.',
        ),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Koppeling bevestigen'),
      '#button_type' => 'primary',
    ];
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Terug naar Mail'),
      '#url' => Url::fromRoute('brebo_mail_intake.mailbox_root'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $target = (string) $form_state->getValue('target_type');
    $value = match ($target) {
      'project' => (int) $form_state->getValue(['project_wrap', 'project']),
      'organization' => (int) $form_state->getValue(['organization_wrap', 'organization']),
      'contact' => (int) $form_state->getValue(['contact_wrap', 'contact']),
      'brebo' => 1,
      default => 0,
    };
    if ($value <= 0) {
      $form_state->setErrorByName('target_type', $this->t('Kies een geldige primaire context.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->get('communication_nid');
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $target = (string) $form_state->getValue('target_type');
    $contextId = 0;
    $contextType = $target;

    if ($target === 'project') {
      $contextId = (int) $form_state->getValue(['project_wrap', 'project']);
      if ($node->hasField('field_brebo_project_ref')) {
        $node->set('field_brebo_project_ref', $contextId);
      }
    }
    elseif ($target === 'organization') {
      $contextId = (int) $form_state->getValue(['organization_wrap', 'organization']);
      if ($node->hasField('field_brebo_comm_org_ref')) {
        $node->set('field_brebo_comm_org_ref', $contextId);
      }
    }
    elseif ($target === 'contact') {
      $contextId = (int) $form_state->getValue(['contact_wrap', 'contact']);
      if ($node->hasField('field_brebo_comm_contact_ref')) {
        $node->set('field_brebo_comm_contact_ref', $contextId);
      }
    }
    elseif ($target === 'brebo') {
      $contextId = 0;
      if ($node->hasField('field_brebo_comm_context')) {
        $node->set('field_brebo_comm_context', 'BREBO algemeen / intern');
      }
    }
    else {
      throw new \InvalidArgumentException('Onbekend mail contexttype.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Primaire zakelijke context handmatig bevestigd vanuit BREBO Mail.');
    $node->save();

    $documentIds = [];
    if ($this->documents !== NULL && $this->database->schema()->tableExists('brebo_document_source')) {
      $documentIds = $this->database->select('brebo_document_source', 's')
        ->fields('s', ['document_id'])
        ->condition('communication_nid', $nid)
        ->distinct()
        ->execute()
        ->fetchCol();
    }

    if ($this->documents !== NULL) {
      $now = $this->time->getRequestTime();
      $uid = (int) $this->currentUser()->id();
      foreach (array_map('intval', $documentIds) as $documentId) {
        $this->documents->upsertContext($documentId, [
          'context_type' => $contextType,
          'context_id' => $contextId,
          'relation_role' => 'supporting',
          'confidence' => 1.0,
          'relation_source' => 'mail_manual_confirmation',
          'review_status' => 'confirmed',
          'confirmed_by_uid' => $uid,
          'confirmed_at' => $now,
        ]);
      }
    }

    $this->messenger()->addStatus($this->t('Communicatie en @count document(en) zijn aan de gekozen primaire context gekoppeld.', [
      '@count' => count($documentIds),
    ]));

    $form_state->setRedirect('brebo_mail_intake.mailbox_root');
  }

}
