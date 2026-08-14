<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Connects a mail communication to one canonical BREBO destination. */
final class MailContextForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly ?DocumentRepository $documents,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $mailContextRequestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->has('brebo_document_data.repository') ? $container->get('brebo_document_data.repository') : NULL,
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mail_context_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form['#attributes']['class'][] = 'brebo-mail-context-form';
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('communication_nid', (int) $node->id());
    $destination = (string) ($this->mailContextRequestStack->getCurrentRequest()?->query->get('destination', '') ?? '');
    $returnUrl = str_starts_with($destination, '/') && !str_starts_with($destination, '//')
      ? Url::fromUserInput($destination)
      : Url::fromRoute('brebo_mail_intake.mailbox_root');
    $form_state->set('return_path', $returnUrl->toString());
    $project = $node->hasField('field_brebo_project_ref') ? $node->get('field_brebo_project_ref')->entity : NULL;
    $currentContext = $node->hasField('field_brebo_comm_context') ? trim((string) $node->get('field_brebo_comm_context')->value) : '';
    $defaultTarget = $project instanceof NodeInterface ? 'project'
      : match ($currentContext) {
        'Administratie' => 'administration',
        'Persoonlijk' => 'personal',
        'Junk' => 'junk',
        default => NULL,
      };

    $return = Url::fromRoute('brebo_mail_intake.mail_context', ['node' => $node->id()], [
      'query' => ['destination' => $returnUrl->toString()],
    ])->toString();

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
        'personal' => $this->t('Privé'),
        'junk' => $this->t('Junk'),
      ],
      '#default_value' => $defaultTarget,
      '#required' => TRUE,
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
      '#description' => $this->t('Koppel projectmail alleen aan het project. Gebouw en andere projectrelaties worden vanuit dat project afgeleid en niet dubbel op de e-mail opgeslagen.'),
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
        '#markup' => '<p class="description">' . $this->t('Privé is bedoeld voor privécommunicatie en staat buiten het gedeelde zakelijke project- en administratiedossier.') . '</p>',
      ],
    ];

    $form['junk_note'] = [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="target_type"]' => ['value' => 'junk']]],
      'text' => [
        '#markup' => '<p class="description">' . $this->t('Junk heeft geen dossierwaarde. De e-mail blijft bewaard maar wordt naar Spam verplaatst en niet aan een project of documentcontext gekoppeld.') . '</p>',
      ],
    ];

    $documentIds = [];
    foreach (['brebo_document_communication', 'brebo_document_source'] as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }
      $ids = $this->database->select($table, 'd')
        ->fields('d', ['document_id'])
        ->condition('communication_nid', (int) $node->id())
        ->distinct()
        ->execute()
        ->fetchCol();
      foreach ($ids as $documentId) {
        $documentIds[(int) $documentId] = TRUE;
      }
    }
    $documentCount = count($documentIds);
    $form['documents'] = [
      '#type' => 'item',
      '#title' => $this->t('Geregistreerde bijlagen/documenten'),
      '#markup' => $this->documents === NULL
        ? $this->t('Documentregistratie is niet beschikbaar; de communicatiekoppeling kan wel worden opgeslagen.')
        : $this->formatPlural(
          $documentCount,
          '1 document wordt bij bevestiging volgens de gekozen bestemming verwerkt.',
          '@count documenten worden bij bevestiging volgens de gekozen bestemming verwerkt.',
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
      '#url' => $returnUrl,
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $target = (string) $form_state->getValue('target_type');
    if (!in_array($target, ['project', 'administration', 'personal', 'junk'], TRUE)) {
      $form_state->setErrorByName('target_type', $this->t('Kies een geldige primaire bestemming.'));
      return;
    }
    if ($target === 'project') {
      $projectId = (int) $form_state->getValue(['project_wrap', 'project']);
      $project = $projectId > 0 ? $this->entityTypeManager->getStorage('node')->load($projectId) : NULL;
      if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project' || !$project->access('view')) {
        $form_state->setErrorByName('project_wrap][project', $this->t('Kies een bestaand, toegankelijk BREBO-project of maak eerst een nieuw project aan.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->get('communication_nid');
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $target = (string) $form_state->getValue('target_type');
    $project = NULL;
    $projectId = 0;
    $buildingId = 0;
    if ($target === 'project') {
      $projectId = (int) $form_state->getValue(['project_wrap', 'project']);
      $project = $this->entityTypeManager->getStorage('node')->load($projectId);
      if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project' || !$project->access('view')) {
        $form_state->setErrorByName('project_wrap][project', $this->t('Het gekozen project is niet meer beschikbaar.'));
        return;
      }

    }
    elseif (!in_array($target, ['administration', 'personal', 'junk'], TRUE)) {
      throw new \InvalidArgumentException('Onbekend mail contexttype.');
    }

    $documentIds = [];
    if ($this->documents !== NULL) {
      foreach (['brebo_document_communication', 'brebo_document_source'] as $table) {
        if (!$this->database->schema()->tableExists($table)) {
          continue;
        }
        $ids = $this->database->select($table, 'd')
          ->fields('d', ['document_id'])
          ->condition('communication_nid', $nid)
          ->distinct()
          ->execute()
          ->fetchCol();
        foreach ($ids as $documentId) {
          $documentIds[(int) $documentId] = (int) $documentId;
        }
      }
    }

    $mailboxId = 0;
    if ($target === 'junk' && $this->database->schema()->tableExists('brebo_mailbox_message')) {
      $mailboxId = (int) $this->database->select('brebo_mailbox_message', 'bm')
        ->fields('bm', ['mailbox_id'])
        ->condition('communication_id', $nid)
        ->range(0, 1)
        ->execute()
        ->fetchField();
    }

    $transaction = $this->database->startTransaction();
    try {
      if ($node->hasField('field_brebo_project_ref')) {
        $node->set('field_brebo_project_ref', $projectId > 0 ? $projectId : NULL);
      }
      if ($node->hasField('field_brebo_building_ref')) {
        $node->set('field_brebo_building_ref', $buildingId > 0 ? $buildingId : NULL);
      }
      if ($node->hasField('field_brebo_comm_scope_target')) {
        $node->set('field_brebo_comm_scope_target', NULL);
      }
      if ($node->hasField('field_brebo_comm_context')) {
        $node->set('field_brebo_comm_context', match ($target) {
          'project' => 'Projectgericht',
          'administration' => 'Administratie',
          'personal' => 'Persoonlijk',
          'junk' => 'Junk',
        });
      }

      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Primaire mailbestemming handmatig bevestigd vanuit BREBO Mail.');
      $node->save();

      if ($target === 'junk' && $this->database->schema()->tableExists('brebo_mailbox_message')) {
        $this->database->update('brebo_mailbox_message')
          ->fields(['mail_state' => 'spam', 'is_read' => 1, 'needs_action' => 0, 'changed' => $this->time->getRequestTime()])
          ->condition('communication_id', $nid)
          ->execute();
      }

      if ($documentIds !== [] && $this->database->schema()->tableExists('brebo_document_context')) {
        $this->database->delete('brebo_document_context')
          ->condition('document_id', array_values($documentIds), 'IN')
          ->condition('relation_source', 'mail_manual_confirmation')
          ->execute();
      }

      if ($this->documents !== NULL && $projectId > 0) {
        $now = $this->time->getRequestTime();
        $uid = (int) $this->currentUser()->id();
        foreach ($documentIds as $documentId) {
          $this->documents->upsertContext($documentId, [
            'context_type' => 'project',
            'context_id' => $projectId,
            'relation_role' => 'supporting',
            'confidence' => 1.0,
            'relation_source' => 'mail_manual_confirmation',
            'review_status' => 'confirmed',
            'confirmed_by_uid' => $uid,
            'confirmed_at' => $now,
          ]);
        }
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $this->getLogger('brebo_mail_intake')->error('Mailkoppeling voor communicatie @nid mislukt: @message', [
        '@nid' => $nid,
        '@message' => $exception->getMessage(),
      ]);
      $this->messenger()->addError($this->t('De koppeling kon niet volledig worden opgeslagen. Er is niets gewijzigd.'));
      return;
    }

    $message = $projectId > 0
      ? $this->t('Communicatie en @count document(en) zijn aan project @project gekoppeld.', [
        '@count' => count($documentIds),
        '@project' => $project?->label() ?? '',
      ])
      : $this->t('Communicatie is als @destination opgeslagen. @count document(en) blijven via deze communicatie vindbaar, zonder kunstmatige objectkoppeling.', [
        '@destination' => match ($target) {
          'personal' => $this->t('Privé'),
          'junk' => $this->t('Junk en naar Spam verplaatst'),
          default => $this->t('Administratie'),
        },
        '@count' => count($documentIds),
      ]);
    $this->messenger()->addStatus($message);

    if ($target === 'junk' && $mailboxId > 0) {
      $form_state->setRedirect('brebo_mail_intake.mailbox_message', [
        'mailbox_id' => $mailboxId,
        'mail_state' => 'spam',
        'communication_id' => $nid,
      ]);
      return;
    }

    $returnPath = (string) $form_state->get('return_path');
    $form_state->setRedirectUrl(
      str_starts_with($returnPath, '/') && !str_starts_with($returnPath, '//')
        ? Url::fromUserInput($returnPath)
        : Url::fromRoute('brebo_mail_intake.mailbox_root'),
    );
  }
}
