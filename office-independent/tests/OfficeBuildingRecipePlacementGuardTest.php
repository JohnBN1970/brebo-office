<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/../web/modules/custom/brebo_calculation/src/Service/RecipeManager.php';

$reflection=new ReflectionClass(\Drupal\brebo_calculation\Service\RecipeManager::class);
$manager=$reflection->newInstanceWithoutConstructor();
$invalid=[
  ['review_status'=>'proposed','building_id'=>1,'recipe_version_id'=>1,'source_fingerprint'=>str_repeat('a',64),'paragraph_key'=>'kozijnen','quantity'=>1],
  ['review_status'=>'approved','building_id'=>1,'recipe_version_id'=>1,'source_fingerprint'=>'invalid','paragraph_key'=>'kozijnen','quantity'=>1],
  ['review_status'=>'approved','building_id'=>1,'recipe_version_id'=>1,'source_fingerprint'=>str_repeat('a',64),'paragraph_key'=>'kozijnen','quantity'=>-1],
];
foreach ($invalid as $proposal) {
  try {
    $manager->placeReviewedBuildingComponent(1,'v1',$proposal,1);
    throw new RuntimeException('unreviewed_or_invalid_component_accepted');
  } catch (InvalidArgumentException $expected) {}
}
echo "Unreviewed building components cannot enter recipe placement\n";
