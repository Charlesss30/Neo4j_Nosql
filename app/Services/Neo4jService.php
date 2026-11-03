<?php

namespace App\Services;

use App\Exceptions\Neo4jQueryException;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Traversable;
use UnexpectedValueException;

class Neo4jService
{
    private ?string $lastError = null;

    private ?TransactionInterface $activeTransaction = null;

    public function __construct(
        private readonly ClientInterface $neo4j,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function run(string $cypher, array $params = []): array
    {
        try {
            $rows = [];

            foreach (($this->activeTransaction ?? $this->neo4j)->run($cypher, $params) as $row) {
                $rows[] = $this->toPlainValue($row);
            }

            $this->lastError = null;

            return $rows;
        } catch (Throwable $exception) {
            $mapped = Neo4jQueryException::fromDriver($exception);
            $this->lastError = $mapped->getMessage();

            throw $mapped;
        }
    }

    public function client(): ClientInterface
    {
        return $this->neo4j;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->activeTransaction !== null) {
            return $callback();
        }

        try {
            return $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($callback): mixed {
                $this->activeTransaction = $transaction;

                try {
                    return $callback();
                } finally {
                    $this->activeTransaction = null;
                }
            });
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $mapped = Neo4jQueryException::fromDriver($exception);
            $this->lastError = $mapped->getMessage();

            throw $mapped;
        }
    }

    public function ping(): bool
    {
        try {
            return ($this->run('RETURN 1 AS ok')[0]['ok'] ?? null) === 1;
        } catch (Neo4jQueryException) {
            return false;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function toPlainValue(mixed $value): mixed
    {
        if (is_array($value) || $value instanceof Traversable) {
            $plain = [];

            foreach ($value as $key => $item) {
                $plain[$key] = $this->toPlainValue($item);
            }

            return $plain;
        }

        if ($value instanceof JsonSerializable) {
            return $this->toPlainValue($value->jsonSerialize());
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new UnexpectedValueException('The query must return property maps, lists, or scalars.');
    }
}
