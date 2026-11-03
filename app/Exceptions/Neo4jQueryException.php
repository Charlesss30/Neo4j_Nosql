<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class Neo4jQueryException extends RuntimeException
{
    public static function fromDriver(Throwable $exception): self
    {
        $details = '';

        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            $details .= ' '.strtolower($cause::class.' '.$cause->getMessage());
        }

        $message = match (true) {
            str_contains($details, 'unauthorized'),
            str_contains($details, 'authentication') => 'Xác thực Neo4j thất bại. Hãy kiểm tra NEO4J_USER và NEO4J_PASSWORD trong .env.',
            str_contains($details, 'credentials_expired'),
            str_contains($details, 'credentialsexpired') => 'Mật khẩu Neo4j cần được đổi. Hãy đổi mật khẩu trong Neo4j Browser rồi cập nhật .env.',
            str_contains($details, 'connection'),
            str_contains($details, 'connect'),
            str_contains($details, 'socket'),
            str_contains($details, 'timed out'),
            str_contains($details, 'unavailable') => 'Không thể kết nối Neo4j. Hãy khởi động Neo4j và kiểm tra NEO4J_URI cùng cổng 7687.',
            default => 'Không thể thực hiện truy vấn Neo4j. Hãy kiểm tra cơ sở dữ liệu và danh mục truy vấn của Member A.',
        };

        return new self($message, 0, $exception);
    }
}
