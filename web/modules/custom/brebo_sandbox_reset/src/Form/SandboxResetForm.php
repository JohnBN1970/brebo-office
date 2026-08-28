<?php

declare(strict_types=1);

namespace Drupal\brebo_sandbox_reset\Form;

use Drupal\brebo_sandbox_reset\Service\SandboxResetManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Admin-only sandbox content reset form. */
final class SandboxResetForm extends FormBase {

  public function __construct(
    private readonly SandboxResetManager $resetManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_sandbox_reset.manager'));
  }

  public function getFormId(): string {
    return 'brebo_sandbox_reset_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $scope = (string) ($form_state->getValue('scope') ?: 'mail_content');
    $preview = $this->resetManager->preview($scope);

    $form['warning'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning"><strong>Alleen gekozen bedrijfsinhoud wordt gewist.</strong> Software, database-schema, velden, rollen, workflows en configuratie blijven intact. Externe bronnen zoals Zoho en Moneybird worden nooit gewijzigd.</div>',
    ];

    $form['scope'] = [
      '#type' => 'radios',
      '#title' => $this->t('Resetbereik'),
      '#options' => [
        'mail_content' => $this->t('Mail-inhoud wissen — broncursors behouden'),
        'mail_content_zoho' => $this->t('Mail-inhoud wissen + Zoho pilot/migratiestate terug naar start'),
        'projects' => $this->t('Alle projecten wissen — overige inhoud behouden'),
        'buildings' => $this->t('Alle gebouwen wissen — overige inhoud behouden'),
        'projects_buildings' => $this->t('Alle projecten + gebouwen wissen — overige inhoud behouden'),
      ],
      '#default_value' => $scope,
      '#required' => TRUE,
      '#description' => $this->t('Bij project/gebouw-reset worden verwijzingen vanuit andere records eerst veilig losgekoppeld. Mail en andere bedrijfsinhoud blijven bestaan.'),
    ];

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Wat wordt geraakt?'),
      '#open' => TRUE,
    ];
    $form['preview']['counts'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('@count via Mail Intake geregistreerde e-mailcommunicaties', ['@count' => $preview['mail_communications']]),
        $this->t('@count mailboxprojecties', ['@count' => $preview['mailbox_rows']]),
        $this->t('@count projecten in gekozen reset', ['@count' => $preview['projects']]),
        $this->t('@count gebouwen in gekozen reset', ['@count' => $preview['buildings']]),
        $this->t('@count verwijzende records/velden worden eerst losgekoppeld', ['@count' => $preview['object_references']]),
        $this->t('@count onbevestigde adres-/scopevoorstellen worden bij object-reset gewist', ['@count' => $preview['address_scope_proposals']]),
      ],
    ];
    $form['preview']['kept'] = [
      '#markup' => '<p><strong>Blijft staan:</strong> software, schema, configuratie, mailboxdefinities, gebruikers/rollen en externe bronmail. Een project/gebouw-reset verwijdert geen Communications; die worden alleen losgekoppeld van verwijderde objecten.</p>',
    ];

    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ik begrijp dat de inhoud binnen het gekozen resetbereik definitief uit BREBO Office wordt verwijderd.'),
      '#required' => TRUE,
    ];
    $form['confirmation_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Typ WIS INHOUD ter bevestiging'),
      '#required' => TRUE,
      '#size' => 24,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Gekozen inhoud resetten'),
      '#button_type' => 'danger',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('confirmation_text')) !== 'WIS INHOUD') {
      $form_state->setErrorByName('confirmation_text', $this->t('Bevestiging klopt niet; typ exact WIS INHOUD.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->resetManager->reset((string) $form_state->getValue('scope'));
    $this->messenger()->addStatus($this->t(
      'Reset voltooid: @projects projecten, @buildings gebouwen en @communications mailcommunicaties binnen het gekozen bereik verwijderd. Software en configuratie zijn behouden.',
      [
        '@projects' => $result['projects'],
        '@buildings' => $result['buildings'],
        '@communications' => $result['mail_communications'],
      ],
    ));
  }

}
