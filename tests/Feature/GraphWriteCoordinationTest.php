<?php

namespace Tests\Feature;

use App\Services\GraphWriteLock;
use App\Services\Neo4jService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GraphWriteCoordinationTest extends TestCase
{
    public function test_business_writes_reset_profile_and_backups_share_the_same_lock(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'shared-lock-test'])->withHeader('X-CSRF-TOKEN', 'shared-lock-test');
        Storage::fake('local');
        $lock = $this->createMock(GraphWriteLock::class);
        $lock->expects($this->exactly(5))->method('run')->willThrowException(new LockTimeoutException('Gia phả đang được bảo trì.'));
        $this->app->instance(GraphWriteLock::class, $lock);
        $queries = [];
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('transaction');
        $neo4j->expects($this->never())->method('client');
        $neo4j->method('run')->willReturnCallback(function (string $query, array $params = []) use (&$queries): array {
            $queries[] = $query;

            return in_array($query, ['MATCH (n) RETURN count(n) AS total', 'MATCH ()-[r]->() RETURN count(r) AS total'], true)
                ? [['total' => 0]] : [];
        });
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->post('/relationships/parent', ['parentId' => 'A', 'childId' => 'B'])->assertSessionHasErrors('family');
        $this->post('/admin/reset', ['confirm_reset' => 'yes'])->assertSessionHas('error');
        $this->post('/admin/profile', ['sample' => 'nameIndex', 'name' => 'Nguyễn Minh Khang'])
            ->assertOk()->assertSee('Gia phả đang được bảo trì.');
        $this->post('/admin/backups')->assertSessionHas('error');
        $persons = [['id' => 'A', 'name' => 'A', 'gender' => 'M', 'birthYear' => 1980]];
        $json = json_encode([
            'format' => 'neo4j-family-tree', 'version' => 1, 'created_at' => now()->toIso8601String(), 'reason' => 'manual',
            'counts' => ['persons' => 1, 'parentOf' => 0, 'spouseOf' => 0], 'persons' => $persons, 'relationships' => [],
            'checksum' => hash('sha256', json_encode([$persons, []], JSON_THROW_ON_ERROR)),
        ], JSON_THROW_ON_ERROR);
        $this->post('/admin/restore', ['backup' => UploadedFile::fake()->createWithContent('backup.json', $json), 'confirm_restore' => 'yes'])
            ->assertSessionHas('error');

        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^(SHOW|MATCH)/', $query);
            $this->assertDoesNotMatchRegularExpression('/\b(CREATE|DROP|DELETE|SET)\b/', $query);
        }
    }

    public function test_seed_cannot_write_while_the_shared_lock_is_busy(): void
    {
        $lock = $this->createMock(GraphWriteLock::class);
        $lock->expects($this->once())->method('run')->willThrowException(new LockTimeoutException);
        $this->app->instance(GraphWriteLock::class, $lock);
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $this->app->instance(Neo4jService::class, $neo4j);
        $this->expectException(LockTimeoutException::class);

        Artisan::call('db:seed');
    }
}
