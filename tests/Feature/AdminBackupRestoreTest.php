<?php

namespace Tests\Feature;

use App\Services\FamilyBackupService;
use App\Services\Neo4jService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laudis\Neo4j\Contracts\ClientInterface;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Neo4jTestCase;

#[Group('isolated-restore')]
class AdminBackupRestoreTest extends Neo4jTestCase
{
    protected function setUp(): void
    {
        if (getenv('NEO4J_RESTORE_TESTS') !== '1') {
            $this->markTestSkipped('Restore tests require NEO4J_RESTORE_TESTS=1 and a dedicated disposable Neo4j database.');
        }

        parent::setUp();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'backup-restore-test'])->withHeader('X-CSRF-TOKEN', 'backup-restore-test');
        Storage::fake('local');
    }

    public function test_download_restore_round_trip_preserves_properties_types_and_edges_and_saves_a_safety_copy(): void
    {
        $this->createTestPerson('T_BACKUP_PARENT', 1980);
        $this->createTestPerson('T_BACKUP_CHILD', 2010, 'F');
        $this->family->updatePerson('T_BACKUP_CHILD', "Nguyễn O'Brien <script>", 'F', 2010, null, 'Huế', 'Nội dung có dấu ; và dấu nháy.');
        $this->family->addParentOf('T_BACKUP_PARENT', 'T_BACKUP_CHILD');
        $originalGraph = $this->family->graphAll()[0];
        $download = $this->post('/admin/backups')->assertOk()->assertDownload();
        $json = $download->streamedContent();
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame(['persons' => 32, 'parentOf' => 37, 'spouseOf' => 11], $data['counts']);
        $this->assertTrue(is_int($data['persons'][0]['birthYear']));
        $this->createTestPerson('T_AFTER_BACKUP', 2000);
        $this->family->deletePerson('T_BACKUP_CHILD');

        $this->restore($json)->assertRedirect(route('admin.index'))->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame($originalGraph, $this->family->graphAll()[0]);
        $history = $this->app->make(FamilyBackupService::class)->history();
        $this->assertCount(2, $history);
        $safety = array_values(array_filter($history, fn (array $backup): bool => $backup['reason'] === 'before_restore'))[0];
        $saved = $this->get(route('admin.backups.download', $safety['id']))->assertOk()->assertDownload();
        $safetyData = json_decode($saved->streamedContent(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertContains('T_AFTER_BACKUP', array_column($safetyData['persons'], 'id'));
        $this->assertNotContains('T_BACKUP_CHILD', array_column($safetyData['persons'], 'id'));
        $this->get('/admin')->assertOk()->assertSee('Sao lưu &amp; khôi phục gia phả', false)
            ->assertSee('Tự lưu trước khôi phục')->assertSee('Tải JSON');
    }

    public function test_failure_after_deleting_data_rolls_back_the_entire_restore(): void
    {
        $json = $this->post('/admin/backups')->assertOk()->streamedContent();
        $this->createTestPerson('T_KEEP_AFTER_FAILED_RESTORE');
        $before = $this->family->graphAll()[0];
        $faulty = new class($this->app->make(ClientInterface::class)) extends Neo4jService
        {
            public function run(string $cypher, array $params = []): array
            {
                if (str_contains($cypher, 'CREATE (a)-[r:PARENT_OF]')) {
                    throw new RuntimeException('Injected restore failure after delete and person creation');
                }

                return parent::run($cypher, $params);
            }
        };
        $this->app->instance(Neo4jService::class, $faulty);

        $this->restore($json)->assertRedirect(route('admin.index'))->assertSessionHas('error');
        $this->assertSame($before, $this->family->graphAll()[0]);
        $this->assertCount(2, Storage::disk('local')->files('family-backups'));
    }

    public function test_restore_does_not_change_data_if_the_safety_copy_cannot_be_saved(): void
    {
        $json = $this->post('/admin/backups')->assertOk()->streamedContent();
        $this->createTestPerson('T_KEEP_WHEN_STORAGE_FAILS');
        $before = $this->family->graphAll()[0];
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->expects($this->once())->method('put')->willReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->restore($json)->assertRedirect(route('admin.index'))->assertSessionHas('error');
        $this->assertSame($before, $this->family->graphAll()[0]);
    }

    public function test_restore_refuses_to_delete_unrelated_labels(): void
    {
        $json = $this->post('/admin/backups')->assertOk()->streamedContent();
        $this->neo4j->run("CREATE (:BackupTestOther {id: 'keep'})");

        try {
            $this->restore($json)->assertSessionHasErrors('backup');
            $this->assertSame(1, $this->neo4j->run('MATCH (n:BackupTestOther) RETURN count(n) AS total')[0]['total']);
        } finally {
            $this->neo4j->run('MATCH (n:BackupTestOther) DETACH DELETE n');
        }
    }

    public function test_reset_failure_rolls_back_the_deleted_graph_and_restores_the_query_runner(): void
    {
        $this->createTestPerson('T_RESET_KEEP');
        $before = $this->family->graphAll()[0];
        $faulty = new class($this->app->make(ClientInterface::class)) extends Neo4jService
        {
            private bool $deleted = false;

            public function run(string $cypher, array $params = []): array
            {
                if ($this->deleted && str_contains($cypher, 'PARENT_OF')) {
                    throw new RuntimeException('Injected reset failure after deleting the graph');
                }
                $result = parent::run($cypher, $params);
                $this->deleted = $this->deleted || $cypher === 'MATCH (n) DETACH DELETE n';

                return $result;
            }
        };
        $this->app->instance(Neo4jService::class, $faulty);
        $this->post('/admin/reset', ['confirm_reset' => 'yes'])->assertSessionHas('error');
        $this->assertSame($before, $this->family->graphAll()[0]);
        $this->assertTrue($faulty->ping());
        $this->post('/admin/backups')->assertOk()->assertDownload();
    }

    public function test_failed_name_profile_restores_the_real_index_in_finally(): void
    {
        $profileClient = $this->createMock(ClientInterface::class);
        $profileClient->method('run')->willThrowException(new RuntimeException('Injected PROFILE failure'));
        $faulty = new class($this->app->make(ClientInterface::class), $profileClient) extends Neo4jService
        {
            public function __construct(ClientInterface $client, private readonly ClientInterface $profileClient)
            {
                parent::__construct($client);
            }

            public function client(): ClientInterface
            {
                return $this->profileClient;
            }
        };
        $this->app->instance(Neo4jService::class, $faulty);
        $this->post('/admin/profile', ['sample' => 'nameIndex', 'name' => 'Nguyễn Minh Khang'])
            ->assertOk()->assertSee('Không thể phân tích truy vấn.');
        $this->assertSame([['state' => 'ONLINE']], $this->neo4j->run("SHOW INDEXES YIELD name, state WHERE name = 'person_name' RETURN state"));
        $this->assertTrue($faulty->ping());
        $this->get('/admin')->assertOk();
    }

    public function test_empty_graph_can_be_backed_up_and_restored(): void
    {
        $original = $this->post('/admin/backups')->assertOk()->streamedContent();

        try {
            $this->neo4j->run('MATCH (n) DETACH DELETE n');
            $empty = $this->post('/admin/backups')->assertOk()->streamedContent();
            $this->assertSame([], json_decode($empty, true, 64, JSON_THROW_ON_ERROR)['persons']);
            $this->createTestPerson('T_EMPTY_GRAPH_MEMBER');
            $this->restore($empty)->assertSessionHas('success');
            $this->assertSame(['nodes' => [], 'edges' => []], $this->family->graphAll()[0]);
        } finally {
            $this->restore($original)->assertSessionHas('success');
        }
    }

    private function restore(string $json): TestResponse
    {
        return $this->from('/admin')->post('/admin/restore', [
            'backup' => UploadedFile::fake()->createWithContent('family-tree.json', $json),
            'confirm_restore' => 'yes',
        ]);
    }
}
