<?php

namespace App\Http\Controllers;

use App\Exceptions\Neo4jQueryException;
use App\Services\CypherGuard;
use App\Services\Neo4jService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Plan;
use Laudis\Neo4j\Databags\ProfiledQueryPlan;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    public function __construct(private readonly Neo4jService $neo4j, private readonly CypherGuard $guard) {}

    public function index(): View
    {
        return view('admin', $this->screen());
    }

    public function profile(Request $request): View
    {
        $validated = $request->validate([
            'sample' => ['required', Rule::in([...array_keys($this->samples()), 'nameIndex'])],
            'name' => ['required_if:sample,nameIndex', 'nullable', 'string', 'max:100'],
        ], [
            'sample.required' => 'Vui lòng chọn truy vấn mẫu.',
            'sample.in' => 'Truy vấn mẫu không hợp lệ.',
            'name.required_if' => 'Vui lòng nhập tên cần so sánh index.',
            'name.string' => 'Tên cần tìm phải là văn bản.',
            'name.max' => 'Tên cần tìm không được vượt quá 100 ký tự.',
        ]);

        if ($validated['sample'] === 'nameIndex') {
            abort_unless(app()->environment('local'), 403, 'So sánh bằng cách bỏ index chỉ được phép ở môi trường local.');
        }

        $extra = ['selected' => $validated['sample'], 'lookupName' => $validated['name'] ?? 'Nguyễn Minh Khang'];

        try {
            if ($validated['sample'] === 'nameIndex') {
                $extra['profiles'] = Cache::lock('neo4j-admin-maintenance', 60)->block(
                    3, fn (): array => $this->compareNameIndex($validated['name']),
                );
            } else {
                $sample = $this->samples()[$validated['sample']];
                $extra['profiles'] = [$this->runProfile(
                    $this->catalog($sample['query']), $sample['params'], $sample['label'],
                )];
            }
        } catch (Throwable $exception) {
            $extra['error'] = 'Không thể phân tích truy vấn. '.Neo4jQueryException::fromDriver($exception)->getMessage();
        }

        return view('admin', $this->screen($extra));
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 403, 'Chỉ được đặt lại dữ liệu khi APP_ENV=local.');
        $request->validate(['confirm_reset' => ['accepted']], [
            'confirm_reset.accepted' => 'Vui lòng xác nhận xóa dữ liệu demo trước khi đặt lại.',
        ]);

        try {
            $schema = $this->guard->splitStatements(File::get(base_path('neo4j/schema.cypher')));
            $seed = $this->guard->splitStatements(File::get(base_path('neo4j/seed.cypher')));

            if ($schema === [] || $seed === []) {
                throw new RuntimeException('Tệp schema hoặc seed rỗng.');
            }

            Cache::lock('neo4j-admin-maintenance', 60)->block(3, function () use ($schema, $seed): void {
                foreach ($schema as $statement) {
                    $this->neo4j->run($statement);
                }

                $this->neo4j->client()->writeTransaction(function (TransactionInterface $transaction) use ($seed): void {
                    $transaction->run('MATCH (n) DETACH DELETE n')->getSummary();

                    foreach ($seed as $statement) {
                        $transaction->run($statement)->getSummary();
                    }
                });
            });

            return to_route('admin.index')->with('success', 'Đã đặt lại và nạp dữ liệu mẫu. Dự kiến: 30 người, 36 PARENT_OF và 11 SPOUSE_OF.');
        } catch (Throwable $exception) {
            return to_route('admin.index')->with('error', 'Không thể đặt lại dữ liệu. '.Neo4jQueryException::fromDriver($exception)->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function screen(array $extra = []): array
    {
        $data = [
            'constraints' => [], 'indexes' => [], 'nodeTotal' => null, 'relationshipTotal' => null,
            'labels' => [], 'relationshipTypes' => [], 'error' => null, 'profiles' => [],
            'samples' => $this->samples(), 'selected' => 'person', 'lookupName' => 'Nguyễn Minh Khang',
            'local' => app()->environment('local'),
        ];

        try {
            $data['constraints'] = $this->neo4j->run('SHOW CONSTRAINTS');
            $data['indexes'] = $this->neo4j->run('SHOW INDEXES');
            $data['nodeTotal'] = $this->neo4j->run('MATCH (n) RETURN count(n) AS total')[0]['total'];
            $data['relationshipTotal'] = $this->neo4j->run('MATCH ()-[r]->() RETURN count(r) AS total')[0]['total'];
            $data['labels'] = $this->neo4j->run('MATCH (n) UNWIND labels(n) AS label RETURN label, count(*) AS total ORDER BY label');
            $data['relationshipTypes'] = $this->neo4j->run('MATCH ()-[r]->() RETURN type(r) AS type, count(*) AS total ORDER BY type');
        } catch (Throwable $exception) {
            $data['error'] = 'Không đọc được thông tin quản trị. '.Neo4jQueryException::fromDriver($exception)->getMessage();
        }

        return array_replace($data, $extra);
    }

    /** @return array<string, array{label: string, query: string, params: array<string, string>}> */
    private function samples(): array
    {
        return [
            'person' => ['label' => 'Tìm thành viên P010', 'query' => 'getPerson', 'params' => ['id' => 'P010']],
            'ancestors' => ['label' => 'Tổ tiên của P028', 'query' => 'ancestors', 'params' => ['id' => 'P028']],
            'cousins' => ['label' => 'Anh em họ của P010', 'query' => 'cousins', 'params' => ['id' => 'P010']],
            'topParents' => ['label' => 'Năm người có nhiều con nhất', 'query' => 'topParents', 'params' => []],
        ];
    }

    private function catalog(string $name): string
    {
        $source = str_replace("\r\n", "\n", File::get(base_path('neo4j/queries.cypher')));
        $pattern = '~// @name '.preg_quote($name, '~').'\s*\n// @params[^\n]*\n// @returns[^\n]*\n(.*?)(?=// @name|\z)~s';

        if (! preg_match($pattern, $source, $matches)) {
            throw new RuntimeException('Không tìm thấy truy vấn mẫu trong danh mục.');
        }

        $statements = $this->guard->splitStatements($matches[1]);

        if (count($statements) !== 1) {
            throw new RuntimeException('Truy vấn mẫu phải có đúng một câu lệnh.');
        }

        return $statements[0];
    }

    /** @return list<array<string, mixed>> */
    private function compareNameIndex(string $name): array
    {
        $query = 'MATCH (p:Person) WHERE p.name = $name RETURN p {.*} AS person';
        $createIndex = 'CREATE INDEX person_name IF NOT EXISTS FOR (p:Person) ON (p.name)';

        try {
            $this->neo4j->run('DROP INDEX person_name IF EXISTS');
            $without = $this->runProfile($query, ['name' => $name], 'Không có index person_name');
            $this->neo4j->run($createIndex);
            $this->neo4j->run('CALL db.awaitIndexes(30)');
            $with = $this->runProfile($query, ['name' => $name], 'Có index person_name');

            if ($without['results'] !== $with['results']) {
                throw new RuntimeException('Kết quả trước và sau index không giống nhau.');
            }

            return [$without, $with];
        } finally {
            $this->neo4j->run($createIndex);
            $this->neo4j->run('CALL db.awaitIndexes(30)');
        }
    }

    /** @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function runProfile(string $query, array $params, string $label): array
    {
        $started = hrtime(true);
        $summary = $this->neo4j->client()->run('PROFILE '.$query, $params)->getSummary();
        $profile = $summary->getProfiledPlan();
        $operators = [];
        $mode = 'PROFILE';
        $planText = $profile?->arguments->stringRepresentation;

        if ($profile !== null) {
            $this->profileRows($profile, $operators);
        } else {
            $mode = 'EXPLAIN';
            $plan = $this->neo4j->client()->run('EXPLAIN '.$query, $params)->getSummary()->getPlan();

            if ($plan !== null) {
                $this->planRows($plan, $operators);
                $planText = $plan->getArgs()->stringRepresentation;
            }
        }

        $elapsed = round((hrtime(true) - $started) / 1_000_000, 2);

        return [
            'label' => $label, 'query' => $query, 'params' => $params, 'mode' => $mode,
            'operators' => $operators, 'rows' => $profile === null ? null : ($profile->arguments->rows ?? $profile->records),
            'dbHits' => $profile === null ? null : array_sum(array_column($operators, 'dbHits')),
            'elapsed' => $elapsed, 'results' => $this->neo4j->run($query, $params),
            'planText' => $planText,
        ];
    }

    /**
     * Neo4j 5 supplies actual Rows in arguments; Laudis 3.7 may leave records at zero.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function profileRows(ProfiledQueryPlan $plan, array &$rows, int $depth = 0): void
    {
        $rows[] = ['operator' => $plan->operatorType, 'rows' => $plan->arguments->rows ?? $plan->records, 'dbHits' => $plan->dbHits,
            'estimated' => $plan->arguments->estimatedRows, 'details' => $plan->arguments->details, 'depth' => $depth];

        foreach ($plan->children as $child) {
            $this->profileRows($child, $rows, $depth + 1);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function planRows(Plan $plan, array &$rows, int $depth = 0): void
    {
        $rows[] = ['operator' => $plan->getOperator(), 'rows' => null, 'dbHits' => null,
            'estimated' => $plan->getArgs()->estimatedRows, 'details' => $plan->getArgs()->details, 'depth' => $depth];

        foreach ($plan->getChildren() as $child) {
            $this->planRows($child, $rows, $depth + 1);
        }
    }
}
