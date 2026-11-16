<?php
declare(strict_types=1);
namespace Brebo\Office\Project;
use PDO;
require_once __DIR__.'/OfficeNativeProjectAccess.php';
require_once __DIR__.'/OfficeProjectContractRepository.php';
require_once __DIR__.'/OfficeProjectContractService.php';
require_once __DIR__.'/OfficeProjectTermRepository.php';
require_once __DIR__.'/OfficeProjectChangeOrderRepository.php';
require_once dirname(__DIR__).'/Finance/OfficeFinanceAccess.php';
/** Reconnect the existing native contract repositories to the authorized project dossier. */
final class OfficeProjectFinanceControls {
  public static function allowed(PDO $db,string $actor,int $project): bool {
    $access=new \Brebo\Office\Finance\OfficeFinanceAccess($db,$actor);
    return ctype_digit($actor) && (new OfficeNativeProjectAccess($db))->canManage((int)$actor,$project)
      && $access->allows('read') && $access->allows('draft_edit');
  }
  public static function handle(PDO $db,string $actor,int $project,array $input): void {
    if (!self::allowed($db,$actor,$project)) throw new \DomainException('Finance-schrijf- en projectbeheerrechten vereist.');
    $text=static function(string $key)use($input):string {if(!is_string($input[$key]??null)||strlen($input[$key])>1000)throw new \InvalidArgumentException('Ongeldige invoer.');return trim($input[$key]);};
    $amount=str_replace(' ','',$text('amount'));
    if (str_contains($amount,',')) $amount=str_replace(',', '.',str_replace('.','',$amount));
    if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',$amount,$m)) throw new \InvalidArgumentException('Ongeldig bedrag.');
    $amount=$m[1].'.'.str_pad($m[2]??'',2,'0');
    $action=$input['action']??'';
    if ($action==='create_project_contract') {
      (new OfficeProjectContractService($db))->createDraft($actor,$project,$text('contract_number'),$text('client_name'),$amount);return;
    }
    $contract=filter_var($input['contract_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $ids=array_map('intval',array_column((new OfficeProjectContractRepository($db))->forProject($project),'id'));
    if (!is_int($contract)||!in_array($contract,$ids,true)) throw new \DomainException('Contract hoort niet bij dit project.');
    if ($action==='create_project_term') {
      $number=filter_var($input['term_number']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
      if (!is_int($number)) throw new \InvalidArgumentException('Ongeldig termijnnummer.');
      (new OfficeProjectTermRepository($db))->create($contract,$number,$text('description'),$amount);return;
    }
    if ($action==='create_project_change') {
      (new OfficeProjectChangeOrderRepository($db))->createDraft($contract,$text('change_number'),$text('description'),$text('change_type'),$amount);return;
    }
    throw new \InvalidArgumentException('Onbekende contractactie.');
  }
  public static function render(PDO $db,string $actor,int $project,string $csrf): string {
    if (!self::allowed($db,$actor,$project)) return '';
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $start='<form method="post"><input type="hidden" name="csrf" value="'.$e($csrf).'">';
    $money='<label>Bedrag excl. btw (€)<input name="amount" inputmode="decimal" placeholder="0,00" required></label>';
    $html='<section class="project-panel" aria-label="Contracten en termijnen"><h2>Contracten &amp; termijnen</h2><p class="project-muted">Contractafspraken, termijnschema en concept meer- of minderwerk bij dit project.</p><details><summary>Nieuw contract</summary>'.$start.'<input type="hidden" name="action" value="create_project_contract"><label>Contractnummer<input name="contract_number" maxlength="100" required></label><label>Opdrachtgever<input name="client_name" maxlength="255" required></label>'.$money.'<button>Contractconcept vastleggen</button></form></details>';
    $contracts=(new OfficeProjectContractRepository($db))->forProject($project);
    if ($contracts!==[]) {
      $select='<label>Contract<select name="contract_id">';foreach ($contracts as $c) $select.='<option value="'.(int)$c['id'].'">'.$e($c['contract_number']?:'#'.$c['id']).'</option>';$select.='</select></label>';
      $html.='<details><summary>Termijn toevoegen</summary>'.$start.'<input type="hidden" name="action" value="create_project_term">'.$select.'<label>Termijnnummer<input name="term_number" type="number" min="1" required></label><label>Omschrijving<input name="description" maxlength="255" required></label>'.$money.'<button>Termijn vastleggen</button></form></details>';
      $html.='<details><summary>Meer- of minderwerk</summary>'.$start.'<input type="hidden" name="action" value="create_project_change">'.$select.'<label>Wijzigingsnummer<input name="change_number" maxlength="100" required></label><label>Omschrijving<input name="description" maxlength="255" required></label><label>Soort<select name="change_type"><option value="more">Meerwerk</option><option value="less">Minderwerk</option></select></label>'.$money.'<button>Wijzigingsconcept vastleggen</button></form></details>';
    }
    return $html.'</section>';
  }
}
