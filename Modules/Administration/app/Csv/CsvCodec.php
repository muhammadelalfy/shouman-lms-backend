<?php

declare(strict_types=1);

namespace Modules\Administration\Csv;

use Illuminate\Validation\ValidationException;

final class CsvCodec
{
    public const int MAX_BYTES = 1048576;

    public const int MAX_ROWS = 200;

    /** @param list<string> $headers
     * @return list<array<string, string>>
     */
    public function decode(string $content, array $headers): array
    {
        abort_if(strlen($content) > self::MAX_BYTES, 413, 'الحد الأقصى للملف 1 ميجابايت.');
        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            $this->invalid('الملف يجب أن يكون UTF-8 وبحجم لا يتجاوز 1 ميجابايت.');
        }
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $this->validateQuotes($content);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read CSV.');
        }
        try {
            fwrite($stream, $content);
            rewind($stream);
            $actual = fgetcsv($stream, escape: '');
            if ($actual === false || in_array(null, $actual, true)) {
                $this->invalid('أعمدة الملف لا تطابق النموذج.');
            }
            $expected = $headers;
            sort($expected);
            $sorted = $actual;
            sort($sorted);
            if ($sorted !== $expected) {
                $this->invalid('أعمدة الملف لا تطابق النموذج.');
            }
            $rows = [];
            while (($cells = fgetcsv($stream, escape: '')) !== false) {
                $number = count($rows) + 2;
                if (count($cells) !== count($headers) || in_array(null, $cells, true)) {
                    throw ValidationException::withMessages(['rows.'.$number => 'عدد الأعمدة غير صحيح أو يوجد صف فارغ.']);
                }
                if (count($rows) >= self::MAX_ROWS) {
                    $this->invalid('الحد الأقصى للاستيراد 200 صف.');
                }
                $rows[] = array_combine($actual, array_map($this->unescape(...), $cells));
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    public function escape(string $value): string
    {
        return str_starts_with($value, "'") || $this->dangerous($value) ? "'".$value : $value;
    }

    private function unescape(string $value): string
    {
        if (str_starts_with($value, "'")) {
            $rest = substr($value, 1);
            if (str_starts_with($rest, "'") || $this->dangerous($rest)) {
                return $rest;
            }
        }

        return $value;
    }

    private function dangerous(string $value): bool
    {
        return preg_match('/\A[\s\x00-\x20]*[=+\-@\t\r\n]/u', $value) === 1;
    }

    private function validateQuotes(string $content): void
    {
        $state = 'start';
        $length = strlen($content);
        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            if ($state === 'quoted') {
                if ($char === '"') {
                    $state = 'closed';
                }

                continue;
            }
            if ($state === 'closed' && $char === '"') {
                $state = 'quoted';

                continue;
            }
            if ($char === ',' || $char === "\n" || $char === "\r") {
                if ($char === "\r" && ($content[$i + 1] ?? '') !== "\n") {
                    $this->invalid('استخدم أسطر LF أو CRLF.');
                }
                $state = 'start';

                continue;
            }
            if ($state === 'closed' || ($state === 'text' && $char === '"')) {
                $this->invalid('تنسيق علامات الاقتباس في CSV غير صالح.');
            }
            $state = $char === '"' && $state === 'start' ? 'quoted' : 'text';
        }
        if ($state === 'quoted') {
            $this->invalid('يوجد حقل بعلامات اقتباس غير مغلقة.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
