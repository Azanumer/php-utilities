<?php

declare(strict_types=1);

namespace Azanumer\Utils;

/**
 * RFC 4122 UUID generation and validation — no dependencies.
 *
 *   $id  = Uuid::v4();            // random, e.g. "f47ac10b-58cc-4372-a567-0e02b2c3d479"
 *   $id  = Uuid::v7();            // time-ordered (Unix ms + 74 random bits), DB-index friendly
 *   Uuid::isValid($id);           // true / false, any version
 *   Uuid::version($id);            // 4, 7, ... or null when invalid
 *   $bin = Uuid::toBytes($id);    // 16-byte binary for BINARY(16) columns
 *   Uuid::fromBytes($bin);        // back to canonical string
 */
final class Uuid
{
    private function __construct() {}

    /**
     * Version 4: 122 bits of cryptographic randomness.
     */
    public static function v4(): string
    {
        $bytes = random_bytes(16);
        // version nibble = 0100, variant bits = 10xx.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return self::stringify($bytes);
    }

    /**
     * Version 7: 48-bit Unix timestamp in milliseconds, then 74 random bits.
     * Time-ordered — sortable and index-friendly in databases.
     *
     * @param int|null $millis Unix timestamp in milliseconds (default: now).
     */
    public static function v7(?int $millis = null): string
    {
        $millis = $millis ?? (int) (microtime(true) * 1000);
        $bytes  = random_bytes(16);

        // First 6 bytes = big-endian millisecond timestamp.
        for ($i = 0; $i < 6; $i++) {
            $bytes[$i] = chr(($millis >> (8 * (5 - $i))) & 0xff);
        }
        // version nibble = 0111, variant bits = 10xx.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return self::stringify($bytes);
    }

    /**
     * Validate any canonical UUID (versions 1–8, nil UUID included).
     */
    public static function isValid(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    /**
     * Version nibble of a valid UUID, or null when invalid.
     */
    public static function version(string $uuid): ?int
    {
        if (!self::isValid($uuid)) {
            return null;
        }
        return (int) hexdec($uuid[14]);
    }

    /**
     * Canonical string → 16-byte binary (for BINARY(16) storage).
     *
     * @throws \InvalidArgumentException on invalid UUID.
     */
    public static function toBytes(string $uuid): string
    {
        if (!self::isValid($uuid)) {
            throw new \InvalidArgumentException('Invalid UUID: ' . $uuid);
        }
        return hex2bin(str_replace('-', '', $uuid));
    }

    /**
     * 16-byte binary → canonical string.
     *
     * @throws \InvalidArgumentException when not exactly 16 bytes.
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new \InvalidArgumentException('UUID bytes must be exactly 16 bytes.');
        }
        return self::stringify($bytes);
    }

    private static function stringify(string $bytes): string
    {
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
