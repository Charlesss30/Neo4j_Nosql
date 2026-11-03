<?php

namespace App\Services;

class FamilyGraphService
{
    public function __construct(
        private readonly FamilyRepository $family,
        private readonly FamilyService $service,
    ) {}

    /** @return array{root: ?string, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} */
    public function tree(?string $root, bool $all = false): array
    {
        $root ??= isset($this->family->getPerson('P001')[0]) ? 'P001' : ($this->family->roots()[0]['person']['id'] ?? null);
        if ($root === null) {
            return ['root' => null, 'nodes' => [], 'edges' => []];
        }
        $this->service->person($root);
        $graph = ($all ? $this->family->graphAll() : $this->family->subtree($root))[0] ?? ['nodes' => [], 'edges' => []];
        $levels = [];
        foreach ($this->family->generations($root) as $row) {
            $levels[$row['person']['id']] = (int) $row['generation'] - 1;
        }

        $groups = [];
        foreach ($graph['nodes'] as $person) {
            $groups[$person['id']] = $person['id'];
        }
        foreach ($graph['edges'] as $edge) {
            if ($edge['type'] === 'SPOUSE_OF') {
                $oldGroup = $groups[$edge['to']];
                $newGroup = $groups[$edge['from']];
                foreach ($groups as $id => $group) {
                    if ($group === $oldGroup) {
                        $groups[$id] = $newGroup;
                    }
                }
            }
        }
        $groupLevels = [];
        foreach ($levels as $id => $level) {
            if (isset($groups[$id])) {
                $groupLevels[$groups[$id]] = min($groupLevels[$groups[$id]] ?? $level, $level);
            }
        }
        foreach ($groups as $id => $group) {
            $levels[$id] = $groupLevels[$group] ?? 0;
        }

        return ['root' => $root, ...$this->present($graph, $levels)];
    }

    /** @return array{sentence: string, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} */
    public function relation(string $a, string $b): array
    {
        $people = [$a => $this->service->person($a), $b => $this->service->person($b)];
        if ($a === $b) {
            return ['sentence' => 'Đây là cùng một người.', ...$this->present(['nodes' => [$people[$a]], 'edges' => []])];
        }
        $path = $this->family->relationPath($a, $b)[0] ?? null;
        if ($path === null || $path['nodes'] === []) {
            return ['sentence' => 'Không có quan hệ huyết thống hoặc hôn nhân trong gia phả.', 'nodes' => [], 'edges' => []];
        }
        foreach ($path['nodes'] as $node) {
            $people[$node['id']] ??= $this->service->person($node['id']);
        }
        $sentences = [];
        $edges = [];
        foreach ($path['relTypes'] as $index => $type) {
            $first = $people[$path['nodes'][$index]['id']];
            $second = $people[$path['nodes'][$index + 1]['id']];
            $outgoing = $path['relFrom'][$index] === $first['id'];
            $role = $type === 'SPOUSE_OF'
                ? ($first['gender'] === 'M' ? 'chồng' : 'vợ')
                : ($outgoing ? ($first['gender'] === 'M' ? 'cha' : 'mẹ') : ($first['gender'] === 'M' ? 'con trai' : 'con gái'));
            $sentences[] = $first['name'].' là '.$role.' của '.$second['name'];
            $edges[] = ['from' => $outgoing ? $first['id'] : $second['id'], 'to' => $outgoing ? $second['id'] : $first['id'], 'type' => $type];
        }
        $nodes = array_map(fn (array $node): array => $people[$node['id']], $path['nodes']);

        return ['sentence' => implode(', ', $sentences).'.', ...$this->present(compact('nodes', 'edges'))];
    }

    /**
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     * @param  array<string, int>  $levels
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function present(array $graph, array $levels = []): array
    {
        $nodes = [];
        foreach ($graph['nodes'] as $person) {
            $deceased = isset($person['deathYear']);
            $nodes[] = [
                'id' => $person['id'], 'name' => $person['name'], 'gender' => $person['gender'],
                'label' => ($deceased ? '† ' : '').$person['name']."\n".$person['id'],
                'title' => e($person['name'].' · '.($person['birthYear'] ?? '—').($deceased ? ' – '.$person['deathYear'] : '')),
                'color' => $deceased ? '#d9dde1' : ($person['gender'] === 'M' ? '#b9d9ff' : '#ffd1e2'),
                ...($levels === [] ? [] : ['level' => $levels[$person['id']] ?? 0]),
            ];
        }
        $edges = [];
        foreach ($graph['edges'] as $index => $edge) {
            $spouse = $edge['type'] === 'SPOUSE_OF';
            $edges[] = [
                'id' => 'edge-'.$index, 'from' => $edge['from'], 'to' => $edge['to'], 'type' => $edge['type'],
                'arrows' => $spouse ? '' : 'to', 'dashes' => $spouse, 'smooth' => false,
                'color' => $spouse ? '#ba6686' : '#507766',
                'title' => $spouse ? 'Vợ/chồng'.(isset($edge['marriageYear']) ? ' · '.$edge['marriageYear'] : '') : 'Cha mẹ → con',
            ];
        }

        return compact('nodes', 'edges');
    }
}
