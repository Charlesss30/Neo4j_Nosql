<?php

namespace App\Services;

use InvalidArgumentException;

class CypherStatements
{
    /** @return list<string> */
    public function splitStatements(string $source): array
    {
        $masked = $this->mask($source);
        $statements = [];
        $start = 0;

        for ($index = 0, $length = strlen($masked); $index <= $length; $index++) {
            if ($index === $length || $masked[$index] === ';') {
                if (trim(substr($masked, $start, $index - $start)) !== '') {
                    $statements[] = trim(substr($source, $start, $index - $start));
                }

                $start = $index + 1;
            }
        }

        return $statements;
    }

    private function mask(string $source): string
    {
        $masked = '';
        $quote = null;
        $lineComment = false;
        $blockDepth = 0;

        for ($index = 0, $length = strlen($source); $index < $length; $index++) {
            $char = $source[$index];
            $pair = substr($source, $index, 2);

            if ($lineComment) {
                $lineComment = $char !== "\n" && $char !== "\r";
                $masked .= $lineComment ? ' ' : $char;
            } elseif ($blockDepth > 0) {
                if ($pair === '/*' || $pair === '*/') {
                    $blockDepth += $pair === '/*' ? 1 : -1;
                    $masked .= '  ';
                    $index++;
                } else {
                    $masked .= ' ';
                }
            } elseif ($quote !== null) {
                if ($char === '\\' && $index + 1 < $length) {
                    $masked .= '  ';
                    $index++;
                } elseif ($char === $quote && $index + 1 < $length && $source[$index + 1] === $quote) {
                    $masked .= '  ';
                    $index++;
                } else {
                    $masked .= ' ';

                    if ($char === $quote) {
                        $quote = null;
                    }
                }
            } elseif ($pair === '//' || $pair === '/*') {
                $lineComment = $pair === '//';
                $blockDepth = $pair === '/*' ? 1 : 0;
                $masked .= '  ';
                $index++;
            } elseif (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $masked .= '_';
            } else {
                $masked .= $char;
            }
        }

        if ($quote !== null || $blockDepth > 0) {
            throw new InvalidArgumentException('Chuỗi, tên trong dấu nháy hoặc chú thích chưa được đóng.');
        }

        return $masked;
    }
}
