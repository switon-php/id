<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\RandomInterface;

/**
 * Generates ULID-style identifiers with lexicographic time ordering.
 *
 * Use when you need sortable string IDs that stay URL-safe.
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see https://github.com/ulid/spec
 */
class Ulid implements IdGeneratorInterface
{
    /** Time source for millisecond timestamp generation. */
    #[Autowired] protected ClockInterface $clock;

    /** Random source for the 80-bit entropy section. */
    #[Autowired] protected RandomInterface $random;

    /** Crockford Base32 alphabet (no I, L, O, U). */
    public const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** Last timestamp used for local monotonic ordering. */
    protected int $lastTimestamp = 0;

    /** Last emitted 80-bit entropy block. */
    protected string $entropy = '';

    /**
     * Returns one ULID string.
     *
     * @return string 26-character ULID string
     */
    public function next(): string
    {
        $timestamp = (int)($this->clock->microtime() * 1000);

        if ($timestamp > $this->lastTimestamp || $this->entropy === '') {
            $this->lastTimestamp = $timestamp;
            $this->entropy = $this->random->bytes(10);
        } else {
            $timestamp = $this->lastTimestamp;
            $this->incrementEntropy();
        }

        return $this->encodeTimestamp($timestamp) . $this->encodeEntropy($this->entropy);
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
     * Encode a number to Base32 with specified length.
     */
    protected function encodeTimestamp(int $timestamp): string
    {
        $result = '';

        for ($i = 0; $i < 10; $i++) {
            $result = self::BASE32_ALPHABET[$timestamp & 31] . $result;
            $timestamp >>= 5;
        }

        return $result;
    }

    /**
     * Encode the 80-bit entropy block to 16 Crockford Base32 characters.
     */
    protected function encodeEntropy(string $data): string
    {
        $result = '';
        $buffer = 0;
        $bits = 0;

        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $result .= self::BASE32_ALPHABET[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }
        return $result;
    }

    /**
     * Increment the current 80-bit entropy block for monotonic same-millisecond output.
     */
    protected function incrementEntropy(): void
    {
        for ($i = 9; $i >= 0; $i--) {
            $byte = ord($this->entropy[$i]);
            if ($byte < 0xFF) {
                $this->entropy[$i] = chr($byte + 1);
                return;
            }
            $this->entropy[$i] = "\x00";
        }

        usleep(1000);
        $this->lastTimestamp = max($this->lastTimestamp + 1, (int)($this->clock->microtime() * 1000));
        $this->entropy = $this->random->bytes(10);
    }
}
