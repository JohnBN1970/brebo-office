<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OnSitePresenceReviewView.php';
$view=\Brebo\Office\Inzet\OnSitePresenceReviewView::render([
 'intervals'=>[['person_id'=>'101','project_id'=>'17','zone_id'=>'2',
   'entered_at'=>'2026-10-22T08:00:00Z','left_at'=>'2026-10-22T09:30:00Z',
   'elapsed_seconds'=>5400,'status'=>'evidence_only']],
 'exceptions'=>[['reason'=>'missing_departure','event_id'=>'onsite_test_event_0001']]
]);
foreach (['Aanwezigheidscontrole','1:30','Ter beoordeling','Ontbrekend vertrek'] as $needle) {
 if (!str_contains($view,$needle)) throw new RuntimeException('Presence review view missing: '.$needle);
}
$live=\Brebo\Office\Inzet\OnSitePresenceReviewView::render([
 'active'=>[['person_id'=>'101','project_id'=>'17','zone_id'=>'2','entered_at'=>'2026-10-23T08:00:00Z']],
 'intervals'=>[], 'exceptions'=>[]
]);
foreach (['Lopende aanwezigheid','onsite-live-duration','data-start=','Aanwezig (nog niet goedgekeurd)'] as $needle) {
 if (!str_contains($live,$needle)) throw new RuntimeException('Live presence view missing: '.$needle);
}
if (str_contains($live,'name="approved_hours"')) throw new RuntimeException('Live presence must not approve hours');
if (str_contains($view,'<form') || str_contains($view,'name="approved_hours"'))
 throw new RuntimeException('Presence review unexpectedly approves hours');
$unsafe=\Brebo\Office\Inzet\OnSitePresenceReviewView::render([
 'intervals'=>[], 'exceptions'=>[['reason'=>'unknown','event_id'=>'<script>alert(1)</script>']]
]);
if (str_contains($unsafe,'<script>') || !str_contains($unsafe,'&lt;script&gt;'))
 throw new RuntimeException('Presence review XSS escape failed');
echo "OnSite presence review view OK\n";
