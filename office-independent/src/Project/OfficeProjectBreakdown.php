<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

/**
 * Pure WBS/RBS projections. Persistence remains with native Office repositories.
 * No duplicate risk or budget ledger is introduced.
 */
final class OfficeProjectBreakdown
{
    /** @param array<int,array<string,mixed>> $items */
    public static function tree(array $items): array
    {
        $nodes = [];
        foreach ($items as $item) {
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || isset($nodes[$id])) {
                throw new \InvalidArgumentException('Ongeldig of dubbel breakdown-ID.');
            }
            $parent = $item['parent_id'] ?? null;
            if ($parent !== null && (!is_numeric($parent) || (int)$parent < 1)) {
                throw new \InvalidArgumentException('Ongeldig bovenliggend onderdeel.');
            }
            $nodes[$id] = ['id'=>$id, 'parent_id'=>$parent === null ? null : (int)$parent,
                'code'=>(string)($item['code'] ?? ''), 'title'=>(string)($item['title'] ?? ''),
                'children'=>[]];
            if ($nodes[$id]['title'] === '') throw new \InvalidArgumentException('Omschrijving verplicht.');
        }
        foreach ($nodes as $id=>$node) {
            $parent = $node['parent_id'];
            if ($parent !== null && !isset($nodes[$parent])) {
                throw new \DomainException('Bovenliggend onderdeel ontbreekt.');
            }
            $visited = [$id=>true];
            while ($parent !== null) {
                if (isset($visited[$parent])) throw new \DomainException('Cyclische breakdown.');
                $visited[$parent] = true;
                $parent = $nodes[$parent]['parent_id'];
            }
        }
        $children = [];
        foreach ($nodes as $id=>$node) $children[$node['parent_id'] ?? 0][] = $id;
        $build = static function (int $parent) use (&$build, $nodes, $children): array {
            $result = [];
            foreach ($children[$parent] ?? [] as $id) {
                $node = $nodes[$id];
                $node['children'] = $build($id);
                $result[] = $node;
            }
            return $result;
        };
        return $build(0);
    }

    /** Native Office risks remain the source of truth for probability and impact. */
    public static function riskMatrix(array $risks): array
    {
        $cells = array_fill(1, 5, array_fill(1, 5, 0));
        $totals = ['low'=>0, 'medium'=>0, 'high'=>0, 'critical'=>0];
        foreach ($risks as $risk) {
            if (($risk['status'] ?? '') === 'closed') continue;
            $payload = $risk['payload'] ?? $risk;
            $probability = filter_var($payload['probability'] ?? null, FILTER_VALIDATE_INT);
            $impact = filter_var($payload['impact'] ?? null, FILTER_VALIDATE_INT);
            if ($probability === false || $impact === false || $probability < 1 || $probability > 5 || $impact < 1 || $impact > 5) {
                throw new \InvalidArgumentException('Risicoscore moet tussen 1 en 5 liggen.');
            }
            $cells[$probability][$impact]++;
            $score = $probability * $impact;
            $band = $score >= 20 ? 'critical' : ($score >= 12 ? 'high' : ($score >= 5 ? 'medium' : 'low'));
            $totals[$band]++;
        }
        return ['cells'=>$cells, 'totals'=>$totals];
    }
}
