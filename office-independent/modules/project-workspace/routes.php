<?php
declare(strict_types=1);
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_PROJECT_WORKSPACE_ENABLED')!=='enabled')return [];
return ['/office-test/projecten/beheer'=>'office-project-workspace.php'];
