<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Contract\RecipeRepositoryInterface;
use Drupal\brebo_calculation\Service\CalculationWorkspaceStateService;
use Drupal\brebo_calculation\Service\RecipeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Places a published reusable recipe into a calculation leaf paragraph. */
final class RecipePlacementForm extends FormBase {

  public function __construct(
    private readonly CalculationWorkspaceStateService $workspaceState,
    private readonly RecipeRepositoryInterface $recipeRepository,
    private readonly RecipeManager $recipeManager,
    private readonly RequestStack $recipeRequestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.workspace_state'),
      $container->get('brebo_calculation.recipe_repository'),
      $container->get('brebo_calculation.recipe_manager'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_recipe_placement_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $calculation = NULL): array {
    $calculationId = (int) $calculation;
    if ($calculationId <= 0) {
      return ['message' => ['#markup' => '<p>Calculatie niet gevonden.</p>']];
    }

    try {
      $state = $this->workspaceState->state($calculationId);
    }
    catch (\RuntimeException) {
      return ['message' => ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie.</p>']];
    }

    $version = is_array($state['version'] ?? NULL) ? $state['version'] : NULL;
    if ($version === NULL) {
      return ['message' => ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie.</p>']];
    }

    $form['#tree'] = TRUE;
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => $calculationId];
    $form['calculation_version'] = ['#type' => 'hidden', '#value' => (string) $version['version']];

    $form['intro'] = [
      '#markup' => '<div class="brebo-calc-empty-state"><strong>Recept plaatsen</strong><p>Kies een gepubliceerd recept, een eindparagraaf en de hoeveelheid. Het recept wordt als versievaste snapshot geplaatst; latere wijzigingen in de bibliotheek veranderen deze calculatie niet stilzwijgend.</p></div>',
    ];

    $form['back'] = [
      '#type' => 'link',
      '#title' => 'Terug naar calculatiewerkbank',
      '#url' => Url::fromRoute('brebo_calculation.workbench', ['calculation' => $calculationId]),
      '#attributes' => ['class' => ['button']],
    ];

    if (empty($state['editable'])) {
      $form['readonly'] = ['#markup' => '<p><strong>Deze calculatieversie is niet bewerkbaar. Recepten kunnen alleen in een ontgrendelde conceptversie worden geplaatst.</strong></p>'];
      return $form;
    }

    $paragraphs = $this->leafParagraphOptions((array) ($state['structure'] ?? []));
    $recipes = $this->publishedRecipeOptions();

    if ($paragraphs === []) {
      $form['missing_structure'] = ['#markup' => '<p>Maak eerst minimaal één eindparagraaf in de calculatiestructuur.</p>'];
      return $form;
    }
    if ($recipes === []) {
      $form['missing_recipes'] = ['#markup' => '<p>Er zijn nog geen gepubliceerde receptversies beschikbaar.</p>'];
      return $form;
    }

    $selectedVersion = (int) ($form_state->getValue(['recipe', 'version_id']) ?: array_key_first($recipes));
    $requestedParagraph = trim((string) $this->recipeRequestStack->getCurrentRequest()?->query->get('paragraph', ''));
    $selectedParagraph = (string) ($form_state->getValue(['recipe', 'paragraph_key']) ?: (isset($paragraphs[$requestedParagraph]) ? $requestedParagraph : array_key_first($paragraphs)));

    $form['recipe'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'brebo-recipe-placement'],
    ];
    $form['recipe']['version_id'] = [
      '#type' => 'select',
      '#title' => 'Recept',
      '#options' => $recipes,
      '#default_value' => $selectedVersion,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => 'brebo-recipe-placement',
      ],
    ];
    $form['recipe']['paragraph_key'] = [
      '#type' => 'select',
      '#title' => 'Eindparagraaf',
      '#options' => $paragraphs,
      '#default_value' => $selectedParagraph,
      '#required' => TRUE,
    ];
    $form['recipe']['quantity'] = [
      '#type' => 'number',
      '#title' => 'Hoeveelheid',
      '#default_value' => 1,
      '#min' => 0,
      '#step' => '0.0001',
      '#required' => TRUE,
    ];

    $parameters = $this->recipeRepository->parameters($selectedVersion);
    if ($parameters !== []) {
      $form['recipe']['parameters'] = [
        '#type' => 'details',
        '#title' => 'Receptparameters',
        '#open' => TRUE,
      ];
      foreach ($parameters as $parameter) {
        $key = (string) $parameter['parameter_key'];
        $label = (string) $parameter['label'];
        if (!empty($parameter['unit'])) {
          $label .= ' (' . $parameter['unit'] . ')';
        }
        $form['recipe']['parameters'][$key] = [
          '#type' => 'number',
          '#title' => $label,
          '#default_value' => $parameter['default_value'] !== NULL && $parameter['default_value'] !== '' ? $parameter['default_value'] : NULL,
          '#step' => '0.0001',
          '#required' => (bool) $parameter['required'] && trim((string) ($parameter['formula'] ?? '')) === '',
          '#description' => trim((string) ($parameter['formula'] ?? '')) !== '' ? 'Wordt automatisch berekend wanneer geen waarde is ingevuld.' : NULL,
        ];
      }
    }

    $form['recipe']['actions'] = ['#type' => 'actions'];
    $form['recipe']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Recept in calculatie plaatsen',
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('recipe');
    $parameterValues = [];
    foreach (($values['parameters'] ?? []) as $key => $value) {
      if ($value !== NULL && $value !== '') {
        $parameterValues[(string) $key] = $value;
      }
    }

    $instanceId = $this->recipeManager->placeRecipe(
      (int) $form_state->getValue('calculation_id'),
      (string) $form_state->getValue('calculation_version'),
      (string) $values['paragraph_key'],
      (int) $values['version_id'],
      (float) $values['quantity'],
      $parameterValues,
      (int) $this->currentUser()->id(),
    );

    $this->messenger()->addStatus($this->t('Recept geplaatst als calculatieblok @id.', ['@id' => $instanceId]));
    $form_state->setRedirect('brebo_calculation.workbench', ['calculation' => (int) $form_state->getValue('calculation_id')]);
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    return $form['recipe'];
  }

  /** @param list<array<string,mixed>> $records @return array<string,string> */
  private function leafParagraphOptions(array $records): array {
    $parents = [];
    foreach ($records as $record) {
      if (!empty($record['parent_key'])) {
        $parents[(string) $record['parent_key']] = TRUE;
      }
    }

    $options = [];
    foreach ($records as $record) {
      $key = (string) ($record['node_key'] ?? '');
      if ($key === '' || (string) ($record['node_type'] ?? '') !== 'paragraph' || isset($parents[$key])) {
        continue;
      }
      $indent = str_repeat('— ', max(0, (int) ($record['depth'] ?? 0) - 1));
      $code = trim((string) ($record['code'] ?? ''));
      $options[$key] = $indent . ($code !== '' ? $code . ' · ' : '') . (string) ($record['label'] ?? $key);
    }
    return $options;
  }

  /** @return array<int,string> */
  private function publishedRecipeOptions(): array {
    $options = [];
    foreach ($this->recipeRepository->publishedVersions() as $record) {
      $options[(int) $record['id']] = $record['recipe_name'] . ' · v' . $record['version'] . ' · per ' . $record['base_unit'];
    }
    return $options;
  }

}
