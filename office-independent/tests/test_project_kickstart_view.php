<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartView.php';
use Brebo\Office\Project\OfficeProjectKickstartView;
$html = OfficeProjectKickstartView::render(
    ['status'=>'awaiting_second_approval','revision'=>2,'items'=>['projectteam'=>true]],
    [['question'=>'<script>alert(1)</script>','answer'=>'<b>nee</b>','decision'=>'Uitstellen','owner_label'=>'Werkvoorbereider']],
    [['actor_label'=>'<img src=x onerror=alert(1)>','decision_label'=>'Goedgekeurd','created_at'=>'2026-11-13']]
);
if (!str_contains($html,'Wacht op tweede goedkeurder') || !str_contains($html,'Projectteam: <strong>Gereed</strong>')) {
    throw new RuntimeException('Expected start dossier content missing');
}
if (str_contains($html,'<script>') || str_contains($html,'<img ') || str_contains($html,'<b>nee</b>')) {
    throw new RuntimeException('Unsafe content rendered');
}
if (!str_contains($html,'&lt;script&gt;') || !str_contains($html,'&lt;img')) {
    throw new RuntimeException('Escaping missing');
}
echo "KICKSTART_VIEW_TESTS_OK\n";
