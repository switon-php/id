<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;

/**
 * Generates UUID version 7 strings with millisecond time ordering.
 *
 * Use when you need UUID compatibility with better insertion locality than UUID v4.
 *
 * When the local same-millisecond sequence is exhausted, this implementation advances
 * a logical millisecond to preserve monotonic output instead of wrapping the 12-bit field.
 *
 * @see \Switon\Id\IdGeneratorInterface
 */
class Uuid7 implements IdGeneratorInterface
{
    protected const MAX_SEQUENCE = 0x0FFF;

    /** Time source for millisecond timestamp generation. */
    #[Autowired] protected ClockInterface $clock;

    /** Last timestamp used for local monotonic ordering. */
    protected int $lastTimestamp = 0;

    /** Sequence for calls in the same millisecond. */
    protected int $sequence = 0;

    /**
     * Returns one UUID v7 string.
     *
     * @return string UUID in canonical 36-character format
     */
    public function next(): string
    {
        // Get current Unix timestamp in milliseconds
        $timestamp = (int)($this->clock->microtime() * 1000);

        // Clamp clock rollback, then advance a logical millisecond when the 12-bit sequence is full.
        if ($timestamp < $this->lastTimestamp) {
            $timestamp = $this->lastTimestamp;
        }

        if ($timestamp === $this->lastTimestamp) {
            if ($this->sequence >= self::MAX_SEQUENCE) {
                $timestamp = $this->lastTimestamp + 1;
                $this->lastTimestamp = $timestamp;
                $this->sequence = 0;
            } else {
                $this->sequence++;
            }
        } else {
            $this->sequence = 0;
            $this->lastTimestamp = $timestamp;
        }

        $bytes = unpack('N1a/n1b/n1c/n1d/n1e/N1f', random_bytes(16));

        return sprintf(
            '%08x-%04x-%04x-%04x-%04x%08x',
            $timestamp >> 16,
            $timestamp & 0xFFFF,
            (0x7000 | ($this->sequence & self::MAX_SEQUENCE)),
            (0x8000 | ($bytes['b'] & 0x3FFF)),
            $bytes['c'],
            ($bytes['d'] << 16) | $bytes['e']
        );
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
}
