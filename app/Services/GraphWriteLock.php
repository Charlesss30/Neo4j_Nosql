<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use RuntimeException;

class GraphWriteLock
{
    /** @var resource|null */
    private mixed $handle = null;

    public function __construct(private readonly ?string $path = null) {}

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback, ?int $waitSeconds = null): mixed
    {
        if (is_resource($this->handle)) {
            return $callback();
        }

        $path = $this->path ?? storage_path('framework/cache/graph-write.lock');
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Không thể mở khóa ghi gia phả.');
        }
        $deadline = hrtime(true) + max(0, $waitSeconds ?? (int) config('neo4j.write_lock_wait_seconds', 5)) * 1_000_000_000;
        $acquired = false;

        try {
            do {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    $acquired = true;
                    $this->handle = $handle;

                    return $callback();
                }
                if (hrtime(true) >= $deadline) {
                    throw new LockTimeoutException('Gia phả đang được cập nhật hoặc bảo trì. Vui lòng thử lại sau.');
                }
                usleep(20_000);
            } while (true);
        } finally {
            if ($acquired) {
                $this->handle = null;
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
}
