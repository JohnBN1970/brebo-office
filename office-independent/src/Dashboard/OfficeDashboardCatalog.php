<?php
declare(strict_types=1);
namespace Brebo\Office\Dashboard;
/** Shared KPI definitions. Monitor eligibility is an explicit server-owned allowlist. */
final class OfficeDashboardCatalog {
  public static function all(): array {
    return [
      'projects'=>['Projecten','projects','number',true,'/projecten'],
      'critical'=>['Kritieke projecten','projects','number',true,'/projecten?view=gantt'],
      'waiting'=>['Projecten in wacht','projects','number',true,'/projecten?view=gantt'],
      'budget'=>['Vastgestelde inkoopbegroting','projects','money',false,'/projecten'],
      'contract'=>['Opdrachtwaarde','projects','money',false,'/projecten'],
      'planned'=>['Gepland deze week','projects','hours',false,'/projecten?view=inzet'],
      'approved'=>['Goedgekeurde uren cumulatief','projects','hours',false,'/projecten?view=inzet&tab=controle'],
      'revenue'=>['Omzet dit jaar','finance','money',false,'/finance/verkoop'],
      'debtors'=>['Openstaande verkoopfacturen','finance','money',false,'/finance/verkoop'],
      'overdue'=>['Vervallen verkoopfacturen','finance','money',false,'/finance/verkoop'],
      'creditors'=>['Open geboekte inkoopfacturen','finance','money',false,'/finance/administratie'],
      'documents'=>['Actieve projectdocumenten','quality','number',false,'/documenten'],
      'drafts'=>['Conceptfacturen','finance','number',false,'/finance/verkoop'],
      'quality'=>['Open kwaliteitsacties','quality','number',true,'/kwaliteit'],
      'deviations'=>['Kwaliteitsafwijkingen','quality','number',true,'/kwaliteit'],
      'risks'=>['Open projectrisico’s','quality','number',true,'/risicos'],
    ];
  }
  public static function visible(array $widgets,bool $monitor): array {
    return array_filter($widgets,static fn($id)=>isset(self::all()[$id])&&(!$monitor||self::all()[$id][3]),ARRAY_FILTER_USE_KEY);
  }
  public static function format(string $id,?int $value): string {
    if($value===null)return 'Niet beschikbaar';
    return match(self::all()[$id][2]) {
      'money'=>($value<0?'-':'').'€ '.number_format(abs($value)/100,2,',','.'),
      'hours'=>number_format($value/3600,2,',','.').' uur',
      default=>number_format($value,0,',','.'),
    };
  }
}
