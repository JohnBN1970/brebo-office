<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Central management UI for legal entities and document numbering. */
final class AdministrationSettingsForm extends ConfigFormBase {

  private const SERIES = [
    'project' => 'Project', 'quotation' => 'Offerte', 'assignment' => 'Opdracht / order', 'sales_invoice' => 'Verkoopfactuur',
    'credit_invoice' => 'Creditfactuur', 'purchase' => 'Inkoop', 'contract' => 'Contract',
    'report' => 'Rapport', 'inspection' => 'Inspectie', 'document' => 'Algemeen document',
  ];

  protected function getEditableConfigNames(): array { return ['brebo_office_core.settings']; }
  public function getFormId(): string { return 'brebo_office_core_administration_settings_form'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('brebo_office_core.settings');
    $administrations = $config->get('administrations');
    if (!is_array($administrations) || $administrations === []) $administrations = ['primary' => $this->legacyAdministration($config)];
    $form['intro'] = ['#markup' => '<p><strong>Administraties & entiteiten</strong><br>Iedere administratie heeft een eigen juridische identiteit, branding, bank- en integratiegegevens en eigen nummerreeksen. Uitgegeven nummers worden nooit hernummerd.</p>'];
    $labels = [];
    foreach ($administrations as $code => $administration) $labels[$code] = (string) ($administration['trade_name'] ?? $administration['legal_name'] ?? $code);
    $form['primary_administration'] = ['#type' => 'select', '#title' => $this->t('Primaire administratie'), '#options' => $labels, '#default_value' => $config->get('primary_administration') ?? array_key_first($administrations), '#required' => TRUE];
    $form['administrations'] = ['#type' => 'container', '#tree' => TRUE];
    foreach ($administrations as $code => $administration) {
      $form['administrations'][$code] = ['#type' => 'details', '#title' => $administration['trade_name'] ?? $code, '#open' => count($administrations) === 1, '#tree' => TRUE];
      $a =& $form['administrations'][$code];
      $a['active'] = ['#type' => 'checkbox', '#title' => $this->t('Actief'), '#default_value' => $administration['active'] ?? TRUE];
      foreach (['trade_name'=>'Handelsnaam','legal_name'=>'Statutaire naam','registration_number'=>'KVK / ondernemingsnummer','vat_number'=>'Btw-nummer','address'=>'Adres','postal_code'=>'Postcode','city'=>'Plaats','country'=>'Landcode','general_phone'=>'Telefoon','default_iban'=>'Standaard IBAN','bic'=>'BIC','moneybird_administration_id'=>'Moneybird administratie-ID'] as $key => $label) $a[$key] = ['#type'=>'textfield','#title'=>$this->t($label),'#default_value'=>$administration[$key] ?? ''];
      $a['general_email'] = ['#type'=>'email','#title'=>$this->t('Algemeen e-mailadres'),'#default_value'=>$administration['general_email'] ?? ''];
      $a['website'] = ['#type'=>'url','#title'=>$this->t('Website'),'#default_value'=>$administration['website'] ?? ''];
      $a['logo_uri'] = ['#type'=>'textfield','#title'=>$this->t('Logo bestand/URI'),'#default_value'=>$administration['logo_uri'] ?? '','#description'=>$this->t('Drupal-bestands-URI voor documenten en huisstijl.')];
      $a['logo_compact_uri'] = ['#type'=>'textfield','#title'=>$this->t('Compact logo bestand/URI'),'#default_value'=>$administration['logo_compact_uri'] ?? ''];
      $a['currency'] = ['#type'=>'textfield','#title'=>$this->t('Valuta'),'#default_value'=>$administration['currency'] ?? 'EUR','#maxlength'=>3];
      $a['timezone'] = ['#type'=>'textfield','#title'=>$this->t('Tijdzone'),'#default_value'=>$administration['timezone'] ?? 'Europe/Amsterdam'];
      $a['numbering'] = ['#type'=>'details','#title'=>$this->t('Documentnummering'),'#tree'=>TRUE];
      foreach (self::SERIES as $series => $label) {
        $current = is_array($administration['numbering'][$series] ?? NULL) ? $administration['numbering'][$series] : $this->defaultSeries($series, $code);
        $a['numbering'][$series] = ['#type'=>'fieldset','#title'=>$this->t($label),'#tree'=>TRUE];
        $s =& $a['numbering'][$series];
        $s['prefix'] = ['#type'=>'textfield','#title'=>$this->t('Prefix'),'#default_value'=>$current['prefix'] ?? '','#maxlength'=>16];
        $s['include_year'] = ['#type'=>'checkbox','#title'=>$this->t('Jaar opnemen'),'#default_value'=>$current['include_year'] ?? TRUE];
        $s['separator'] = ['#type'=>'textfield','#title'=>$this->t('Scheidingsteken'),'#default_value'=>$current['separator'] ?? '-','#maxlength'=>4];
        $s['digits'] = ['#type'=>'number','#title'=>$this->t('Aantal cijfers'),'#default_value'=>$current['digits'] ?? 4,'#min'=>1,'#max'=>10,'#required'=>TRUE];
        $s['start_number'] = ['#type'=>'number','#title'=>$this->t('Startnummer'),'#default_value'=>$current['start_number'] ?? 1,'#min'=>1,'#required'=>TRUE,'#description'=>$this->t('Geldt voor een nieuwe reeks; bestaande uitgegeven nummers blijven ongewijzigd.')];
        $s['reset_yearly'] = ['#type'=>'checkbox','#title'=>$this->t('Jaarlijks resetten'),'#default_value'=>$current['reset_yearly'] ?? TRUE];
      }
      unset($s, $a);
    }
    $form['new_administration'] = ['#type'=>'details','#title'=>$this->t('Extra administratie toevoegen'),'#tree'=>TRUE];
    $form['new_administration']['code'] = ['#type'=>'machine_name','#title'=>$this->t('Administratiecode'),'#machine_name'=>['exists'=>[$this,'administrationCodeExists']],'#required'=>FALSE];
    $form['new_administration']['trade_name'] = ['#type'=>'textfield','#title'=>$this->t('Handelsnaam')];
    return parent::buildForm($form, $form_state);
  }

  public function administrationCodeExists(string $code): bool { $a=$this->config('brebo_office_core.settings')->get('administrations'); return is_array($a) && isset($a[$code]); }
  public function validateForm(array &$form, FormStateInterface $form_state): void { parent::validateForm($form,$form_state); $new=(array)$form_state->getValue('new_administration'); if (($new['code']??'')!=='' && trim((string)($new['trade_name']??''))==='') $form_state->setErrorByName('new_administration][trade_name',$this->t('Vul een handelsnaam in voor de nieuwe administratie.')); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config=$this->configFactory->getEditable('brebo_office_core.settings'); $administrations=(array)$form_state->getValue('administrations');
    foreach ($administrations as $code=>&$administration) { $administration['code']=$code; $administration['active']=!empty($administration['active']); } unset($administration);
    $new=(array)$form_state->getValue('new_administration'); $newCode=trim((string)($new['code']??'')); if ($newCode!=='') $administrations[$newCode]=$this->blankAdministration($newCode,trim((string)$new['trade_name']));
    $primary=(string)$form_state->getValue('primary_administration'); if (!isset($administrations[$primary])) $primary=(string)array_key_first($administrations);
    $config->set('primary_administration',$primary)->set('administrations',$administrations)->save(); parent::submitForm($form,$form_state);
    if ($newCode!=='') $this->messenger()->addStatus($this->t('Administratie @code is toegevoegd. Open deze pagina opnieuw om alle gegevens en nummerreeksen in te vullen.',['@code'=>$newCode]));
  }

  private function legacyAdministration(Config $config): array {
    $numbering=[]; foreach (array_keys(self::SERIES) as $series) $numbering[$series]=$this->defaultSeries($series,'primary',(string)($config->get('organization.trade_name')??'BREBO'));
    return ['code'=>'primary','active'=>TRUE,'trade_name'=>(string)($config->get('organization.trade_name')??'BREBO'),'legal_name'=>(string)($config->get('organization.legal_name')??'BREBO Bouw en Advies B.V.'),'registration_number'=>(string)($config->get('organization.registration_number')??''),'vat_number'=>(string)($config->get('organization.vat_number')??''),'address'=>(string)($config->get('organization.address')??''),'postal_code'=>(string)($config->get('organization.postal_code')??''),'city'=>(string)($config->get('organization.city')??''),'country'=>(string)($config->get('organization.country')??'NL'),'general_email'=>(string)($config->get('organization.general_email')??''),'general_phone'=>(string)($config->get('organization.general_phone')??''),'website'=>(string)($config->get('organization.website')??''),'logo_uri'=>(string)($config->get('organization.logo_uri')??''),'logo_compact_uri'=>(string)($config->get('organization.logo_compact_uri')??''),'currency'=>(string)($config->get('organization.currency')??'EUR'),'timezone'=>(string)($config->get('project.timezone')??'Europe/Amsterdam'),'default_iban'=>(string)($config->get('organization.default_iban')??''),'bic'=>(string)($config->get('organization.bic')??''),'moneybird_administration_id'=>(string)($config->get('organization.moneybird_administration_id')??''),'numbering'=>$numbering];
  }

  private function blankAdministration(string $code,string $tradeName): array {
    $numbering=[]; foreach (array_keys(self::SERIES) as $series) $numbering[$series]=$this->defaultSeries($series,$code,$tradeName);
    return ['code'=>$code,'active'=>TRUE,'trade_name'=>$tradeName,'legal_name'=>'','registration_number'=>'','vat_number'=>'','address'=>'','postal_code'=>'','city'=>'','country'=>'NL','general_email'=>'','general_phone'=>'','website'=>'','logo_uri'=>'','logo_compact_uri'=>'','currency'=>'EUR','timezone'=>'Europe/Amsterdam','default_iban'=>'','bic'=>'','moneybird_administration_id'=>'','numbering'=>$numbering];
  }

  private function defaultSeries(string $series,string $administrationCode='',string $tradeName=''): array {
    $projectPrefix=strtoupper((string)preg_replace('/[^A-Za-z0-9]+/','',$tradeName));
    if ($projectPrefix==='') $projectPrefix=strtoupper((string)preg_replace('/[^A-Za-z0-9]+/','',$administrationCode));
    if ($projectPrefix==='') $projectPrefix='PRJ';
    $prefixes=['project'=>substr($projectPrefix,0,16),'quotation'=>'OFF','assignment'=>'OPD','sales_invoice'=>'VF','credit_invoice'=>'CR','purchase'=>'INK','contract'=>'CTR','report'=>'RAP','inspection'=>'INS','document'=>'DOC'];
    return ['prefix'=>$prefixes[$series]??'DOC','include_year'=>TRUE,'separator'=>'-','digits'=>4,'start_number'=>1,'reset_yearly'=>TRUE];
  }
}
