<?php

namespace Tests\Unit;

use App\Exceptions\Neo4jQueryException;
use App\Services\Neo4jService;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Neo4jServiceTest extends TestCase
{
    public function test_nested_driver_values_become_plain_arrays_without_losing_types(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $summary = null;
        $result = new SummarizedResult($summary, [new CypherMap([
            'nodes' => new CypherList([new CypherMap([
                'id' => 'P001', 'name' => 'Nguyễn An', 'birthYear' => 1950, 'note' => null,
            ])]),
            'exists' => true,
            'total' => 0,
            'edges' => new CypherList,
        ])]);
        $client->expects($this->once())->method('run')->willReturn($result);

        $this->assertSame([[
            'nodes' => [['id' => 'P001', 'name' => 'Nguyễn An', 'birthYear' => 1950, 'note' => null]],
            'exists' => true, 'total' => 0, 'edges' => [],
        ]], (new Neo4jService($client))->run('test statement'));
    }

    public function test_query_and_untrusted_parameters_are_forwarded_separately(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $summary = null;
        $params = ['q' => "' MATCH (n) DETACH DELETE n //", 'skip' => 0, 'limit' => 10];
        $client->expects($this->once())->method('run')->with('catalog statement', $params)
            ->willReturn(new SummarizedResult($summary));

        $service = new Neo4jService($client);
        $this->assertSame([], $service->run('catalog statement', $params));
        $this->assertSame($client, $service->client());
    }

    public function test_authentication_errors_use_vietnamese_and_hide_driver_details(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('run')->willThrowException(new RuntimeException('Neo.ClientError.Security.Unauthorized private-detail'));
        $service = new Neo4jService($client);

        $this->assertFalse($service->ping());
        $this->assertStringContainsString('Xác thực Neo4j thất bại', $service->lastError());
        $this->assertStringNotContainsString('private-detail', $service->lastError());
    }

    public function test_errors_while_consuming_lazy_results_are_also_translated(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $summary = null;
        $client->method('run')->willReturn(new SummarizedResult($summary, function () {
            yield new CypherMap(['ok' => 1]);
            throw new RuntimeException('Socket connection refused');
        }));

        $this->expectException(Neo4jQueryException::class);
        $this->expectExceptionMessage('Không thể kết nối Neo4j');
        (new Neo4jService($client))->run('test statement');
    }

    public function test_successful_retry_clears_the_previous_error(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $attempt = 0;
        $client->method('run')->willReturnCallback(function () use (&$attempt): SummarizedResult {
            if ($attempt++ === 0) {
                throw new RuntimeException('Connection refused');
            }

            $summary = null;

            return new SummarizedResult($summary, [new CypherMap(['ok' => 1])]);
        });
        $service = new Neo4jService($client);

        $this->assertFalse($service->ping());
        $this->assertNotNull($service->lastError());
        $this->assertTrue($service->ping());
        $this->assertNull($service->lastError());
    }
}
