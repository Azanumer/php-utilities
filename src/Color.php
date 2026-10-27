<?php
/**
 * Color utilities: hex/RGB/HSL conversion, lighten/darken, WCAG luminance
 * and contrast ratio — handy for themes, OG images and accessibility checks.
 */
class Color
{
    private int $r;
    private int $g;
    private int $b;

    /** Accepts "#ff8800", "ff8800" or 3-digit "#f80". Throws on invalid input. */
    public static function fromHex(string $hex): self
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new InvalidArgumentException("Invalid hex color: {$hex}");
        }
        return new self(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    public static function fromRgb(int $r, int $g, int $b): self
    {
        return new self($r, $g, $b);
    }

    private function __construct(int $r, int $g, int $b)
    {
        // Clamp instead of throwing: callers lightening/darkening past the
        // edges (e.g. lighten white) should get white, not an exception.
        $this->r = max(0, min(255, $r));
        $this->g = max(0, min(255, $g));
        $this->b = max(0, min(255, $b));
    }

    public function toHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
    }

    /** @return array{int,int,int} */
    public function toRgb(): array
    {
        return [$this->r, $this->g, $this->b];
    }

    /** @return array{float,float,float} h in 0-360, s/l in 0-100 */
    public function toHsl(): array
    {
        $r = $this->r / 255;
        $g = $this->g / 255;
        $b = $this->b / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, round($l * 100, 2)];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if ($max === $r) {
            $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
        } elseif ($max === $g) {
            $h = ($b - $r) / $d + 2;
        } else {
            $h = ($r - $g) / $d + 4;
        }
        return [round($h * 60, 2), round($s * 100, 2), round($l * 100, 2)];
    }

    /** Move lightness toward white by $percent (0-100). */
    public function lighten(float $percent): self
    {
        $p = max(0, min(100, $percent)) / 100;
        return new self(
            (int) round($this->r + (255 - $this->r) * $p),
            (int) round($this->g + (255 - $this->g) * $p),
            (int) round($this->b + (255 - $this->b) * $p)
        );
    }

    /** Move lightness toward black by $percent (0-100). */
    public function darken(float $percent): self
    {
        $p = max(0, min(100, $percent)) / 100;
        return new self(
            (int) round($this->r * (1 - $p)),
            (int) round($this->g * (1 - $p)),
            (int) round($this->b * (1 - $p))
        );
    }

    /** WCAG relative luminance, 0 (black) to 1 (white). */
    public function luminance(): float
    {
        $channel = function (int $c): float {
            $v = $c / 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * $channel($this->r) + 0.7152 * $channel($this->g) + 0.0722 * $channel($this->b);
    }

    /** WCAG contrast ratio vs another color: 1 (identical) to 21 (black/white). */
    public function contrastRatio(self $other): float
    {
        $a = $this->luminance();
        $b = $other->luminance();
        return round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
    }

    public function isLight(): bool
    {
        return $this->luminance() > 0.5;
    }

    /** '#000000' or '#ffffff' — whichever reads better on this color. */
    public function bestTextColor(): string
    {
        return $this->contrastRatio(self::fromHex('#000000')) >= $this->contrastRatio(self::fromHex('#ffffff'))
            ? '#000000'
            : '#ffffff';
    }
}
