<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Modules\Administration\Csv\CsvCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CsvCodecTest extends TestCase
{
    public function test_arabic_quotes_newlines_and_spreadsheet_prefixes_round_trip(): void
    {
        $codec = new CsvCodec;
        $values = ['مرحبا، بالعالم', "سطر\nسطر آخر", 'quoted "answer"', '=HYPERLINK("bad")', " \t@evil", "'literal", "''literal", "'=literal", 'ordinary'];
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['value'], escape: '', eol: "\r\n");
        foreach ($values as $value) {
            fputcsv($stream, [$codec->escape($value)], escape: '', eol: "\r\n");
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        self::assertSame(array_map(fn (string $value): array => ['value' => $value], $values), $codec->decode($content, ['value']));
        self::assertSame("'=1", $codec->escape('=1'));
        self::assertSame("''literal", $codec->escape("'literal"));
    }

    #[DataProvider('malformed')]
    public function test_malformed_or_unbounded_inputs_are_rejected(string $content): void
    {
        $this->expectException(ValidationException::class);
        (new CsvCodec)->decode($content, ['value']);
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'unknown header' => ["other\nvalue\n"];
        yield 'duplicate header' => ["value,value\nx,y\n"];
        yield 'unterminated quote' => ["value\n\"never closed"];
        yield 'quote in unquoted text' => ["value\nabc\"def\n"];
        yield 'text after closing quote' => ["value\n\"abc\"x\n"];
        yield 'invalid utf8' => ["value\n\xFF\n"];
        yield 'null byte' => ["value\nx\0y\n"];
        yield 'too many fields' => ["value\nx,y\n"];
        yield 'blank row' => ["value\n\n"];
        yield 'row limit' => ["value\n".str_repeat("x\n", 201)];
    }

    public function test_header_only_is_noop_and_columns_may_be_reordered(): void
    {
        $codec = new CsvCodec;
        self::assertSame([], $codec->decode("value\n", ['value']));
        self::assertSame([['b' => '2', 'a' => '1']], $codec->decode("b,a\n2,1\n", ['a', 'b']));
    }

    public function test_oversize_is_rejected_before_parsing(): void
    {
        $this->expectException(HttpException::class);
        (new CsvCodec)->decode(str_repeat('x', 1048577), ['value']);
    }
}
