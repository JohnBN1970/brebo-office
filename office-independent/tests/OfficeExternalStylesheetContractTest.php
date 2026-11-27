<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$navigation=(string)file_get_contents($root.'/src/Intake/OfficeNavigation.php');
$router=(string)file_get_contents($root.'/public/index.php');
foreach (['office-navigation.css','office-theme.css'] as $asset) {
  if (!is_file($root.'/resources/'.$asset)) throw new RuntimeException('Stylesheet missing: '.$asset);
  if (!str_contains($navigation,'/office-test/assets/'.$asset)) throw new RuntimeException('External stylesheet link missing: '.$asset);
  if (!str_contains($router,"'/office-test/assets/".$asset."'")) throw new RuntimeException('Router mapping missing: '.$asset);
}
if (str_contains($navigation,"$". "css='<style>") || str_contains($navigation,"$"."css.='<style>")) {
  throw new RuntimeException('Navigation still emits inline CSS');
}
echo "OFFICE_EXTERNAL_CSS_OK\n";
