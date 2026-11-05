<?php

namespace Tests\Unit;

use App\Services\GraphWriteLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class GraphWriteLockTest extends TestCase
{
    public function test_another_process_cannot_enter_until_the_owner_releases_the_lock(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graph-lock-');
        $input = new InputStream;
        $code = <<<'PHP'
require $argv[1];
$lock = new App\Services\GraphWriteLock($argv[2]);
$lock->run(function (): void {
    fwrite(STDOUT, "LOCKED\n");
    fflush(STDOUT);
    fgets(STDIN);
}, 0);
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, dirname(__DIR__, 2).'/vendor/autoload.php', $path]);
        $worker->setInput($input)->setTimeout(10);
        $worker->start();
        $output = '';

        try {
            $ready = $worker->waitUntil(function (string $type, string $buffer) use (&$output): bool {
                $output .= $buffer;

                return str_contains($output, 'LOCKED');
            });
            $this->assertTrue($ready, $worker->getErrorOutput());
            $lock = new GraphWriteLock($path);
            try {
                $lock->run(fn (): bool => true, 0);
                $this->fail('A second process entered a locked graph.');
            } catch (LockTimeoutException) {
                $this->addToAssertionCount(1);
            }
            $input->write("release\n");
            $input->close();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame('acquired', $lock->run(fn (): string => 'acquired', 0));
        } finally {
            $input->close();
            if ($worker->isRunning()) {
                $worker->stop();
            }
            unlink($path);
        }
    }

    public function test_nested_calls_share_ownership_and_exceptions_release_the_handle(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'graph-lock-');
        $lock = new GraphWriteLock($path);
        try {
            $this->assertSame(42, $lock->run(fn (): int => $lock->run(fn (): int => 42), 0));
            try {
                $lock->run(fn () => throw new RuntimeException('callback failed'), 0);
                $this->fail('The callback exception was swallowed.');
            } catch (RuntimeException $exception) {
                $this->assertSame('callback failed', $exception->getMessage());
            }
            $this->assertTrue((new GraphWriteLock($path))->run(fn (): bool => true, 0));
        } finally {
            unlink($path);
        }
    }
}
