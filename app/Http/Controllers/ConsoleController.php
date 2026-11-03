<?php

namespace App\Http\Controllers;

use App\Services\CypherGuard;
use App\Services\Neo4jService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Throwable;

class ConsoleController extends Controller
{
    public function index(): View
    {
        return view('console', $this->screen());
    }

    public function run(Request $request, Neo4jService $neo4j, CypherGuard $guard): Response
    {
        $query = $request->input('query', '');
        $data = $this->screen(is_string($query) ? $query : '');
        $started = hrtime(true);
        $status = 200;

        try {
            if (! is_string($query)) {
                throw new InvalidArgumentException('Truy vấn phải là chuỗi văn bản.');
            }

            $guard->validate($query);
            $rows = $neo4j->run($query);
            $data['total'] = count($rows);
            $data['rows'] = array_slice($rows, 0, 500);
            $data['executed'] = true;

            foreach ($data['rows'] as $row) {
                $data['columns'] = array_values(array_unique([...$data['columns'], ...array_keys($row)]));
            }
        } catch (InvalidArgumentException $exception) {
            $data['error'] = $exception->getMessage();
            $status = 422;
        } catch (Throwable $exception) {
            $data['error'] = 'Không chạy được truy vấn. '.$exception->getMessage();
            $status = 422;
        }

        $data['elapsed'] = round((hrtime(true) - $started) / 1_000_000, 2);

        return response()->view('console', $data, $status);
    }

    /** @return array<string, mixed> */
    private function screen(string $query = ''): array
    {
        return [
            'query' => $query, 'rows' => [], 'columns' => [], 'total' => 0,
            'elapsed' => null, 'executed' => false, 'error' => null,
            'examples' => [
                ['label' => 'Danh sách thành viên', 'query' => 'MATCH (p:Person) RETURN p {.*} AS person ORDER BY person.id LIMIT 10;'],
                ['label' => 'Tổ tiên của P028', 'query' => "MATCH (p:Person)-[:PARENT_OF*1..]->(:Person {id: 'P028'}) RETURN DISTINCT p {.*} AS person ORDER BY person.id;"],
                ['label' => 'Anh em họ của P010', 'query' => "MATCH (p:Person {id: 'P010'})<-[:PARENT_OF]-(parent:Person)<-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(uncle:Person)-[:PARENT_OF]->(cousin:Person) WHERE uncle <> parent AND cousin <> p AND NOT EXISTS { MATCH (p)<-[:PARENT_OF]-(:Person)-[:PARENT_OF]->(cousin) } RETURN DISTINCT cousin {.*} AS person ORDER BY person.id;"],
                ['label' => 'Đường đi ngắn nhất', 'query' => "MATCH (a:Person {id: 'P010'}), (b:Person {id: 'P012'}) MATCH path = shortestPath((a)-[:PARENT_OF|SPOUSE_OF*0..]-(b)) RETURN [p IN nodes(path) | p {.id, .name}] AS nodes, [r IN relationships(path) | type(r)] AS relTypes, [r IN relationships(path) | startNode(r).id] AS relFrom;"],
                ['label' => 'Người có nhiều con', 'query' => 'MATCH (p:Person)-[:PARENT_OF]->(child:Person) RETURN p.id AS id, p.name AS name, count(DISTINCT child) AS childCount ORDER BY childCount DESC, id LIMIT 5;'],
                ['label' => 'Thống kê giới tính', 'query' => 'MATCH (p:Person) RETURN p.gender AS gender, count(*) AS total ORDER BY gender;'],
                ['label' => 'Hai hôn nhân của P005', 'query' => "MATCH (:Person {id: 'P005'})-[r:SPOUSE_OF]-(p:Person) RETURN p {.*} AS person, r.marriageYear AS marriageYear ORDER BY person.id;"],
            ],
        ];
    }
}
