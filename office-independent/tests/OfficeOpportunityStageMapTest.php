<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityWorkflow.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityStageMap.php';

use Brebo\Office\Workflow\OfficeOpportunityStageMap as Map;

if (Map::workflowStage('prospect') !== 'nieuw') throw new RuntimeException('Legacy prospect mapping failed');
if (Map::workflowStage('offerte') !== 'offerte') throw new RuntimeException('Native stage mapping failed');
if (Map::workflowStage('unknown-custom-stage') !== null) throw new RuntimeException('Unknown stage must remain unmapped');
if (Map::label('unknown-custom-stage') !== 'unknown-custom-stage') throw new RuntimeException('Unknown label must be preserved');
if (Map::nextStages('prospect') !== ['in_behandeling']) throw new RuntimeException('Legacy next steps failed');
if (Map::nextStages('unknown-custom-stage') !== []) throw new RuntimeException('Unknown stage must not transition');
echo "Opportunity stage compatibility: OK\n";
