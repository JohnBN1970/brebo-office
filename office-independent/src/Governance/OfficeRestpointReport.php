<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

use Brebo\Office\Governance\Contract\OfficeGovernanceStore;

final class OfficeRestpointReport
{
    public function __construct(private readonly OfficeGovernanceStore $store) {}

    public function build(string $actor,int $project): array
    {
        if ($project<1) throw new \InvalidArgumentException('Ongeldig project.');
        $buildings=[];
        $totals=['total'=>0,'open'=>0,'recheck'=>0,'accepted'=>0,'blocking'=>0];
        foreach ($this->store->contexts($actor,'quality') as $context) {
            if ((int)$context['project_id']!==$project) continue;
            $building=(int)$context['building_id'];
            if (isset($buildings[$building])) continue;
            $rows=[];
            foreach ($this->store->listing($actor,'quality',$project,$building) as $row) {
                $p=$row['payload'];
                if (($p['record_kind']??null)!=='restpunt') continue;
                $status=(string)$row['status'];
                $bucket=match($status){'accepted'=>'accepted','recheck'=>'recheck',default=>'open'};
                $blocking=$status!=='accepted' && !empty($p['blocks_release']);
                ++$totals['total'];++$totals[$bucket];
                if ($blocking) ++$totals['blocking'];
                $rows[]=['id'=>(int)$row['id'],'title'=>$row['title'],'status'=>$status,
                    'revision'=>(int)$row['revision'],'dwelling_id'=>(int)($p['dwelling_id']??0),
                    'room_id'=>(int)($p['room_id']??0),'owner'=>$p['owner']??null,
                    'due_date'=>$p['due_date']??null,'observation'=>$p['observation']??null,
                    'remedy'=>$p['remedy']??null,'evidence'=>$p['evidence']??null,'blocking'=>$blocking];
            }
            $buildings[$building]=['id'=>$building,'name'=>$context['building_name'],'restpoints'=>$rows];
        }
        if (!$buildings) throw new \DomainException('Geen toegankelijke projectgebouwen.');
        return ['kind'=>'concept','project_id'=>$project,'generated_at'=>gmdate('c'),
            'totals'=>$totals,'buildings'=>array_values($buildings)];
    }
}
