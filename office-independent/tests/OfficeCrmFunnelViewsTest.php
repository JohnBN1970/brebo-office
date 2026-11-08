<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnelViews.php';
use Brebo\Office\Crm\OfficeCrmFunnel;
use Brebo\Office\Crm\OfficeCrmFunnelViews;
$assert = static function(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); };
$assert(OfficeCrmFunnel::canTransition('lead','prospect'), 'lead qualification');
$assert(!OfficeCrmFunnel::canTransition('lead','won'), 'cannot skip qualification');
$assert(!OfficeCrmFunnel::canTransition('won','lead'), 'closed cannot reopen as lead');
$items = [['name'=>'<script>alert(1)</script>','stage'=>'prospect','next_action'=>'Opname plannen','due_date'=>'2026-11-12']];
foreach (['kanban','list','calendar'] as $view) {
    $html=OfficeCrmFunnelViews::render($items,$view);
    $assert(str_contains($html,'&lt;script&gt;'), 'escaping '.$view);
    $assert(!str_contains($html,'<script>'), 'raw injection '.$view);
    $assert(str_contains($html,'Opname plannen'), 'optional opname '.$view);
    $assert(str_contains($html,'?view=kanban') && str_contains($html,'?view=list') && str_contains($html,'?view=calendar'), 'view switch '.$view);
}
$assert(OfficeCrmFunnel::normalizeView('invalid')==='kanban','invalid view fallback');
echo "CRM_FUNNEL_VIEWS_OK\n";
