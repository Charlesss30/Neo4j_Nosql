<?php

namespace Tests\Feature;

use App\Services\Neo4jService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBackupInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'backup-input-test'])->withHeader('X-CSRF-TOKEN', 'backup-input-test');
        Storage::fake('local');
        $neo4j = $this->createMock(Neo4jService::class);
        $neo4j->expects($this->never())->method('run');
        $neo4j->expects($this->never())->method('transaction');
        $this->app->instance(Neo4jService::class, $neo4j);
    }

    public function test_backup_and_restore_are_forbidden_outside_local(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->post('/admin/backups')->assertForbidden();
        $this->post('/admin/restore')->assertForbidden();
        $this->get('/admin/backups/'.Str::uuid())->assertForbidden();
    }

    public function test_restore_requires_a_json_file_and_explicit_confirmation(): void
    {
        $this->from('/admin')->post('/admin/restore', ['confirm_restore' => 'yes'])
            ->assertRedirect('/admin')->assertSessionHasErrors('backup');
        $this->from('/admin')->post('/admin/restore', ['backup' => $this->upload($this->document())])
            ->assertRedirect('/admin')->assertSessionHasErrors('confirm_restore');
        $this->from('/admin')->post('/admin/restore', [
            'backup' => UploadedFile::fake()->createWithContent('backup.php', '{}'), 'confirm_restore' => 'yes',
        ])->assertRedirect('/admin')->assertSessionHasErrors('backup');
        $this->from('/admin')->post('/admin/restore', [
            'backup' => UploadedFile::fake()->create('backup.json', 10241), 'confirm_restore' => 'yes',
        ])->assertRedirect('/admin')->assertSessionHasErrors('backup');
    }

    public function test_invalid_json_version_and_corrupted_files_are_rejected_before_database_access(): void
    {
        $changed = $this->document();
        $changed['persons'][0]['name'] = 'Changed without updating checksum';
        $unsupportedVersion = $this->document();
        $unsupportedVersion['version'] = 2;

        foreach (['{broken', '{}', json_encode($changed), json_encode($unsupportedVersion)] as $json) {
            $this->from('/admin')->post('/admin/restore', [
                'backup' => UploadedFile::fake()->createWithContent('backup.json', $json), 'confirm_restore' => 'yes',
            ])->assertRedirect('/admin')->assertSessionHasErrors('backup');
        }
    }

    public function test_invalid_people_and_relationships_are_rejected_even_with_a_valid_checksum(): void
    {
        $mutations = [
            'string year' => function (array &$data): void {
                $data['persons'][0]['birthYear'] = '1980';
            },
            'duplicate id' => function (array &$data): void {
                $data['persons'][1]['id'] = 'A';
            },
            'unknown property' => function (array &$data): void {
                $data['persons'][0]['password'] = 'unexpected';
            },
            'death before birth' => function (array &$data): void {
                $data['persons'][0]['deathYear'] = 1970;
            },
            'missing endpoint' => function (array &$data): void {
                $data['relationships'][0]['to'] = 'MISSING';
            },
            'self parent' => function (array &$data): void {
                $data['relationships'][0]['to'] = 'A';
            },
            'parent cycle' => function (array &$data): void {
                $data['relationships'][] = ['from' => 'B', 'to' => 'A', 'type' => 'PARENT_OF', 'properties' => []];
            },
            'third parent' => function (array &$data): void {
                $data['persons'][] = ['id' => 'D', 'name' => 'D', 'gender' => 'M', 'birthYear' => 1970];
                $data['relationships'][] = ['from' => 'C', 'to' => 'B', 'type' => 'PARENT_OF', 'properties' => []];
                $data['relationships'][] = ['from' => 'D', 'to' => 'B', 'type' => 'PARENT_OF', 'properties' => []];
            },
            'reversed duplicate marriage' => function (array &$data): void {
                $data['relationships'][] = ['from' => 'C', 'to' => 'A', 'type' => 'SPOUSE_OF', 'properties' => []];
            },
            'early marriage year' => function (array &$data): void {
                $data['relationships'][1]['properties']['marriageYear'] = 1970;
            },
            'arbitrary relationship type' => function (array &$data): void {
                $data['relationships'][0]['type'] = 'OTHER';
            },
        ];

        foreach ($mutations as $mutation) {
            $data = $this->document();
            $mutation($data);
            $data = $this->sign($data);
            $this->from('/admin')->post('/admin/restore', ['backup' => $this->upload($data), 'confirm_restore' => 'yes'])
                ->assertRedirect('/admin')->assertSessionHasErrors('backup');
        }
    }

    public function test_download_does_not_accept_missing_ids_or_paths(): void
    {
        $this->get('/admin/backups/'.Str::uuid())->assertNotFound();
        $this->get('/admin/backups/composer.json')->assertNotFound();
        $this->get('/admin/backups/..%2F.env')->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return $this->sign([
            'format' => 'neo4j-family-tree', 'version' => 1, 'created_at' => now()->toIso8601String(), 'reason' => 'manual',
            'persons' => [
                ['id' => 'A', 'name' => 'A', 'gender' => 'M', 'birthYear' => 1980],
                ['id' => 'B', 'name' => 'B', 'gender' => 'F', 'birthYear' => 2000],
                ['id' => 'C', 'name' => 'C', 'gender' => 'F', 'birthYear' => 1979],
            ],
            'relationships' => [
                ['from' => 'A', 'to' => 'B', 'type' => 'PARENT_OF', 'properties' => []],
                ['from' => 'A', 'to' => 'C', 'type' => 'SPOUSE_OF', 'properties' => ['marriageYear' => 2001]],
            ],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function upload(array $data): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('backup.json', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sign(array $data): array
    {
        $data['checksum'] = hash('sha256', json_encode([$data['persons'], $data['relationships']], JSON_THROW_ON_ERROR));
        $data['counts'] = [
            'persons' => count($data['persons']),
            'parentOf' => count(array_filter($data['relationships'], fn (array $edge): bool => $edge['type'] === 'PARENT_OF')),
            'spouseOf' => count(array_filter($data['relationships'], fn (array $edge): bool => $edge['type'] === 'SPOUSE_OF')),
        ];

        return $data;
    }
}
