<?php

namespace App\Services;

use App\Exceptions\Neo4jQueryException;
use JsonSerializable;
use Laudis\Neo4j\Contracts\ClientInterface;
use Throwable;
use Traversable;
use UnexpectedValueException;

class Neo4jService
{
    private ?string $lastError = null;

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

            foreach ($this->neo4j->run($cypher, $params) as $row) {
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
