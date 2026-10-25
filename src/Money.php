<?php
declare(strict_types=1);

/**
 * Money — an immutable money value object that stores amounts in minor
 * units (cents, paisa, etc.) as integers, so 0.1 + 0.2 style float errors
 * can never creep into prices, totals, or invoices.
 *
 * Money::fromMajor('19.99', 'USD')->add(Money::fromMajor('0.01', 'USD'))
 *   => 2000 minor units, formatted as $20.00
 *
 * Rules: arithmetic only between the same currency (throws on mismatch),
 * amounts are rounded half-up when converting from major units, and
 * multiply() rounds the result half-up too. Formatting uses a small
 * built-in symbol map and falls back to the currency code.
 */
final class Money
{
    /** Currencies with zero minor units (no cents). */
    private const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'CLP', 'PYG', 'XAF', 'XOF', 'XPF'];

    /** Common symbols; anything else formats as "CODE 1,234.56". */
    private const SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => 'Rs ',
        'INR' => '₹', 'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF ',
        'AED' => 'AED ', 'SAR' => 'SAR ', 'JPY' => '¥', 'CNY' => '¥',
    ];

    private int $minor;
    private string $currency;

    private function __construct(int $minor, string $currency)
    {
        $this->minor = $minor;
        $this->currency = strtoupper($currency);
    }

    /** Build from minor units, e.g. 1999 cents. */
    public static function fromMinor(int $minor, string $currency): self
    {
        return new self($minor, $currency);
    }

    /** Build from a major-unit string ('19.99') — never pass a float. */
    public static function fromMajor(string $major, string $currency): self
    {
        $currency = strtoupper($currency);
        $scale = self::scale($currency);
        // String math via round() on the parsed value is exact enough here:
        // the input is a short decimal string, and we round half-up to an int.
        $minor = (int) round((float) $major * (10 ** $scale));
        return new self($minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function minor(): int { return $this->minor; }
    public function currency(): string { return $this->currency; }

    /** Major-unit value as a string with the right decimal places. */
    public function major(): string
    {
        $scale = self::scale($this->currency);
        if ($scale === 0) {
            return (string) $this->minor;
        }
        $divisor = 10 ** $scale;
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);
        return $sign . intdiv($abs, $divisor) . '.' . str_pad((string) ($abs % $divisor), $scale, '0', STR_PAD_LEFT);
    }

    public function format(): string
    {
        $scale = self::scale($this->currency);
        $formatted = number_format((float) $this->major(), $scale, '.', ',');
        $symbol = self::SYMBOLS[$this->currency] ?? ($this->currency . ' ');
        return $symbol . $formatted;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}"
            );
        }
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor - $other->minor, $this->currency);
    }

    /** Multiply by a factor (e.g. quantity or tax rate), rounded half-up. */
    public function multiply(float $factor): self
    {
        return new self((int) round($this->minor * $factor), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /** -1, 0, or 1 — like strcmp, for sorting and comparisons. */
    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);
        return $this->minor <=> $other->minor;
    }

    public function isZero(): bool { return $this->minor === 0; }
    public function isNegative(): bool { return $this->minor < 0; }

    private static function scale(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }
}
