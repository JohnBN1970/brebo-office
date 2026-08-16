<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationPriceSourceManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Price-source cockpit for one calculation row. */
final class CalculationPriceSourceForm extends FormBase {

  private const CARRIERS = [
    'labour' => 'Arbeid',
    'material' => 'Materiaal',
    'equipment' => 'Materieel',
    'subcontracting' => 'OA',
    'other' => 'Overig',
  ];

  public function __construct(private readonly Connection $database, private readonly CalculationPriceSourceManager $priceSourceManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_calculation.price_source_manager'));
  }

  public function getFormId(): string { return 'brebo_calculation_price_source_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $line = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation' || !$line) return ['message' => ['#markup' => '<p>Calculatieregel niet gevonden.</p>']];
    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) return ['message' => ['#markup' => '<p>Geen actieve calculatieversie gevonden.</p>']];

    $row = $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')
      ->condition('calculation_id', (int) $node->id())->condition('version', $version['version'])->condition('calc_line_id', $line)
      ->execute()->fetchAssoc();
    if (!$row) return ['message' => ['#markup' => '<p>Deze regel hoort niet bij de actieve calculatieversie.</p>']];

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => (int) $node->id()];
    $form['version'] = ['#type' => 'hidden', '#value' => (string) $version['version']];
    $form['line_id'] = ['#type' => 'hidden', '#value' => $line];

    $summary = [];
    foreach (['labour_unit_cost'=>'Arbeid','material_unit_cost'=>'Materiaal','equipment_unit_cost'=>'Materieel','subcontracting_unit_cost'=>'OA','other_unit_cost'=>'Overig'] as $field => $label) {
      $summary[] = '<span><small>' . $label . '</small><strong>€ ' . number_format((float) $row[$field], 2, ',', '.') . '</strong></span>';
    }
    $form['summary'] = [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-price-source-summary']],
      'back' => ['#type' => 'link', '#title' => '← Terug naar calculatie', '#url' => Url::fromRoute('brebo_calculation.workbench', ['node' => $node->id()]), '#attributes' => ['class' => ['button','button--small']]],
      'costs' => ['#markup' => '<div class="brebo-price-source-costs">' . implode('', $summary) . '</div>'],
    ];

    $query = $this->database->select('brebo_calculation_price_source_line', 'm');
    $query->join('brebo_calculation_price_source', 's', 's.id = m.price_source_id');
    $query->fields('m');
    $query->fields('s', ['source_type','supplier_name','supplier_email','offer_number','offer_date','valid_until','quoted_total','scope_summary','conditions_summary','internal_note','status']);
    $query->condition('m.calculation_id', (int) $node->id())->condition('m.version', $version['version'])->condition('m.calc_line_id', $line)->orderBy('m.created', 'DESC');
    $records = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $form['sources'] = ['#type'=>'table','#header'=>['Bron','Leverancier','Referentie','Datum','Kostendrager','Voorstel / EH','Status','Actie'],'#empty'=>'Nog geen prijsbronnen gekoppeld aan deze regel.','#attributes'=>['class'=>['brebo-price-source-table']]];
    foreach ($records as $mappingId => $source) {
      $active = (int) $source['is_active_source'] === 1;
      $carrier = str_starts_with((string) ($source['source_line_ref'] ?? ''), 'cost_carrier:') ? substr((string) $source['source_line_ref'], 13) : 'subcontracting';
      if (!isset(self::CARRIERS[$carrier])) $carrier = 'subcontracting';
      $label = match ((string) $source['source_type']) {'email'=>'✉ E-mail','document'=>'▣ Document',default=>'● Handmatig'};
      $form['sources']['source_' . $mappingId] = [
        '#attributes'=>['class'=>[$active?'is-active-source':'is-candidate-source']],
        'type'=>['#markup'=>'<strong>'.$label.'</strong>'],
        'supplier'=>['#markup'=>htmlspecialchars((string) ($source['supplier_name'] ?: $source['supplier_email'] ?: '—'))],
        'reference'=>['#markup'=>htmlspecialchars((string) ($source['offer_number'] ?: '—'))],
        'date'=>['#markup'=>htmlspecialchars((string) ($source['offer_date'] ?: '—'))],
        'carrier'=>['#markup'=>'<strong>'.self::CARRIERS[$carrier].'</strong>'],
        'proposal'=>['#markup'=>$source['proposed_oa_unit_cost'] !== NULL ? '€ '.number_format((float) $source['proposed_oa_unit_cost'],2,',','.') : '—'],
        'status'=>['#markup'=>$active?'<strong>✓ Actieve bron</strong>':($source['approval_status']==='approved'?'Goedgekeurd':'Te controleren')],
        'action'=>$active?['#markup'=>'']:[
          '#type'=>'submit','#value'=>'Gebruik als '.self::CARRIERS[$carrier].'-prijs','#name'=>'approve_'.$mappingId,'#submit'=>['::approveSource'],
          '#price_source_id'=>(int)$source['price_source_id'],'#cost_carrier'=>$carrier,'#proposed_unit_cost'=>(float)($source['proposed_oa_unit_cost']??0),
          '#disabled'=>$source['proposed_oa_unit_cost']===NULL,'#attributes'=>['class'=>['button','button--small']],
        ],
      ];
    }

    $form['add'] = ['#type'=>'details','#title'=>'Prijsbron toevoegen','#open'=>!$records,'#attributes'=>['class'=>['brebo-price-source-add']]];
    $form['add']['source_type'] = ['#type'=>'radios','#title'=>'Bronsoort','#options'=>['email'=>'E-mail','document'=>'Document','manual'=>'Handmatig'],'#default_value'=>'email'];
    $form['add']['cost_carrier'] = ['#type'=>'select','#title'=>'Voorgestelde kostendrager','#options'=>self::CARRIERS,'#default_value'=>'subcontracting','#description'=>'Kies waar deze prijs thuishoort. Bijvoorbeeld: steiger = Materieel; verflevering = Materiaal; uitbesteed schilderwerk = OA.'];
    $form['add']['supplier_name'] = ['#type'=>'textfield','#title'=>'Leverancier'];
    $form['add']['supplier_email'] = ['#type'=>'email','#title'=>'E-mailadres leverancier'];
    $form['add']['offer_number'] = ['#type'=>'textfield','#title'=>'Offerte / referentie'];
    $form['add']['offer_date'] = ['#type'=>'date','#title'=>'Datum prijsbron'];
    $form['add']['valid_until'] = ['#type'=>'date','#title'=>'Geldig tot'];
    $form['add']['scope_summary'] = ['#type'=>'textarea','#title'=>'Scope / omschrijving','#rows'=>3];
    $form['add']['conditions_summary'] = ['#type'=>'textarea','#title'=>'Voorwaarden / uitsluitingen','#rows'=>3];
    $form['add']['quoted_total'] = ['#type'=>'number','#title'=>'Totaal genoemde prijs','#step'=>'0.01','#min'=>0];
    $form['add']['proposed_unit_cost'] = ['#type'=>'number','#title'=>'Voorgestelde kostprijs / EH','#step'=>'0.0001','#min'=>0,'#required'=>TRUE];
    $form['add']['internal_note'] = ['#type'=>'textarea','#title'=>'Interne notitie','#rows'=>3];
    $form['add']['email_message_id'] = ['#type'=>'textfield','#title'=>'E-mailbericht-ID','#description'=>'Wordt later automatisch gevuld bij selectie vanuit de gekoppelde mailbox.'];
    $form['add']['source_ref'] = ['#type'=>'textfield','#title'=>'Bronreferentie / documentreferentie'];
    $form['add']['submit'] = ['#type'=>'submit','#value'=>'Als prijsbron vastleggen','#button_type'=>'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sourceId = $this->priceSourceManager->createForLine((int)$form_state->getValue('calculation_id'),(string)$form_state->getValue('version'),(int)$form_state->getValue('line_id'),(array)$form_state->getValue('add'),$this->currentUser());
    $this->messenger()->addStatus($this->t('Prijsbron @id toegevoegd en klaar voor controle.', ['@id'=>$sourceId]));
    $form_state->setRebuild(TRUE);
  }

  public function approveSource(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $carrier = (string)($trigger['#cost_carrier']??'subcontracting');
    $this->priceSourceManager->approveForLine((int)$form_state->getValue('calculation_id'),(string)$form_state->getValue('version'),(int)$form_state->getValue('line_id'),(int)($trigger['#price_source_id']??0),$carrier,(float)($trigger['#proposed_unit_cost']??0),'Prijsbron vanuit regelcockpit goedgekeurd.',$this->currentUser());
    $this->messenger()->addStatus('Prijsbron goedgekeurd en '.(self::CARRIERS[$carrier]??$carrier).'-prijs bijgewerkt.');
    $form_state->setRebuild(TRUE);
  }

  /** @return array<string,mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version','v')->fields('v')->condition('calculation_id',$calculationId)->orderBy('id','DESC')->range(0,1)->execute()->fetchAssoc();
    return $record ?: NULL;
  }
}
