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

/** Connects a mail communication to one canonical BREBO destination. */
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
    $currentContext = $node->hasField('field_brebo_comm_context') ? trim((string) $node->get('field_brebo_comm_context')->value) : '';
    $defaultTarget = $project instanceof NodeInterface ? 'project'
      : ($currentContext === 'Persoonlijk' ? 'personal' : 'administration');

    $return = Url::fromRoute('brebo_mail_intake.mail_context', ['node' => $node->id()])->toString();

    $form['intro'] = [
      '#markup' => '<p><strong>' . $this->t('Waar hoort deze e-mail thuis?') . '</strong><br>'
        . $this->t('Kies alleen de primaire bestemming. Gebouw, organisatie en contactpersoon zijn onderliggende relaties en worden niet als concurrerende bestemmingen aangeboden.') . '</p>',
    ];

    $form['target_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Bestemming'),
      '#options' => [
        'project' => $this->t('Project'),
        'administration' => $this->t('Administratie'),
        'personal' => $this->t('Persoonlijk'),
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
      '#description' => $this->t('Zoek eerst naar een bestaand project. Gebouw, organisatie en contactpersonen volgen als relaties vanuit het project en de communicatie.'),
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
      '#markup' => '<p class="description">' . $this->t('Bestaat het gebouw nog niet, maak het vanuit de projectaanmaak aan. Zo blijft de gebouwrelatie centraal op één plek.') . '</p>',
    ];

    $form['administration_note'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="target_type"]' => ['value' => 'administration']]],
      'text' => [
        '#markup' => '<p class="description">' . $this->t('Administratieve classificaties en relaties zoals organisatie en contactpersoon worden onder deze bestemming vastgelegd; ze zijn geen aparte hoofdbestemming.') . '</p>',
      ],
    ];

    $form['personal_note'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="target_type"]' => ['value' => 'personal']]],
      'text' => [
        '#markup' => '<p class="description">' . $this->t('Persoonlijk is bedoeld voor privécommunicatie en staat buiten het gedeelde zakelijke project- en administratiedossier.') . '</p>',
      ],
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
          '1 document krijgt bij bevestiging dezelfde primaire bestemming.',
          '@count documenten krijgen bij bevestiging dezelfde primaire bestemming.',
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
    if (!in_array($target, ['project', 'administration', 'personal'], TRUE)) {
      $form_state->setErrorByName('target_type', $this->t('Kies een geldige primaire bestemming.'));
      return;
    }
    if ($target === 'project' && (int) $form_state->getValue(['project_wrap', 'project']) <= 0) {
      $form_state->setErrorByName('project_wrap][project', $this->t('Kies een bestaand project of maak eerst een nieuw project aan.'));
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
      if ($node->hasField('field_brebo_comm_context')) {
        $node->set('field_brebo_comm_context', 'Project');
      }
    }
    elseif ($target === 'administration') {
      if ($node->hasField('field_brebo_project_ref')) {
        $node->set('field_brebo_project_ref', NULL);
      }
      if ($node->hasField('field_brebo_comm_context')) {
        $node->set('field_brebo_comm_context', 'Administratie');
      }
    }
    elseif ($target === 'personal') {
      if ($node->hasField('field_brebo_project_ref')) {
        $node->set('field_brebo_project_ref', NULL);
      }
      if ($node->hasField('field_brebo_comm_context')) {
        $node->set('field_brebo_comm_context', 'Persoonlijk');
      }
    }
    else {
      throw new \InvalidArgumentException('Onbekend mail contexttype.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Primaire mailbestemming handmatig bevestigd vanuit BREBO Mail.');
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

    $this->messenger()->addStatus($this->t('Communicatie en @count document(en) zijn aan de gekozen primaire bestemming gekoppeld.', [
      '@count' => count($documentIds),
    ]));

    $form_state->setRedirect('brebo_mail_intake.mailbox_root');
  }

}
