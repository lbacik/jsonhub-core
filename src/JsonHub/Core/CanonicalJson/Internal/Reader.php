<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson\Internal;

use JsonHub\Core\CanonicalJson\CanonicalJsonException;
use JsonHub\Core\CanonicalJson\Violation;

/**
 * Iterative strict/legacy JSON reader producing canonical bytes.
 *
 * Uses an explicit stack instead of PHP recursion, so arbitrarily deep
 * input never exhausts the call stack, and imposes no depth limit.
 * Never uses json_decode. Malformed input (invalid_json) aborts the read
 * immediately; every other violation is collected (capped at 100) and
 * reported together.
 *
 * @internal
 */
final class Reader
{
    private const MAX_VIOLATIONS = 100;

    private const STATE_OBJECT_KEY = 0;
    private const STATE_OBJECT_AFTER_VALUE = 1;
    private const STATE_ARRAY_ITEM = 2;
    private const STATE_ARRAY_AFTER_VALUE = 3;

    private string $input = '';
    private int $length = 0;
    private int $position = 0;
    private bool $strict;

    /** @var list<string> */
    private array $chunks = [];

    /** @var list<array<string, mixed>> */
    private array $frames = [];

    /** @var list<string> */
    private array $path = [];

    /** @var list<Violation> */
    private array $violations = [];

    private bool $legacyUsed = false;
    private bool $objectRoot = false;

    public function __construct(bool $strict)
    {
        $this->strict = $strict;
    }

    /**
     * @return array{string, bool, bool} [canonical bytes, legacyUsed, objectRoot]
     */
    public function read(string $json): array
    {
        $this->input = $json;
        $this->length = strlen($json);
        $this->position = 0;

        $this->skipWhitespace();
        if ($this->position >= $this->length) {
            $this->failInvalid('');
        }
        $this->parseRootValue();
        $this->skipWhitespace();
        if ($this->position !== $this->length) {
            $this->failInvalid('');
        }
        if ($this->violations !== []) {
            throw CanonicalJsonException::fromViolations($this->violations);
        }

        return [implode('', $this->chunks), $this->legacyUsed, $this->objectRoot];
    }

    private function parseRootValue(): void
    {
        $byte = $this->input[$this->position];
        if ($byte === '{') {
            $this->objectRoot = true;
            $this->openFrame('object', true);
        } elseif ($byte === '[') {
            $this->openFrame('array', true);
        } else {
            $this->parseScalar();
        }
        $this->drive();
    }

    /**
     * Flat driver loop: processes the top frame until every container is closed.
     */
    private function drive(): void
    {
        while ($this->frames !== []) {
            $top = count($this->frames) - 1;
            $frame = $this->frames[$top];
            if ($frame['kind'] === 'object') {
                if ($frame['state'] === self::STATE_OBJECT_KEY) {
                    $this->parseMemberKey($top);
                } else {
                    $this->finishMemberValue($top);
                }
            } elseif ($frame['state'] === self::STATE_ARRAY_ITEM) {
                $this->parseElement($top);
            } else {
                $this->finishElementValue($top);
            }
        }
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function openFrame(string $kind, bool $isRoot): void
    {
        $this->position++;
        $this->frames[] = [
            'kind' => $kind,
            'chunkStart' => count($this->chunks),
            'state' => $kind === 'object' ? self::STATE_OBJECT_KEY : self::STATE_ARRAY_ITEM,
            'afterComma' => false,
            'isRoot' => $isRoot,
            /** @var list<array{string, int, int, int}> */
            'members' => [],
            /** @var array<string, bool>|null */
            'seen' => $kind === 'object' ? [] : null,
            'index' => 0,
            'memberStart' => 0,
            'memberKey' => '',
        ];
    }

    private function parseMemberKey(int $top): void
    {
        $this->skipWhitespace();
        $byte = $this->peek();
        if ($byte === '}') {
            if ($this->frames[$top]['afterComma']) {
                $this->failInvalid($this->pointer());
            }
            $this->position++;
            $this->closeObject($top);
            return;
        }
        if ($byte !== '"') {
            $this->failInvalid($this->pointer());
        }

        $key = $this->parseString($this->pointer());
        $this->skipWhitespace();
        if ($this->peek() !== ':') {
            $this->failInvalid($this->pointer());
        }
        $this->position++;
        $this->skipWhitespace();

        $segment = self::escapePointerSegment($key['text']);
        $seen = $this->frames[$top]['seen'];
        /** @var array<string, bool> $seen */
        if (isset($seen[$key['sort']])) {
            if ($this->strict) {
                $this->addViolation(
                    CanonicalJsonException::DUPLICATE_MEMBER,
                    $this->pointer() . '/' . $segment
                );
            } else {
                $this->legacyUsed = true;
            }
        } else {
            $seen[$key['sort']] = true;
            $this->frames[$top]['seen'] = $seen;
        }

        $this->path[] = $segment;
        $this->frames[$top]['memberStart'] = count($this->chunks);
        $this->frames[$top]['memberKey'] = $key['sort'];
        $this->chunks[] = $key['out'] . ':';

        $byte = $this->peek();
        if ($byte === '{' || $byte === '[') {
            $this->frames[$top]['state'] = self::STATE_OBJECT_AFTER_VALUE;
            $this->openFrame($byte === '{' ? 'object' : 'array', false);
        } else {
            $this->parseScalar();
            $this->recordMember($top);
            $this->afterMember($top);
        }
    }

    private function finishMemberValue(int $top): void
    {
        $this->recordMember($top);
        $this->afterMember($top);
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function recordMember(int $top): void
    {
        $frame = $this->frames[$top];
        $frame['members'][] = [
            $frame['memberKey'],
            count($frame['members']),
            $frame['memberStart'],
            count($this->chunks),
        ];
        $this->frames[$top]['members'] = $frame['members'];
        array_pop($this->path);
    }

    private function afterMember(int $top): void
    {
        $this->skipWhitespace();
        $byte = $this->peek();
        if ($byte === ',') {
            $this->position++;
            $this->frames[$top]['state'] = self::STATE_OBJECT_KEY;
            $this->frames[$top]['afterComma'] = true;
            return;
        }
        if ($byte === '}') {
            $this->position++;
            $this->closeObject($top);
            return;
        }
        $this->failInvalid($this->pointer());
    }

    private function parseElement(int $top): void
    {
        $this->skipWhitespace();
        $byte = $this->peek();
        if ($byte === ']') {
            if ($this->frames[$top]['afterComma']) {
                $this->failInvalid($this->pointer());
            }
            $this->position++;
            $this->closeArray($top);
            return;
        }

        if ($this->frames[$top]['index'] > 0) {
            $this->chunks[] = ',';
        }
        $this->path[] = (string) $this->frames[$top]['index'];

        if ($byte === '{' || $byte === '[') {
            $this->frames[$top]['state'] = self::STATE_ARRAY_AFTER_VALUE;
            $this->openFrame($byte === '{' ? 'object' : 'array', false);
        } else {
            $this->parseScalar();
            $this->finishElement($top);
            $this->afterElement($top);
        }
    }

    private function finishElementValue(int $top): void
    {
        $this->finishElement($top);
        $this->afterElement($top);
    }

    private function finishElement(int $top): void
    {
        $this->frames[$top]['index']++;
        array_pop($this->path);
    }

    private function afterElement(int $top): void
    {
        $this->skipWhitespace();
        $byte = $this->peek();
        if ($byte === ',') {
            $this->position++;
            $this->frames[$top]['state'] = self::STATE_ARRAY_ITEM;
            $this->frames[$top]['afterComma'] = true;
            return;
        }
        if ($byte === ']') {
            $this->position++;
            $this->closeArray($top);
            return;
        }
        $this->failInvalid($this->pointer());
    }

    private function closeObject(int $top): void
    {
        $frame = $this->frames[$top];
        $members = $frame['members'];
        usort($members, static function (array $first, array $second): int {
            $order = strcmp($first[0], $second[0]);
            if ($order !== 0) {
                return $order;
            }

            return $first[1] <=> $second[1];
        });

        $text = '{';
        $first = true;
        foreach ($members as $member) {
            if (!$first) {
                $text .= ',';
            }
            $first = false;
            $text .= implode('', array_slice($this->chunks, $member[2], $member[3] - $member[2]));
        }
        $text .= '}';

        $this->popChunks($frame['chunkStart']);
        $this->chunks[] = $text;
        array_pop($this->frames);
    }

    private function closeArray(int $top): void
    {
        $frame = $this->frames[$top];
        $start = $frame['chunkStart'];
        if ($frame['isRoot'] === true && $this->strict === false && $frame['index'] === 0) {
            $this->popChunks($start);
            $this->chunks[] = '{}';
            $this->objectRoot = true;
            array_pop($this->frames);

            return;
        }

        $text = '[' . implode('', array_slice($this->chunks, $start)) . ']';
        $this->popChunks($start);
        $this->chunks[] = $text;
        array_pop($this->frames);
    }

    private function popChunks(int $keep): void
    {
        while (count($this->chunks) > $keep) {
            array_pop($this->chunks);
        }
    }

    private function parseScalar(): void
    {
        $byte = $this->peek();
        if ($byte === '"') {
            $string = $this->parseString($this->pointer());
            $this->chunks[] = $string['out'];
            return;
        }
        if ($byte === 't' || $byte === 'f' || $byte === 'n') {
            $this->parseLiteral();
            return;
        }
        if (($byte >= '0' && $byte <= '9') || $byte === '-') {
            $this->parseNumber();
            return;
        }
        $this->failInvalid($this->pointer());
    }

    private function parseLiteral(): void
    {
        foreach (['true', 'false', 'null'] as $word) {
            $length = strlen($word);
            if (substr($this->input, $this->position, $length) === $word) {
                $this->position += $length;
                $this->chunks[] = $word;

                return;
            }
        }
        $this->failInvalid($this->pointer());
    }

    private function parseNumber(): void
    {
        $start = $this->position;
        $pointer = $this->pointer();
        if ($this->input[$this->position] === '-') {
            $this->position++;
            if ($this->position >= $this->length) {
                $this->failInvalid($pointer);
            }
        }
        if ($this->position >= $this->length) {
            $this->failInvalid($pointer);
        }
        $byte = $this->input[$this->position];
        if ($byte < '0' || $byte > '9') {
            $this->failInvalid($pointer);
        }
        if ($byte === '0') {
            $this->position++;
            if (
                $this->position < $this->length
                && $this->input[$this->position] >= '0'
                && $this->input[$this->position] <= '9'
            ) {
                $this->failInvalid($pointer);
            }
        } else {
            while (
                $this->position < $this->length
                && $this->input[$this->position] >= '0'
                && $this->input[$this->position] <= '9'
            ) {
                $this->position++;
            }
        }
        if ($this->position < $this->length && $this->input[$this->position] === '.') {
            $this->position++;
            $fractionStart = $this->position;
            while (
                $this->position < $this->length
                && $this->input[$this->position] >= '0'
                && $this->input[$this->position] <= '9'
            ) {
                $this->position++;
            }
            if ($this->position === $fractionStart) {
                $this->failInvalid($pointer);
            }
        }
        if (
            $this->position < $this->length
            && ($this->input[$this->position] === 'e' || $this->input[$this->position] === 'E')
        ) {
            $this->position++;
            if (
                $this->position < $this->length
                && ($this->input[$this->position] === '+' || $this->input[$this->position] === '-')
            ) {
                $this->position++;
            }
            $exponentStart = $this->position;
            while (
                $this->position < $this->length
                && $this->input[$this->position] >= '0'
                && $this->input[$this->position] <= '9'
            ) {
                $this->position++;
            }
            if ($this->position === $exponentStart) {
                $this->failInvalid($pointer);
            }
        }

        $literal = substr($this->input, $start, $this->position - $start);
        [$status, , $canonical] = Decimal::classify($literal);
        if ($status === Decimal::OK) {
            $this->chunks[] = $canonical;
            return;
        }

        if ($this->strict) {
            $this->addViolation($status, $pointer);
            $this->chunks[] = '0';
            return;
        }

        $this->legacyUsed = true;
        $this->chunks[] = Decimal::normalizeLiteral($literal);
    }

    /**
     * Parse a JSON string at the current position (opening quote).
     *
     * @return array{out: string, text: string, sort: string}
     */
    private function parseString(string $pointer): array
    {
        $this->position++;
        $out = '"';
        $text = '';
        $sort = '';
        while (true) {
            if ($this->position >= $this->length) {
                $this->failInvalid($pointer);
            }
            $byte = $this->input[$this->position];
            if ($byte === '"') {
                $this->position++;
                $out .= '"';
                break;
            }
            if ($byte === '\\') {
                $this->position++;
                if ($this->position >= $this->length) {
                    $this->failInvalid($pointer);
                }
                $escaped = $this->input[$this->position];
                $code = $this->simpleEscape($escaped);
                if ($code !== null) {
                    $this->position++;
                    $this->emitCodePoint($code, false, $pointer, $out, $text, $sort);
                    continue;
                }
                if ($escaped !== 'u') {
                    $this->failInvalid($pointer);
                }
                $this->position++;
                $value = $this->parseHex4($pointer);
                if ($value >= 0xD800 && $value <= 0xDBFF) {
                    if (
                        $this->position + 5 < $this->length
                        && $this->input[$this->position] === '\\'
                        && $this->input[$this->position + 1] === 'u'
                    ) {
                        $save = $this->position;
                        $this->position += 2;
                        $low = $this->parseHex4($pointer);
                        if ($low >= 0xDC00 && $low <= 0xDFFF) {
                            $this->emitCodePoint(
                                0x10000 + (($value - 0xD800) << 10) + ($low - 0xDC00),
                                false,
                                $pointer,
                                $out,
                                $text,
                                $sort
                            );
                            continue;
                        }
                        $this->position = $save;
                    }
                    $this->emitCodePoint($value, true, $pointer, $out, $text, $sort);
                    continue;
                }
                if ($value >= 0xDC00 && $value <= 0xDFFF) {
                    $this->emitCodePoint($value, true, $pointer, $out, $text, $sort);
                    continue;
                }
                $this->emitCodePoint($value, false, $pointer, $out, $text, $sort);
                continue;
            }
            $ordinal = ord($byte);
            if ($ordinal < 0x20) {
                $this->failInvalid($pointer);
            }
            if ($ordinal < 0x80) {
                $this->position++;
                $this->emitCodePoint($ordinal, false, $pointer, $out, $text, $sort, $byte);
                continue;
            }
            [$code, $bytes] = $this->parseUtf8Sequence($pointer);
            $this->position += $bytes;
            $raw = substr($this->input, $this->position - $bytes, $bytes);
            $this->emitCodePoint($code, false, $pointer, $out, $text, $sort, $raw);
        }

        return ['out' => $out, 'text' => $text, 'sort' => $sort];
    }

    /**
     * @param string $out
     * @param string $text
     * @param string $sort
     */
    private function emitCodePoint(
        int $code,
        bool $loneSurrogate,
        string $pointer,
        string &$out,
        string &$text,
        string &$sort,
        ?string $raw = null
    ): void {
        if ($loneSurrogate) {
            if ($this->strict) {
                $this->addViolation(CanonicalJsonException::INVALID_STRING, $pointer);
                $out .= '?';
                $text .= "\xEF\xBF\xBD";
                $sort .= pack('n', $code);
                return;
            }
            $this->legacyUsed = true;
            $out .= sprintf('\\ud%03x', $code & 0xFFF);
            $text .= "\xEF\xBF\xBD";
            $sort .= pack('n', $code);
            return;
        }

        if ($code < 0x10000) {
            $sort .= pack('n', $code);
        } else {
            $shifted = $code - 0x10000;
            $sort .= pack('n', 0xD800 + ($shifted >> 10)) . pack('n', 0xDC00 + ($shifted & 0x3FF));
        }

        if ($raw !== null && $code >= 0x20 && $code !== 0x22 && $code !== 0x5C) {
            $out .= $raw;
            $text .= $raw;
            return;
        }
        $text .= self::encodeUtf8($code);
        $out .= self::escapeCodePoint($code);
    }

    private function simpleEscape(string $byte): ?int
    {
        return match ($byte) {
            '"' => 0x22,
            '\\' => 0x5C,
            '/' => 0x2F,
            'b' => 0x08,
            'f' => 0x0C,
            'n' => 0x0A,
            'r' => 0x0D,
            't' => 0x09,
            default => null,
        };
    }

    private function parseHex4(string $pointer): int
    {
        if ($this->position + 4 > $this->length) {
            $this->failInvalid($pointer);
        }
        $value = 0;
        for ($i = 0; $i < 4; $i++) {
            $digit = $this->input[$this->position + $i];
            $value *= 16;
            if ($digit >= '0' && $digit <= '9') {
                $value += ord($digit) - 48;
            } elseif ($digit >= 'a' && $digit <= 'f') {
                $value += ord($digit) - 87;
            } elseif ($digit >= 'A' && $digit <= 'F') {
                $value += ord($digit) - 55;
            } else {
                $this->failInvalid($pointer);
            }
        }
        $this->position += 4;

        return $value;
    }

    /**
     * Validate and decode one UTF-8 sequence at the current position.
     *
     * @return array{int, int} [code point, byte length]
     */
    private function parseUtf8Sequence(string $pointer): array
    {
        $first = ord($this->input[$this->position]);
        $second = $this->position + 1 < $this->length ? ord($this->input[$this->position + 1]) : -1;
        if ($first >= 0xC2 && $first <= 0xDF) {
            if (($second & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [(($first & 0x1F) << 6) | ($second & 0x3F), 2];
        }
        $third = $this->position + 2 < $this->length ? ord($this->input[$this->position + 2]) : -1;
        if ($first === 0xE0) {
            if ($second < 0xA0 || $second > 0xBF || ($third & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [(($first & 0x0F) << 12) | (($second & 0x3F) << 6) | ($third & 0x3F), 3];
        }
        if ($first >= 0xE1 && $first <= 0xEC) {
            if (($second & 0xC0) !== 0x80 || ($third & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [(($first & 0x0F) << 12) | (($second & 0x3F) << 6) | ($third & 0x3F), 3];
        }
        if ($first === 0xED) {
            if ($second < 0x80 || $second > 0x9F || ($third & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [(($first & 0x0F) << 12) | (($second & 0x3F) << 6) | ($third & 0x3F), 3];
        }
        if ($first === 0xEE || $first === 0xEF) {
            if (($second & 0xC0) !== 0x80 || ($third & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [(($first & 0x0F) << 12) | (($second & 0x3F) << 6) | ($third & 0x3F), 3];
        }
        $fourth = $this->position + 3 < $this->length ? ord($this->input[$this->position + 3]) : -1;
        if ($first === 0xF0) {
            if ($second < 0x90 || $second > 0xBF || ($third & 0xC0) !== 0x80 || ($fourth & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [
                (($first & 0x07) << 18) | (($second & 0x3F) << 12) | (($third & 0x3F) << 6) | ($fourth & 0x3F),
                4,
            ];
        }
        if ($first >= 0xF1 && $first <= 0xF3) {
            if (($second & 0xC0) !== 0x80 || ($third & 0xC0) !== 0x80 || ($fourth & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [
                (($first & 0x07) << 18) | (($second & 0x3F) << 12) | (($third & 0x3F) << 6) | ($fourth & 0x3F),
                4,
            ];
        }
        if ($first === 0xF4) {
            if ($second < 0x80 || $second > 0x8F || ($third & 0xC0) !== 0x80 || ($fourth & 0xC0) !== 0x80) {
                $this->failInvalid($pointer);
            }

            return [
                (($first & 0x07) << 18) | (($second & 0x3F) << 12) | (($third & 0x3F) << 6) | ($fourth & 0x3F),
                4,
            ];
        }
        $this->failInvalid($pointer);
    }

    private static function encodeUtf8(int $code): string
    {
        if ($code < 0x80) {
            return chr($code);
        }
        if ($code < 0x800) {
            return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
        }
        if ($code < 0x10000) {
            return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
        }

        return chr(0xF0 | ($code >> 18))
            . chr(0x80 | (($code >> 12) & 0x3F))
            . chr(0x80 | (($code >> 6) & 0x3F))
            . chr(0x80 | ($code & 0x3F));
    }

    private static function escapeCodePoint(int $code): string
    {
        if ($code === 0x22) {
            return '\\"';
        }
        if ($code === 0x5C) {
            return '\\\\';
        }
        if ($code === 0x08) {
            return '\\b';
        }
        if ($code === 0x09) {
            return '\\t';
        }
        if ($code === 0x0A) {
            return '\\n';
        }
        if ($code === 0x0C) {
            return '\\f';
        }
        if ($code === 0x0D) {
            return '\\r';
        }
        if ($code < 0x20) {
            return sprintf('\\u%04x', $code);
        }

        return self::encodeUtf8($code);
    }

    private static function escapePointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    private function skipWhitespace(): void
    {
        while ($this->position < $this->length) {
            $byte = $this->input[$this->position];
            if ($byte !== ' ' && $byte !== "\t" && $byte !== "\n" && $byte !== "\r") {
                break;
            }
            $this->position++;
        }
    }

    private function peek(): string
    {
        if ($this->position >= $this->length) {
            $this->failInvalid($this->pointer());
        }

        return $this->input[$this->position];
    }

    private function pointer(): string
    {
        if ($this->path === []) {
            return '';
        }

        return '/' . implode('/', $this->path);
    }

    private function addViolation(string $code, string $pointer): void
    {
        $this->violations[] = new Violation($code, $pointer);
        if (count($this->violations) >= self::MAX_VIOLATIONS) {
            throw CanonicalJsonException::fromViolations($this->violations);
        }
    }

    private function failInvalid(string $enclosingPointer): never
    {
        throw CanonicalJsonException::single(
            CanonicalJsonException::INVALID_JSON,
            $enclosingPointer . '#' . $this->position
        );
    }
}
