<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a version-bound commercial offer from a calculation.
 */
final class OfferVersionForm extends FormBase {

  private ?NodeInterface $calculation = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_office_offer_version';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update') || !$this->currentUser()->hasPermission('create brebo_offer_version content')) {
      throw new AccessDeniedHttpException();
    }

    $this->calculation = $node;
    $storage = $this->entityTypeManager->getStorage('node');
    $existing_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_offer_version')
      ->condition('field_brebo_calculation_ref.target_id', $node->id())
      ->execute();
    $next_version = 1;
    foreach ($storage->loadMultiple($existing_ids) as $offer) {
      if ($offer instanceof NodeInterface) {
        $next_version = max($next_version, ((int) ($offer->get('field_brebo_offer_version')->value ?? 0)) + 1);
      }
    }

    $calculation_code = (string) ($node->get('field_brebo_calc_code')->value ?? ('CALC-' . $node->id()));
    $submitted_input = $form_state->getUserInput();
    $preserved_values = (array) ($form_state->get('offer_form_values') ?? []);
    $form_value = static function (string $key, mixed $default = NULL) use ($submitted_input, $preserved_values): mixed {
      // During a generator rebuild, the preserved state contains the complete
      // submitted form plus the newly generated texts. It must take precedence
      // over the original POST payload, which still contains the old defaults.
      if (array_key_exists($key, $preserved_values)) {
        return $preserved_values[$key];
      }
      return $submitted_input[$key] ?? $default;
    };

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Maak een vaste commerciële offerteversie. Na opslaan blijven layout, teksten en fiscale instellingen gekoppeld aan deze versie.') . '</p>',
    ];
    $form['identity'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Offerteversie'),
    ];
    $form['identity']['offer_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Offertenummer'),
      '#required' => TRUE,
      '#default_value' => $form_value('offer_number', $calculation_code . '-OFF-' . str_pad((string) $next_version, 2, '0', STR_PAD_LEFT)),
      '#maxlength' => 64,
    ];
    $form['identity']['offer_version'] = [
      '#type' => 'number',
      '#title' => $this->t('Versienummer'),
      '#required' => TRUE,
      '#default_value' => $form_value('offer_version', $next_version),
      '#min' => 1,
    ];
    $form['identity']['offer_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#required' => TRUE,
      '#options' => [
        'Concept' => $this->t('Concept'),
        'Vastgesteld' => $this->t('Vastgesteld'),
        'Verzonden' => $this->t('Verzonden'),
        'Geaccepteerd' => $this->t('Geaccepteerd'),
        'Afgewezen' => $this->t('Afgewezen'),
        'Vervallen' => $this->t('Vervallen'),
      ],
      '#default_value' => $form_value('offer_status', 'Concept'),
    ];
    $form['identity']['valid_until'] = [
      '#type' => 'date',
      '#title' => $this->t('Geldig tot'),
      '#default_value' => $form_value('valid_until', ''),
    ];

    $form['presentation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Presentatie'),
    ];
    $form['presentation']['offer_layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Offertelayout'),
      '#required' => TRUE,
      '#options' => [
        'Zakelijk' => $this->t('Zakelijk'),
        'Compact' => $this->t('Compacte prijsopgave'),
        'Technisch' => $this->t('Uitgebreide technische aanbieding'),
        'Aanbesteding' => $this->t('Aanbestedings-/begrotingsstaat'),
        'VvE' => $this->t('VvE-/bewonersvriendelijk'),
        'Maatwerk' => $this->t('Maatwerk'),
      ],
      '#default_value' => $form_value('offer_layout', 'Zakelijk'),
    ];
    $form['presentation']['price_detail'] = [
      '#type' => 'select',
      '#title' => $this->t('Prijsdetailniveau'),
      '#required' => TRUE,
      '#options' => [
        'Gesloten' => $this->t('Gesloten aanbieding'),
        'Halfopen' => $this->t('Halfopen aanbieding'),
        'Open' => $this->t('Open begroting'),
        'Regie' => $this->t('Regie-/eenheidsprijzenstaat'),
        'Maatwerk' => $this->t('Maatwerk'),
      ],
      '#default_value' => $form_value('price_detail', 'Halfopen'),
    ];

    $form['generator'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Conceptgenerator'),
      '#description' => $this->t('Kies enkele parameters. De generator vult alleen conceptteksten in; bedragen en calculatieregels worden niet gewijzigd.'),
    ];
    $form['generator']['client_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type opdrachtgever'),
      '#required' => TRUE,
      '#options' => [
        'Woningcorporatie' => $this->t('Woningcorporatie'),
        'VvE' => $this->t('VvE'),
        'Zakelijk' => $this->t('Zakelijke opdrachtgever'),
        'Overheid' => $this->t('Overheid / aanbestedende dienst'),
        'Particulier' => $this->t('Particulier'),
      ],
      '#default_value' => $form_value('client_type', 'Zakelijk'),
    ];
    $form['generator']['work_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type werk'),
      '#required' => TRUE,
      '#options' => [
        'Planmatig onderhoud' => $this->t('Planmatig onderhoud'),
        'Renovatie' => $this->t('Renovatie'),
        'Verduurzaming' => $this->t('Verduurzaming'),
        'ETICS gevelisolatie' => $this->t('ETICS gevelisolatie'),
        'Schilderwerk' => $this->t('Schilderwerk'),
        'Kozijnvervanging en beglazing' => $this->t('Kozijnvervanging en beglazing'),
        'Gevel- en betonherstel' => $this->t('Gevel- en betonherstel'),
        'Dak-, lood- en zinkwerk' => $this->t('Dak-, lood- en zinkwerk'),
        'Combinatieproject' => $this->t('Combinatieproject'),
        'Maatwerk' => $this->t('Maatwerk'),
      ],
      '#default_value' => $form_value('work_type', 'Planmatig onderhoud'),
    ];
    $form['generator']['writing_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Schrijfstijl'),
      '#required' => TRUE,
      '#options' => [
        'Zakelijk' => $this->t('Zakelijk en helder'),
        'Compact' => $this->t('Compact'),
        'Technisch' => $this->t('Technisch en formeel'),
        'Bewonersvriendelijk' => $this->t('Bewonersvriendelijk'),
      ],
      '#default_value' => $form_value('writing_style', 'Zakelijk'),
    ];
    $form['generator']['generate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Conceptteksten genereren'),
      '#submit' => ['::generateConceptTexts'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];

    $form['texts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Commerciële teksten'),
    ];
    foreach ([
      'scope' => $this->t('Scope'),
      'exclusions' => $this->t('Uitsluitingen'),
      'work_terms' => $this->t('Voor het werk geldende voorwaarden'),
    ] as $key => $label) {
      $form['texts'][$key] = [
        '#type' => 'textarea',
        '#title' => $label,
        '#rows' => 6,
        '#default_value' => (string) $form_value($key, ''),
      ];
    }

    $offer_lines = $this->loadOfferableCalculationLines();
    $form['post_structure'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Indeling offerteposten'),
      '#description' => $this->t('De voorgestelde indeling volgt het posttype uit de calculatie. Controleer iedere regel. Interne kostprijzen, verdisconteerde regels en interne notities worden niet overgenomen.'),
      '#tree' => TRUE,
    ];
    if (!$offer_lines) {
      $form['post_structure']['empty'] = [
        '#markup' => '<p>' . $this->t('Deze calculatie bevat nog geen financiële regels die in een offerte kunnen worden ingedeeld.') . '</p>',
      ];
    }
    else {
      $form['post_structure']['lines'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Calculatieregel'),
          $this->t('Aantal'),
          $this->t('Eenheid'),
          $this->t('Voorgestelde postsoort'),
        ],
      ];
      foreach ($offer_lines as $line_id => $line) {
        $form['post_structure']['lines'][$line_id]['description'] = [
          '#plain_text' => $line['description'],
        ];
        $form['post_structure']['lines'][$line_id]['quantity'] = [
          '#plain_text' => $line['quantity'],
        ];
        $form['post_structure']['lines'][$line_id]['unit'] = [
          '#plain_text' => $line['unit'],
        ];
        $form['post_structure']['lines'][$line_id]['post_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Postsoort voor @line', ['@line' => $line['description']]),
          '#title_display' => 'invisible',
          '#options' => [
            'Basisaanbieding' => $this->t('Basisaanbieding'),
            'Optie' => $this->t('Optie'),
            'Stelpost' => $this->t('Stelpost'),
            'Verrekenpost' => $this->t('Verrekenpost'),
          ],
          '#default_value' => $preserved_values['post_structure']['lines'][$line_id]['post_type'] ?? $line['suggested_type'],
        ];
      }
    }

    $form['tax'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Btw en G-rekening'),
    ];
    $form['tax']['vat_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Standaard btw-behandeling'),
      '#required' => TRUE,
      '#options' => [
        'Belast' => $this->t('Btw belast'),
        'Verlegd' => $this->t('Btw verlegd'),
        'Vrijgesteld' => $this->t('Btw vrijgesteld'),
        'Niet van toepassing' => $this->t('Niet van toepassing'),
      ],
      '#default_value' => $form_value('vat_default', 'Belast'),
    ];
    $form['tax']['g_account_on'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('G-rekening van toepassing'),
      '#default_value' => (bool) $form_value('g_account_on', FALSE),
    ];
    $form['tax']['g_account_pct'] = [
      '#type' => 'number',
      '#title' => $this->t('G-rekeningpercentage'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#default_value' => $form_value('g_account_pct', ''),
      '#states' => [
        'visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
        'required' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
      ],
    ];
    $form['tax']['g_account_base'] = [
      '#type' => 'select',
      '#title' => $this->t('G-rekeninggrondslag'),
      '#empty_option' => $this->t('- Selecteer -'),
      '#options' => [
        'Arbeid' => $this->t('Arbeid'),
        'Aanneemsom' => $this->t('Aanneemsom'),
        'Vaste grondslag' => $this->t('Overeengekomen vaste grondslag'),
      ],
      '#default_value' => $form_value('g_account_base'),
      '#states' => [
        'visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
        'required' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
      ],
    ];
    $form['tax']['g_account_iban'] = [
      '#type' => 'textfield',
      '#title' => $this->t('G-rekeningnummer (IBAN)'),
      '#maxlength' => 64,
      '#default_value' => $form_value('g_account_iban', ''),
      '#states' => [
        'visible' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
        'required' => [':input[name="g_account_on"]' => ['checked' => TRUE]],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Offerteversie opslaan'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => \Drupal\Core\Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * Generates editable commercial texts without changing calculation data.
   */
  public function generateConceptTexts(array &$form, FormStateInterface $form_state): void {
    // This button deliberately skips validation. Read and preserve the complete
    // raw input so a rebuild cannot reset identity, presentation or tax values.
    $input = $form_state->getUserInput();
    $texts = $this->buildConceptTexts(
      (string) ($input['client_type'] ?? 'Zakelijk'),
      (string) ($input['work_type'] ?? 'Planmatig onderhoud'),
      (string) ($input['writing_style'] ?? 'Zakelijk'),
      (string) ($input['offer_layout'] ?? 'Zakelijk'),
      (string) ($input['price_detail'] ?? 'Halfopen'),
    );

    foreach ($texts as $key => $text) {
      $input[$key] = $text;
      $form_state->setValue($key, $text);
    }
    $form_state->set('offer_form_values', $input);
    // Do not replace the raw POST payload. The rebuilt form deliberately reads
    // the preserved state first, so both user input and generated text survive.
    $form_state->setRebuild(TRUE);
    $this->messenger()->addStatus($this->t('De conceptteksten zijn gegenereerd. Controleer en bewerk ze vóór het opslaan.'));
  }

  /**
   * Builds conservative offer copy from user-selected parameters.
   *
   * @return array{scope: string, exclusions: string, work_terms: string}
   *   The three editable commercial text sections.
   */
  private function buildConceptTexts(
    string $client_type,
    string $work_type,
    string $writing_style,
    string $offer_layout,
    string $price_detail,
  ): array {
    $project = $this->calculation instanceof NodeInterface
      ? (string) $this->calculation->label()
      : (string) $this->t('het project');

    $audience = match ($client_type) {
      'Woningcorporatie' => 'de woningcorporatie en haar bewoners',
      'VvE' => 'de VvE, haar bestuur en bewoners',
      'Overheid' => 'de aanbestedende dienst en betrokken gebruikers',
      'Particulier' => 'de opdrachtgever en gebruikers van het pand',
      default => 'de opdrachtgever en betrokken gebruikers',
    };
    $work_focus = match ($work_type) {
      'Verduurzaming' => 'de overeengekomen verduurzamingsmaatregelen en de bouwkundige aansluitingen die daarvoor noodzakelijk zijn',
      'ETICS gevelisolatie' => 'het overeengekomen ETICS-gevelisolatiesysteem, inclusief ondergrondvoorbereiding, aansluitdetails en afwerking',
      'Schilderwerk' => 'de overeengekomen voorbehandeling, herstelwerkzaamheden en schilderafwerking',
      'Kozijnvervanging en beglazing' => 'de overeengekomen kozijnvervanging en beglazing, inclusief de beschreven aansluitingen en afwerking',
      'Gevel- en betonherstel' => 'het overeengekomen gevel- en betonherstel, inclusief de beschreven voorbereiding en afwerking',
      'Dak-, lood- en zinkwerk' => 'de overeengekomen dak-, lood- en zinkwerkzaamheden en de daarbij beschreven aansluitingen',
      'Renovatie' => 'de in de calculatie en werkomschrijving vastgelegde renovatiewerkzaamheden',
      'Combinatieproject' => 'de samenhangende werkzaamheden die in de calculatie en werkomschrijving zijn opgenomen',
      'Maatwerk' => 'de specifiek in de calculatie en werkomschrijving vastgelegde werkzaamheden',
      default => 'de in de calculatie en werkomschrijving vastgelegde onderhoudswerkzaamheden',
    };

    $scope = [
      'Deze aanbieding voor ' . $project . ' betreft ' . $work_focus . '.',
      'De scope wordt bepaald door de bij deze offerte behorende calculatie, werkomschrijving, hoeveelheden, tekeningen en schriftelijk vastgelegde uitgangspunten. Alleen uitdrukkelijk opgenomen werkzaamheden en leveringen maken deel uit van de aanbieding.',
    ];
    if ($writing_style === 'Technisch') {
      $scope[] = 'Uitvoering vindt plaats volgens de overeengekomen technische specificaties, verwerkingsvoorschriften van fabrikanten en vastgelegde keurings- en vrijgavemomenten. Afwijkingen worden vóór uitvoering schriftelijk gemeld.';
    }
    elseif ($writing_style === 'Bewonersvriendelijk' || $offer_layout === 'VvE') {
      $scope[] = 'Bij de uitvoering houden wij rekening met ' . $audience . '. Bereikbaarheid, hinder en noodzakelijke toegang worden tijdig afgestemd.';
    }
    elseif ($writing_style !== 'Compact') {
      $scope[] = 'Werkvolgorde, bereikbaarheid en afstemming met ' . $audience . ' worden vóór aanvang praktisch vastgelegd.';
    }

    $exclusions = [
      'Niet opgenomen zijn werkzaamheden, leveringen en hoeveelheden die niet uitdrukkelijk in deze aanbieding of de bijbehorende calculatie zijn beschreven.',
      'Eveneens uitgesloten zijn niet-zichtbare gebreken, asbest of andere schadelijke stoffen, constructieve gebreken en aanvullende eisen van bevoegd gezag of nutsbedrijven, tenzij deze expliciet als onderdeel of stelpost zijn opgenomen.',
      'Herstel als gevolg van werkzaamheden door derden en wijzigingen na vaststelling van de offerte worden afzonderlijk beoordeeld en, na akkoord, als meer- of minderwerk verwerkt.',
    ];
    if ($writing_style === 'Compact') {
      $exclusions = [
        'Niet inbegrepen zijn niet expliciet omschreven werkzaamheden, verborgen gebreken, schadelijke stoffen, vergunningen en werkzaamheden door derden. Wijzigingen worden alleen na schriftelijk akkoord als meer- of minderwerk uitgevoerd.',
      ];
    }

    $terms = [
      'Deze aanbieding is gebaseerd op de op offertedatum beschikbare projectinformatie en blijft geldig tot de in deze offerte vermelde geldigheidsdatum.',
      'Uitvoering is mogelijk nadat opdracht, planning, bereikbaarheid, werkterrein en noodzakelijke voorzieningen schriftelijk zijn afgestemd.',
      'Afwijkende omstandigheden, gewijzigde hoeveelheden en aanvullende wensen worden vóór uitvoering gemeld en uitsluitend na schriftelijk akkoord verrekend.',
      'Op deze aanbieding zijn de vermelde projectspecifieke voorwaarden en toepasselijke algemene voorwaarden van toepassing. Bij tegenstrijdigheid prevaleren de specifiek in deze offerte vastgelegde afspraken.',
    ];
    if ($price_detail === 'Open' || $price_detail === 'Regie') {
      $terms[] = 'Verrekenbare hoeveelheden, uren en eenheidsprijzen worden geregistreerd en afgerekend volgens de in de aanbieding opgenomen meet- en verrekenafspraken.';
    }
    elseif ($price_detail === 'Gesloten') {
      $terms[] = 'De gesloten aanneemsom geldt uitsluitend voor de omschreven scope en de vastgelegde uitgangspunten.';
    }
    if ($writing_style === 'Technisch') {
      $terms[] = 'Keuringen, vrijgaven en eventuele afwijkingen worden aantoonbaar in het projectdossier vastgelegd.';
    }

    return [
      'scope' => implode("\n\n", $scope),
      'exclusions' => implode("\n\n", $exclusions),
      'work_terms' => implode("\n\n", $terms),
    ];
  }

  /**
   * Loads financial calculation lines that may be shown externally.
   *
   * @return array<int, array{description: string, quantity: string, unit: string, suggested_type: string, unit_price: float, amount: float}>
   *   Offerable lines keyed by calculation-line node ID.
   */
  private function loadOfferableCalculationLines(): array {
    if (!$this->calculation instanceof NodeInterface) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $element_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $this->calculation->id())
      ->sort('field_brebo_element_sequence', 'ASC')
      ->execute();
    if (!$element_ids) {
      return [];
    }

    $line_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_line')
      ->condition('field_brebo_calc_element_ref.target_id', array_values($element_ids), 'IN')
      ->condition('field_brebo_line_type', 'Calculatieregel')
      ->sort('nid', 'ASC')
      ->execute();

    $source_lines = $storage->loadMultiple($line_ids);
    $direct_total = 0.0;
    foreach ($source_lines as $source_line) {
      if ($source_line instanceof NodeInterface) {
        $direct_total += (float) ($source_line->get('field_brebo_direct_cost')->value
          ?? ((float) ($source_line->get('field_brebo_contract_quantity')->value ?? 0) * (float) ($source_line->get('field_brebo_unit_price')->value ?? 0)));
      }
    }
    $tail_pct = 0.0;
    foreach (['field_brebo_general_cost_pct', 'field_brebo_risk_pct', 'field_brebo_profit_pct'] as $field_name) {
      if ($this->calculation->hasField($field_name)) {
        $tail_pct += (float) ($this->calculation->get($field_name)->value ?? 0);
      }
    }
    $commercial_adjustment = $this->calculation->hasField('field_brebo_com_adjustment')
      ? (float) ($this->calculation->get('field_brebo_com_adjustment')->value ?? 0)
      : 0.0;
    $sales_total = max(0.0, $direct_total * (1 + ($tail_pct / 100)) + $commercial_adjustment);
    $factor = $direct_total > 0.0 ? $sales_total / $direct_total : 0.0;

    $lines = [];
    foreach ($source_lines as $line) {
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $source_type = (string) ($line->get('field_brebo_line_post_type')->value ?? 'Vaste post');
      $quantity = (float) ($line->get('field_brebo_contract_quantity')->value ?? 0);
      $direct_cost = (float) ($line->get('field_brebo_direct_cost')->value
        ?? ($quantity * (float) ($line->get('field_brebo_unit_price')->value ?? 0)));
      $amount = round($direct_cost * $factor, 2);
      $lines[(int) $line->id()] = [
        'description' => (string) ($line->get('field_brebo_line_description')->value ?? $line->label()),
        'quantity' => (string) ($line->get('field_brebo_contract_quantity')->value ?? ''),
        'unit' => (string) ($line->get('field_brebo_unit')->value ?? ''),
        'suggested_type' => $this->mapOfferPostType($source_type),
        'unit_price' => $quantity > 0.0 ? round($amount / $quantity, 4) : $amount,
        'amount' => $amount,
      ];
    }
    return $lines;
  }

  /**
   * Maps an internal calculation post type to an external offer post type.
   */
  private function mapOfferPostType(string $source_type): string {
    $normalized = mb_strtolower(trim($source_type));
    return match (TRUE) {
      str_contains($normalized, 'optie'),
      str_contains($normalized, 'alternatief') => 'Optie',
      str_contains($normalized, 'stelpost') => 'Stelpost',
      str_contains($normalized, 'verreken') => 'Verrekenpost',
      default => 'Basisaanbieding',
    };
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->calculation instanceof NodeInterface) {
      $duplicate = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_offer_version')
        ->condition('field_brebo_offer_number', trim((string) $form_state->getValue('offer_number')))
        ->condition('field_brebo_offer_version', (int) $form_state->getValue('offer_version'))
        ->execute();
      if ($duplicate) {
        $form_state->setErrorByName('offer_number', $this->t('Deze combinatie van offertenummer en versienummer bestaat al.'));
      }
    }

    if ($form_state->getValue('g_account_on')) {
      $percentage = (float) $form_state->getValue('g_account_pct');
      if ($percentage <= 0 || $percentage > 100) {
        $form_state->setErrorByName('g_account_pct', $this->t('Vul een G-rekeningpercentage groter dan 0 en maximaal 100 in.'));
      }
      $iban = strtoupper(str_replace(' ', '', (string) $form_state->getValue('g_account_iban')));
      if (!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
        $form_state->setErrorByName('g_account_iban', $this->t('Vul een geldig IBAN-formaat in.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $calculation = $this->calculation;
    if (!$calculation instanceof NodeInterface) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $offer_number = trim((string) $form_state->getValue('offer_number'));
    $version = (int) $form_state->getValue('offer_version');
    $g_account_on = (bool) $form_state->getValue('g_account_on');
    $snapshot = json_encode([
      'calculation_id' => (int) $calculation->id(),
      'calculation_label' => (string) $calculation->label(),
      'calculation_version' => (string) ($calculation->get('field_brebo_calc_version')->value ?? ''),
      'calculation_changed' => (int) $calculation->getChangedTime(),
      'created_at' => gmdate(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $offer = $storage->create([
      'type' => 'brebo_offer_version',
      'title' => $offer_number . ' — v' . $version,
      'field_brebo_calculation_ref' => ['target_id' => $calculation->id()],
      'field_brebo_offer_number' => $offer_number,
      'field_brebo_offer_version' => $version,
      'field_brebo_offer_status' => $form_state->getValue('offer_status'),
      'field_brebo_offer_layout' => $form_state->getValue('offer_layout'),
      'field_brebo_price_detail' => $form_state->getValue('price_detail'),
      'field_brebo_offer_scope' => $form_state->getValue('scope'),
      'field_brebo_exclusions' => $form_state->getValue('exclusions'),
      'field_brebo_work_terms' => $form_state->getValue('work_terms'),
      'field_brebo_valid_until' => $form_state->getValue('valid_until') ?: NULL,
      'field_brebo_vat_default' => $form_state->getValue('vat_default'),
      'field_brebo_g_account_on' => $g_account_on ? 1 : 0,
      'field_brebo_g_account_pct' => $g_account_on ? $form_state->getValue('g_account_pct') : '0.0000',
      'field_brebo_g_account_base' => $g_account_on ? $form_state->getValue('g_account_base') : NULL,
      'field_brebo_g_account_iban' => $g_account_on ? strtoupper(str_replace(' ', '', (string) $form_state->getValue('g_account_iban'))) : NULL,
      'field_brebo_offer_snapshot' => $snapshot ?: '',
      'status' => 1,
    ]);
    $offer->setNewRevision(TRUE);
    $offer->setRevisionLogMessage('Offerteversie gemaakt vanuit calculatie ' . $calculation->label() . '.');
    $offer->save();

    $selected_types = (array) $form_state->getValue(['post_structure', 'lines']);
    $offer_lines = $this->loadOfferableCalculationLines();
    $sequence = 10;
    foreach ($offer_lines as $line_id => $line) {
      $selected = (string) ($selected_types[$line_id]['post_type'] ?? $line['suggested_type']);
      if (!in_array($selected, ['Basisaanbieding', 'Optie', 'Stelpost', 'Verrekenpost'], TRUE)) {
        $selected = $line['suggested_type'];
      }
      $post = $storage->create([
        'type' => 'brebo_offer_post',
        'title' => $offer_number . ' — ' . $sequence . ' — ' . $line['description'],
        'field_brebo_offer_version_ref' => ['target_id' => $offer->id()],
        'field_brebo_offer_post_type' => $selected,
        'field_brebo_offer_post_seq' => $sequence,
        'field_brebo_offer_post_desc' => $line['description'],
        'field_brebo_offer_quantity' => $line['quantity'] !== '' ? $line['quantity'] : NULL,
        'field_brebo_offer_unit' => $line['unit'] !== '' ? $line['unit'] : NULL,
        'field_brebo_offer_unit_price' => number_format($line['unit_price'], 4, '.', ''),
        'field_brebo_offer_amount' => number_format($line['amount'], 4, '.', ''),
        'field_brebo_in_offer_total' => $selected === 'Optie' ? 0 : 1,
        'field_brebo_vat_treatment' => $form_state->getValue('vat_default'),
        'field_brebo_vat_rate' => $form_state->getValue('vat_default') === 'Belast' ? '21.0000' : '0.0000',
        'field_brebo_offer_post_status' => 'Aangeboden',
        'field_brebo_offer_post_notes' => 'Broncalculatieregel: ' . $line_id . '. Interne kostprijzen zijn niet gekopieerd.',
        'status' => 1,
      ]);
      $post->setNewRevision(TRUE);
      $post->setRevisionLogMessage('Offertepost vastgelegd bij offerteversie ' . $offer_number . ' v' . $version . '.');
      $post->save();
      $sequence += 10;
    }

    $this->messenger()->addStatus($this->t('Offerteversie @number v@version is opgeslagen met @count ingedeelde offerteposten.', [
      '@number' => $offer_number,
      '@version' => $version,
      '@count' => count($offer_lines),
    ]));
    $form_state->setRedirect('brebo_office_core.offer_preview', ['node' => $offer->id()]);
  }

}
