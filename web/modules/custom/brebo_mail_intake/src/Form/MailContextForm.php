<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Connects a mail communication to canonical BREBO context. */
final class MailContextForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DocumentRepository $documents,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_document_data.repository'),
      $container->get('datetime.time'),
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
    $building = $node->hasField('field_brebo_building_ref') ? $node->get('field_brebo_building_ref')->entity : NULL;

    $return = Url::fromRoute('brebo_mail_intake.mail_context', ['node' => $node->id()])->toString();

    $form['intro'] = [
      '#markup' => '<p><strong>' . $this->t('Koppel deze communicatie aan de canonieke BREBO-context.') . '</strong><br>'
        . $this->t('Bestaande objecten worden hergebruikt. Maak alleen een nieuw gebouw of project aan wanneer het nog niet bestaat.') . '</p>',
    ];

    $form['project'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Project'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_project']],
      '#default_value' => $project,
      '#description' => $this->t('Zoek eerst naar een bestaand project.'),
    ];
    $form['new_project'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Nieuw project aanmaken'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_project'], [
        'query' => ['destination' => $return],
      ]),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    $form['building'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Gebouw'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_building']],
      '#default_value' => $building,
      '#description' => $this->t('Het gebouw kan naast het project rechtstreeks aan de communicatie worden gekoppeld.'),
    ];
    $form['new_building'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Nieuw gebouw aanmaken'),
      '#url' => Url::fromRoute('node.add', ['node_type' => 'brebo_building'], [
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
      '#markup' => $this->formatPlural(
        $documentCount,
        '1 document wordt bij bevestiging aan dezelfde context gekoppeld.',
        '@count documenten worden bij bevestiging aan dezelfde context gekoppeld.',
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
    if ((int) $form_state->getValue('project') <= 0 && (int) $form_state->getValue('building') <= 0) {
      $form_state->setErrorByName('project', $this->t('Kies minimaal een project of gebouw.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->get('communication_nid');
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $projectId = (int) $form_state->getValue('project');
    $buildingId = (int) $form_state->getValue('building');

    if ($projectId > 0 && $node->hasField('field_brebo_project_ref')) {
      $node->set('field_brebo_project_ref', $projectId);
    }
    if ($buildingId > 0 && $node->hasField('field_brebo_building_ref')) {
      $node->set('field_brebo_building_ref', $buildingId);
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Project-/gebouwcontext handmatig bevestigd vanuit BREBO Mail.');
    $node->save();

    $documentIds = [];
    if ($this->database->schema()->tableExists('brebo_document_source')) {
      $documentIds = $this->database->select('brebo_document_source', 's')
        ->fields('s', ['document_id'])
        ->condition('communication_nid', $nid)
        ->distinct()
        ->execute()
        ->fetchCol();
    }

    $now = $this->time->getRequestTime();
    $uid = (int) $this->currentUser()->id();
    foreach (array_map('intval', $documentIds) as $documentId) {
      foreach (['project' => $projectId, 'building' => $buildingId] as $type => $contextId) {
        if ($contextId <= 0) {
          continue;
        }
        $this->documents->upsertContext($documentId, [
          'context_type' => $type,
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

    $this->messenger()->addStatus($this->t('Communicatie en @count document(en) zijn aan de gekozen context gekoppeld.', [
      '@count' => count($documentIds),
    ]));

    $form_state->setRedirect('brebo_mail_intake.mailbox_root');
  }

}
