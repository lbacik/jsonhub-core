<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson\Internal;

/**
 * Exact decimal arithmetic for JSON numbers, without extensions.
 *
 * A decimal value is a [negative, digits, exponent] triple with the value
 * sign * 0.digits * 10^exponent... represented here as digits * 10^exponent
 * with digits free of leading and trailing zeros ("0" for zero). Literals
 * are never expanded: classification and comparison only use digit counts
 * and lexicographic order, so adversarial inputs (e.g. "1e1000000") stay
 * cheap. Only the exact binary expansion of a double is materialized, and
 * that is bounded (~800 digits).
 *
 * @internal
 */
final class Decimal
{
    private const MAX_SAFE_INTEGER = '9007199254740991';

    public const OK = 'ok';
    public const UNSAFE_INTEGER = 'unsafe_integer';
    public const NUMBER_OVERFLOW = 'number_overflow';
    public const PRECISION_LOSS = 'precision_loss';

    /**
     * Parse an already grammar-validated JSON number literal into an exact
     * decimal triple: [negative, digits, exponent], value = ±digits * 10^exponent.
     *
     * @return array{bool, string, int}
     */
    public static function fromLiteral(string $literal): array
    {
        $position = 0;
        $negative = false;
        if ($literal[0] === '-') {
            $negative = true;
            $position = 1;
        }

        $integer = '';
        $length = strlen($literal);
        while ($position < $length && $literal[$position] >= '0' && $literal[$position] <= '9') {
            $integer .= $literal[$position];
            $position++;
        }

        $fraction = '';
        if ($position < $length && $literal[$position] === '.') {
            $position++;
            while ($position < $length && $literal[$position] >= '0' && $literal[$position] <= '9') {
                $fraction .= $literal[$position];
                $position++;
            }
        }

        $exponent = 0;
        if ($position < $length && ($literal[$position] === 'e' || $literal[$position] === 'E')) {
            $position++;
            $exponentNegative = false;
            if ($position < $length && ($literal[$position] === '+' || $literal[$position] === '-')) {
                $exponentNegative = $literal[$position] === '-';
                $position++;
            }
            $value = 0;
            while ($position < $length && $literal[$position] >= '0' && $literal[$position] <= '9') {
                $value = $value * 10 + (ord($literal[$position]) - 48);
                if ($value > 1000000) {
                    $value = 1000000;
                    break;
                }
                $position++;
            }
            $exponent = $exponentNegative ? -$value : $value;
        }

        $exponent -= strlen($fraction);
        $digits = ltrim($integer . $fraction, '0');
        if ($digits === '') {
            return [false, '0', 0];
        }
        $stripped = strlen($digits);
        $digits = rtrim($digits, '0');
        $exponent += $stripped - strlen($digits);

        return [$negative, $digits, $exponent];
    }

    /**
     * Compare two exact decimals. Each is [negative, digits, exponent]
     * as returned by fromLiteral(). Returns -1, 0 or 1.
     *
     * @param array{bool, string, int} $first
     * @param array{bool, string, int} $second
     */
    public static function compare(array $first, array $second): int
    {
        [$firstNegative, $firstDigits, $firstExponent] = $first;
        [$secondNegative, $secondDigits, $secondExponent] = $second;

        if ($firstDigits === '0' && $secondDigits === '0') {
            return 0;
        }
        if ($firstDigits === '0') {
            return $secondNegative ? 1 : -1;
        }
        if ($secondDigits === '0') {
            return $firstNegative ? -1 : 1;
        }
        if ($firstNegative !== $secondNegative) {
            return $firstNegative ? -1 : 1;
        }

        $sign = $firstNegative ? -1 : 1;
        $firstOrder = strlen($firstDigits) + $firstExponent;
        $secondOrder = strlen($secondDigits) + $secondExponent;
        if ($firstOrder !== $secondOrder) {
            return ($firstOrder > $secondOrder ? 1 : -1) * $sign;
        }

        $length = max(strlen($firstDigits), strlen($secondDigits));
        $paddedFirst = str_pad($firstDigits, $length, '0');
        $paddedSecond = str_pad($secondDigits, $length, '0');
        $result = strcmp($paddedFirst, $paddedSecond);
        if ($result === 0) {
            return 0;
        }

        return ($result > 0 ? 1 : -1) * $sign;
    }

    /**
     * Whether an exact integer decimal has |value| > 2^53 - 1.
     * Digits must be free of leading/trailing zeros and nonzero.
     */
    public static function isUnsafeInteger(string $digits, int $exponent): bool
    {
        if ($exponent < 0) {
            return false;
        }
        $integerLength = strlen($digits) + $exponent;
        if ($integerLength > 16) {
            return true;
        }
        if ($integerLength < 16) {
            return false;
        }

        return strcmp($digits . str_repeat('0', $exponent), self::MAX_SAFE_INTEGER) > 0;
    }

    /**
     * Classify a grammar-validated JSON number literal for Strict Reading.
     *
     * @return array{string, float, string} [status, double value, canonical text if status is ok]
     */
    public static function classify(string $literal): array
    {
        [, $digits, $exponent] = self::fromLiteral($literal);
        $double = (float) $literal;

        if (is_infinite($double)) {
            return [self::NUMBER_OVERFLOW, $double, '0'];
        }
        if ($digits !== '0' && self::isUnsafeInteger($digits, $exponent)) {
            return [self::UNSAFE_INTEGER, $double, '0'];
        }
        if ($double == 0.0) {
            if ($digits === '0') {
                return [self::OK, 0.0, '0'];
            }

            return [self::PRECISION_LOSS, $double, '0'];
        }

        [$shortestDigits, $shortestExponent] = self::shortest($double < 0.0 ? -$double : $double);
        $roundTrip = [false, $shortestDigits, $shortestExponent - strlen($shortestDigits)];
        if (self::compare([false, $digits, $exponent], $roundTrip) !== 0) {
            return [self::PRECISION_LOSS, $double, '0'];
        }

        return [self::OK, $double, self::esToString($double)];
    }

    /**
     * Normalize a legacy (strict-rejected) number to its exact decimal
     * spelling: no leading or trailing zeros, lowercase "e", no "+" in the
     * exponent, no dangling point, no zero exponent.
     */
    public static function normalizeLiteral(string $literal): string
    {
        $position = 0;
        $sign = '';
        if ($literal[0] === '-') {
            $sign = '-';
            $position = 1;
        }

        $length = strlen($literal);
        $integer = '';
        while ($position < $length && $literal[$position] >= '0' && $literal[$position] <= '9') {
            $integer .= $literal[$position];
            $position++;
        }

        $fraction = null;
        if ($position < $length && $literal[$position] === '.') {
            $position++;
            $fraction = '';
            while ($position < $length && $literal[$position] >= '0' && $literal[$position] <= '9') {
                $fraction .= $literal[$position];
                $position++;
            }
        }

        $exponent = null;
        if ($position < $length && ($literal[$position] === 'e' || $literal[$position] === 'E')) {
            $position++;
            $exponentNegative = false;
            if ($position < $length && ($literal[$position] === '+' || $literal[$position] === '-')) {
                $exponentNegative = $literal[$position] === '-';
                $position++;
            }
            $exponentDigits = '';
            while ($position < $length && $literal[$position] >= '0' && $literal[$position] <= '9') {
                $exponentDigits .= $literal[$position];
                $position++;
            }
            $exponentDigits = ltrim($exponentDigits, '0');
            if ($exponentDigits === '') {
                $exponentDigits = '0';
            }
            if ($exponentDigits !== '0') {
                $exponent = ($exponentNegative ? '-' : '') . $exponentDigits;
            }
        }

        $integer = ltrim($integer, '0');
        if ($integer === '') {
            $integer = '0';
        }
        $result = $sign . $integer;
        if ($fraction !== null) {
            $fraction = rtrim($fraction, '0');
            if ($fraction !== '') {
                $result .= '.' . $fraction;
            }
        }
        if ($exponent !== null) {
            $result .= 'e' . $exponent;
        }

        return $result;
    }

    /**
     * ECMAScript Number::toString for a finite double ("-0" becomes "0").
     */
    public static function esToString(float $double): string
    {
        if ($double == 0.0) {
            return '0';
        }
        $negative = $double < 0.0;
        $absolute = $negative ? -$double : $double;

        [$digits, $position] = self::shortest($absolute);
        $count = strlen($digits);
        $point = $position;
        if ($count <= $point && $point <= 21) {
            $text = $digits . str_repeat('0', $point - $count);
        } elseif ($point > 0 && $point <= 21) {
            $text = substr($digits, 0, $point) . '.' . substr($digits, $point);
        } elseif ($point > -6 && $point <= 0) {
            $text = '0.' . str_repeat('0', -$point) . $digits;
        } else {
            $text = $digits[0];
            if ($count > 1) {
                $text .= '.' . substr($digits, 1);
            }
            $shifted = $point - 1;
            $text .= 'e' . ($shifted < 0 ? '-' : '+') . abs($shifted);
        }

        return $negative ? '-' . $text : $text;
    }

    /**
     * Shortest decimal digits of a finite positive double.
     *
     * Returns [digits, position] with value = 0.digits * 10^position.
     * The digits are the fewest that still parse back to the double
     * (ties broken towards the closer value, then towards even).
     */
    public static function shortest(float $double): array
    {
        [$exact, $exactPosition] = self::exactDecimal($double);
        $length = strlen($exact);
        for ($kept = 1; $kept <= 17; $kept++) {
            if ($length <= $kept) {
                return [$exact, $exactPosition];
            }
            $truncated = substr($exact, 0, $kept);
            $incremented = self::addOne($truncated);
            if (strlen($incremented) > $kept) {
                $upDigits = '1';
                $upPosition = $exactPosition + 1;
            } else {
                $upDigits = $incremented;
                $upPosition = $exactPosition;
            }

            $downParses = (float) self::parseable($truncated, $exactPosition) === $double;
            $upParses = (float) self::parseable($upDigits, $upPosition) === $double;
            if (!$downParses && !$upParses) {
                continue;
            }
            if ($downParses && !$upParses) {
                return [$truncated, $exactPosition];
            }
            if ($upParses && !$downParses) {
                return [$upDigits, $upPosition];
            }

            $remainder = substr($exact, $kept);
            $half = '5' . str_repeat('0', strlen($remainder) - 1);
            $comparison = strcmp($remainder, $half);
            if ($comparison < 0) {
                return [$truncated, $exactPosition];
            }
            if ($comparison > 0) {
                return [$upDigits, $upPosition];
            }
            $lastDown = ord($truncated[$kept - 1]) - 48;
            if ($lastDown % 2 === 0) {
                return [$truncated, $exactPosition];
            }

            return [$upDigits, $upPosition];
        }

        return [substr($exact, 0, 17), $exactPosition];
    }

    /**
     * Exact decimal expansion of a finite nonzero double:
     * [digits, position] with value = 0.digits * 10^position.
     *
     * @return array{string, int}
     */
    private static function exactDecimal(float $double): array
    {
        $words = unpack('N2', pack('E', $double));
        assert($words !== false);
        $high = $words[1];
        $low = $words[2];
        $biased = ($high >> 20) & 0x7FF;
        // The 52-bit fraction plus the implicit leading bit; the product
        // stays below 2^53 so 64-bit integer arithmetic is exact.
        $mantissa = (string) ((($high & 0xFFFFF) * 4294967296 + $low) + ($biased === 0 ? 0 : 4503599627370496));
        $binaryExponent = $biased === 0 ? -1074 : $biased - 1075;

        if ($binaryExponent >= 0) {
            $digits = $mantissa;
            for ($i = 0; $i < $binaryExponent; $i++) {
                $digits = self::multiplySmall($digits, 2);
            }
            $position = strlen($digits);
            $stripped = rtrim($digits, '0');
            if ($stripped === '') {
                return ['0', 0];
            }

            return [$stripped, $position];
        }

        $digits = $mantissa;
        for ($i = 0; $i < -$binaryExponent; $i++) {
            $digits = self::multiplySmall($digits, 5);
        }
        $position = $binaryExponent + strlen($digits);
        $stripped = rtrim(ltrim($digits, '0'), '0');
        if ($stripped === '') {
            return ['0', 0];
        }

        return [$stripped, $position];
    }

    private static function parseable(string $digits, int $position): string
    {
        $text = $digits[0];
        if (strlen($digits) > 1) {
            $text .= '.' . substr($digits, 1);
        }

        return $text . 'e' . ($position - 1);
    }

    private static function multiplySmall(string $value, int $factor): string
    {
        $carry = 0;
        $reversed = '';
        for ($i = strlen($value) - 1; $i >= 0; $i--) {
            $product = (ord($value[$i]) - 48) * $factor + $carry;
            $reversed .= chr($product % 10 + 48);
            $carry = intdiv($product, 10);
        }
        while ($carry > 0) {
            $reversed .= chr($carry % 10 + 48);
            $carry = intdiv($carry, 10);
        }
        $result = ltrim(strrev($reversed), '0');

        return $result === '' ? '0' : $result;
    }

    private static function addOne(string $value): string
    {
        $position = strlen($value) - 1;
        $carry = 1;
        while ($position >= 0 && $carry > 0) {
            $sum = (ord($value[$position]) - 48) + $carry;
            $value[$position] = chr($sum % 10 + 48);
            $carry = intdiv($sum, 10);
            $position--;
        }
        if ($carry > 0) {
            $value = '1' . $value;
        }

        return $value;
    }
}
