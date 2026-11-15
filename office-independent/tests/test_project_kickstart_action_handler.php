<?php
declare(strict_types=1);
foreach (['OfficeProjectKickstartPolicy','OfficeProjectKickstartRepository','OfficeProjectKickstartQuestionsRepository','OfficeProjectKickstartActionHandler'] as $class) {
    require_once dirname(__DIR__).'/src/Project/'.$class.'.php';
}
use Brebo\Office\Project\OfficeProjectKickstartActionHandler;
use Brebo\Office\Project\OfficeProjectKickstartRepository;
use Brebo\Office\Project\OfficeProjectKickstartQuestionsRepository;
$db = new PDO('sqlite::memory:');
$handler = new OfficeProjectKickstartActionHandler(new OfficeProjectKickstartRepository($db),new OfficeProjectKickstartQuestionsRepository($db));
$base=['dossier_id'=>'3','expected_revision'=>'2','csrf_token'=>'valid','kickstart_action'=>'submit_for_approval'];
$context=['actor_user_id'=>4,'authorized_dossier_id'=>3,'csrf_token'=>'valid','can_submit'=>false];
$cases=[
    [$base+[],array_replace($context,['csrf_token'=>'different'])],
    [array_replace($base,['expected_revision'=>'0']),$context],
    [$base,array_replace($context,['authorized_dossier_id'=>5])],
    [$base,array_replace($context,['actor_user_id'=>0])],
];
foreach ($cases as [$post,$ctx]) {
    try {
        $handler->handle($post,$ctx);
        throw new RuntimeException('Security boundary unexpectedly accepted');
    } catch (DomainException $expected) {
        // All invalid requests must fail before database access.
    }
}
echo "KICKSTART_ACTION_SECURITY_TESTS_OK\n";
