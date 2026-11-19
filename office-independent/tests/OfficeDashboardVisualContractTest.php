<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$php=file_get_contents($root.'/public/office-personal-dashboard.php');
$js=file_get_contents($root.'/resources/office-dashboard.js');
$css=file_get_contents($root.'/resources/office-dashboard.css');
foreach (['php'=>$php,'js'=>$js,'css'=>$css] as $name=>$source) {
  if (!is_string($source)||$source==='') throw new RuntimeException("Dashboard $name source unavailable");
}
$requiredPhp=[
  "Catalog::visible(\$suite['widgets'],\$monitor)",
  "if(\$json)",
  "if(\$entry['id']==='projects'",
  "if(\$entry['id']==='quality')",
  "'kind'=>'project-status'",
  "'kind'=>'quality-counts'",
  "is_int(\$totalProjects)",
  "is_int(\$criticalProjects)",
  "is_int(\$waitingProjects)",
  "if(is_int(\$value)&&\$value>=0)",
];
foreach($requiredPhp as $needle)if(!str_contains($php,$needle))throw new RuntimeException("Visual KPI source contract missing: $needle");
foreach(['project-status','quality-counts','replaceChildren()','credentials: \'same-origin\'','redirect: \'error\'','!response.ok','Array.isArray(data.widgets)'] as $needle){
  if(!str_contains($js,$needle))throw new RuntimeException("Wallboard refresh contract missing: $needle");
}
foreach(['office-dash-mini-chart','office-dash-mini-quality','office-dash-status-track','office-dash-bar-track','prefers-reduced-motion'] as $needle){
  if(!str_contains($css,$needle))throw new RuntimeException("Visual KPI styling missing: $needle");
}
if(str_contains($php,"\$_GET['operator_id']")||str_contains($php,"\$_POST['operator_id']"))throw new RuntimeException('Untrusted operator identity');
echo "Dashboard visual KPI source contracts passed\n";
