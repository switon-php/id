<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\RandomInterface;
use Switon\Id\Exception\GmpExtensionRequiredException;

/**
 * Generates KSUID strings that are sortable by creation time.
 *
 * Use when you need roughly time-ordered IDs in a compact URL-safe string format.
 *
 * Output format:
 * - 4-byte timestamp (seconds since KSUID epoch)
 * - 16-byte random payload
 * - Base62-encoded to 27 characters
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see https://github.com/segmentio/ksuid
 */
class Ksuid implements IdGeneratorInterface
{
    /** Time source for epoch-relative seconds. */
    #[Autowired] protected ClockInterface $clock;

    /** Random source for 16-byte payload generation. */
    #[Autowired] protected RandomInterface $random;

    /** Base62 alphabet. */
    public const BASE62_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** KSUID epoch (2014-05-13 16:53:20 UTC). */
    public const EPOCH_SECONDS = 1400000000;

    /**
     * Returns one KSUID string.
     *
     * @return string 27-character Base62 KSUID
     */
    public function next(): string
    {
        // Get current timestamp in seconds since KSUID epoch
        $timestamp = $this->clock->time() - self::EPOCH_SECONDS;

        // Generate 16 bytes of random data
        $randomBytes = $this->random->bytes(16);

        // Combine timestamp (4 bytes) + random data (16 bytes) = 20 bytes total
        $timestampBytes = pack('N', $timestamp);
        $combinedBytes = $timestampBytes . $randomBytes;

        // Encode 20 bytes to Base62 (should result in 27 characters)
        return $this->encodeBase62($combinedBytes);
    }

    /**
     * Encodes binary payload as Base62 text.
     *
     * @param string $data Binary data to encode
     * @return string Base62 encoded string
     */
    protected function encodeBase62(string $data): string
    {
        $this->requireGmp();

        if (empty($data)) {
            return '';
        }

        $result = '';

        // Convert binary data to big integer
        $dataLength = strlen($data);
        $number = gmp_init(0);

        for ($i = 0; $i < $dataLength; $i++) {
            $byte = ord($data[$i]);
            $number = gmp_add(gmp_mul($number, 256), $byte);
        }

        // Convert to Base62
        $base = gmp_init(62);
        do {
            $remainder = gmp_mod($number, $base);
            $result = self::BASE62_ALPHABET[gmp_intval($remainder)] . $result;
            $number = gmp_div($number, $base);
        } while (gmp_cmp($number, 0) > 0);

        // Pad to 27 characters if necessary
        return str_pad($result, 27, '0', STR_PAD_LEFT);
    }

    /**
     * Decodes Base62 text back to binary payload.
     *
     * @param string $data Base62 encoded string
     * @return string Binary data
     */
    protected function decodeBase62(string $data): string
    {
        $this->requireGmp();

        if (empty($data)) {
            return '';
        }

        $alphabet = self::BASE62_ALPHABET;
        $base = gmp_init(62);
        $number = gmp_init(0);

        // Convert Base62 string to big integer
        for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
            $char = $data[$i];
            $value = strpos($alphabet, $char);
            if ($value === false) {
                return ''; // Invalid character
            }
            $number = gmp_add(gmp_mul($number, $base), $value);
        }

        // Convert big integer to binary data
        $result = '';
        while (gmp_cmp($number, 0) > 0) {
            $remainder = gmp_mod($number, 256);
            $result = chr(gmp_intval($remainder)) . $result;
            $number = gmp_div($number, 256);
        }

        // Pad to 20 bytes if necessary
        return str_pad($result, 20, "\x00", STR_PAD_LEFT);
    }

    /**
     * Extracts the Unix timestamp from a KSUID string.
     *
     * @param string $ksuid The KSUID to decode
     * @return int Unix timestamp in seconds
     */
    public function extractTimestamp(string $ksuid): int
    {
        // Validate KSUID length
        if (strlen($ksuid) !== 27) {
            return 0;
        }

        // Decode Base62 to binary (20 bytes total)
        $binary = $this->decodeBase62($ksuid);
        if (strlen($binary) !== 20) {
            return 0;
        }

        // Extract first 4 bytes as timestamp
        $timestampBytes = substr($binary, 0, 4);
        $timestampArray = unpack('N', $timestampBytes);
        $timestamp = $timestampArray[1];

        // Convert from KSUID epoch to Unix timestamp
        return $timestamp + self::EPOCH_SECONDS;
    }

    /** {@inheritDoc} */
    public function nextN(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->next();
        }
        return $result;
    }

    /**
     * Require ext-gmp for Base62 conversions.
     */
    protected function requireGmp(): void
    {
        if (!function_exists('gmp_init')) {
            GmpExtensionRequiredException::raise('gmp extension is required for KSUID generation and parsing');
        }
    }
}
