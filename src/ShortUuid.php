<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Exception\InvalidArgumentException;
use Switon\Id\Exception\GmpExtensionRequiredException;

/**
 * Converts UUID strings to and from a compact Base58 representation.
 *
 * Use when you need UUID compatibility with shorter URL-friendly text.
 *
 * @see \Switon\Id\Uuid4
 * @see https://github.com/skorokithakis/short-uuid
 */
class ShortUuid
{
    /** Base58 alphabet (Bitcoin style, no 0, O, I, l). */
    public const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Encodes UUID text to 22-character ShortUUID text.
     *
     * @param string $uuid UUID with or without hyphens
     * @return string 22-character ShortUUID
     * @throws InvalidArgumentException If UUID format is invalid
     */
    public static function encode(string $uuid): string
    {
        // Normalize UUID (remove hyphens and convert to lowercase)
        $normalized = strtolower(str_replace('-', '', $uuid));

        // Validate UUID format
        if (!preg_match('/^[0-9a-f]{32}$/', $normalized)) {
            InvalidArgumentException::raise('Invalid UUID format: expected 32-36 character hex string');
        }

        // Convert hex string to binary
        $binary = hex2bin($normalized);

        // Encode binary data to Base58
        return self::encodeBase58($binary);
    }

    /**
     * Decodes 22-character ShortUUID text to canonical UUID text.
     *
     * @param string $shortUuid 22-character ShortUUID
     * @return string Canonical UUID string (36 characters)
     * @throws InvalidArgumentException If ShortUUID format is invalid
     */
    public static function decode(string $shortUuid): string
    {
        // Validate ShortUUID length
        if (strlen($shortUuid) !== 22) {
            InvalidArgumentException::raise('ShortUUID must be exactly 22 characters');
        }

        // Validate characters are from Base58 alphabet
        if (!preg_match('/^[' . preg_quote(self::BASE58_ALPHABET, '/') . ']{22}$/', $shortUuid)) {
            InvalidArgumentException::raise('Invalid ShortUUID characters');
        }

        try {
            // Decode Base58 to binary
            $binary = self::decodeBase58($shortUuid);

            // Convert binary to hex
            $hex = bin2hex($binary);

            // Validate we got exactly 32 hex characters (16 bytes)
            if (strlen($hex) !== 32) {
                InvalidArgumentException::raise('Invalid ShortUUID: decoded to wrong length');
            }

            // Format as UUID with hyphens
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12)
            );

        } catch (GmpExtensionRequiredException $e) {
            throw $e;
        } catch (\Throwable $e) {
            InvalidArgumentException::raise('Invalid ShortUUID format: {error}', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generates a random ShortUUID using UUID v4 as source.
     *
     * @return string 22-character ShortUUID
     */
    public static function generate(): string
    {
        // Generate random UUID4 using PHP's cryptographically secure random_bytes()
        $bytes = unpack('N1a/n1b/n1c/n1d/n1e/N1f', random_bytes(16));

        $uuid = sprintf(
            '%08x-%04x-%04x-%04x-%04x%08x',
            $bytes['a'],
            $bytes['b'],
            ($bytes['c'] & 0x0FFF) | 0x4000, // Version 4
            ($bytes['d'] & 0x3FFF) | 0x8000, // Variant
            $bytes['e'],
            $bytes['f']
        );

        return self::encode($uuid);
    }

    /**
     * Encodes binary data to Base58 text.
     */
    protected static function encodeBase58(string $data): string
    {
        self::requireGmp();

        $alphabet = self::BASE58_ALPHABET;
        $base = strlen($alphabet);

        // Convert binary data to big integer
        $number = gmp_init(0);
        for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
            $number = gmp_add(gmp_mul($number, 256), ord($data[$i]));
        }

        $result = '';

        // Convert to Base58
        while (gmp_cmp($number, 0) > 0) {
            $remainder = gmp_mod($number, $base);
            $result = $alphabet[gmp_intval($remainder)] . $result;
            $number = gmp_div($number, $base);
        }

        // Handle leading zeros in input
        for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
            $result = $alphabet[0] . $result;
        }

        // Ensure exactly 22 characters for UUIDs
        return str_pad($result, 22, $alphabet[0], STR_PAD_LEFT);
    }

    /**
     * Decodes Base58 text to binary data.
     */
    protected static function decodeBase58(string $data): string
    {
        self::requireGmp();

        $alphabet = self::BASE58_ALPHABET;
        $base = strlen($alphabet);

        $number = gmp_init(0);
        for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
            $char = $data[$i];
            $value = strpos($alphabet, $char);
            if ($value === false) {
                InvalidArgumentException::raise('Invalid Base58 character: {char}', ['char' => $char]);
            }
            $number = gmp_add(gmp_mul($number, $base), $value);
        }

        $result = '';
        while (gmp_cmp($number, 0) > 0) {
            $remainder = gmp_mod($number, 256);
            $result = chr(gmp_intval($remainder)) . $result;
            $number = gmp_div($number, 256);
        }

        // Ensure exactly 16 bytes, pad with leading zeros if necessary
        return str_pad($result, 16, "\x00", STR_PAD_LEFT);
    }

    /**
     * Require ext-gmp for Base58 conversions.
     */
    protected static function requireGmp(): void
    {
        if (!function_exists('gmp_init')) {
            GmpExtensionRequiredException::raise('gmp extension is required for ShortUuid encode and decode');
        }
    }
}
