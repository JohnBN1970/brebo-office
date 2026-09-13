<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\AdministrationContextResolver;
use Drupal\brebo_office_core\Service\AdministrationRegistry;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Assigns the legal/financial administration for a project. */
final class ProjectAdministrationForm extends FormBase {

  private ?NodeInterface $project = NULL;

  public function __construct(
    private readonly AdministrationRegistry $registry,
    private readonly AdministrationContextResolver $resolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_office_core.administration_registry'),
      $container->get('brebo_office_core.administration_context_resolver'),
    );
  }

  public function getFormId(): string {
    return 'brebo_office_core_project_administration';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
    $this->project = $node;

    $options = [];
    foreach ($this->registry->all() as $code => $administration) {
      if (empty($administration['active'])) {
        continue;
      }
      $options[$code] = (string) ($administration['trade_name'] ?? $administration['legal_name'] ?? $code);
    }

    $form['intro'] = [
      '#markup' => '<p>De gekozen administratie bepaalt juridische identiteit, logo, bankgegevens, fiscale context en documentnummering voor dit project en alle daaruit afgeleide stukken.</p>',
    ];
    $form['administration'] = [
      '#type' => 'select',
      '#title' => $this->t('Administratie'),
      '#options' => $options,
      '#default_value' => $this->resolver->projectCode($node),
      '#required' => TRUE,
    ];
    $form['inheritance'] = [
      '#type' => 'item',
      '#title' => $this->t('Overerving'),
      '#markup' => $this->t('Werkpakketten, calculaties, offertes, contracten, facturen en overige projectdocumenten erven deze administratie. Bestaande definitieve snapshots worden niet achteraf gewijzigd.'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Administratie opslaan'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->project instanceof NodeInterface) {
      throw new \LogicException('Project context ontbreekt.');
    }
    $code = (string) $form_state->getValue('administration');
    $this->resolver->assignProject($this->project, $code);
    $administration = $this->registry->get($code);
    $this->messenger()->addStatus($this->t('Administratie ingesteld op @name.', ['@name' => (string) ($administration['trade_name'] ?? $code)]));
    $form_state->setRedirect('brebo_office_core.project_dashboard', ['node' => $this->project->id()]);
  }

}
