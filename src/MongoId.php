<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\RandomInterface;

/**
 * Generates MongoDB ObjectId-compatible hexadecimal identifiers.
 *
 * Use when you need ObjectId format compatibility with MongoDB tooling.
 *
 * Output format:
 * - 4-byte Unix timestamp
 * - 3-byte machine identifier
 * - 2-byte process identifier
 * - 3-byte counter
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see https://docs.mongodb.com/manual/reference/bson-types/#objectid
 */
class MongoId implements IdGeneratorInterface
{
    /** Time source for second-level timestamp generation. */
    #[Autowired] protected ClockInterface $clock;

    /** Random source for machine identifier generation. */
    #[Autowired] protected RandomInterface $random;

    /** 3-byte machine identifier. */
    protected int $machineId;

    /** 2-byte process identifier from current PID. */
    protected int $processId;

    /** Counter for calls in the same second. */
    protected int $counter = 0;

    /** Last timestamp used for counter reset. */
    protected int $lastTimestamp = 0;

    public function __construct()
    {
        // Generate random machine identifier (3 bytes)
        $this->machineId = $this->random->int(0, 0xFFFFFF);

        // Use process ID for process identifier (2 bytes)
        $this->processId = getmypid() & 0xFFFF;
    }

    /**
     * Returns one ObjectId string.
     *
     * @return string 24-character hexadecimal ObjectId
     */
    public function next(): string
    {
        $timestamp = $this->clock->time();

        // Reset counter if timestamp changed
        if ($timestamp !== $this->lastTimestamp) {
            $this->counter = 0;
            $this->lastTimestamp = $timestamp;
        } else {
            $this->counter = ($this->counter + 1) & 0xFFFFFF; // 24-bit counter
        }

        // Build ObjectId: timestamp (4) + machine (3) + pid (2) + counter (3) = 12 bytes
        $objectId = pack('N', $timestamp) .           // 4 bytes timestamp
            substr(pack('N', $this->machineId), 1, 3) . // 3 bytes machine ID
            pack('n', $this->processId) .     // 2 bytes process ID
            substr(pack('N', $this->counter), 1, 3);    // 3 bytes counter

        // Convert to hex string (24 characters)
        return bin2hex($objectId);
    }

    /**
     * Extracts the timestamp portion from an ObjectId.
     *
     * @param string $mongoId The ObjectId string
     * @return int Unix timestamp in seconds
     */
    public function extractTimestamp(string $mongoId): int
    {
        if (strlen($mongoId) !== 24) {
            return 0;
        }

        // First 8 hex chars = first 4 bytes = timestamp
        $timestampHex = substr($mongoId, 0, 8);
        return hexdec($timestampHex);
    }

    /**
     * Validates ObjectId text format.
     *
     * @param string $mongoId The ObjectId to validate
     * @return bool True if valid ObjectId format
     */
    public function isValid(string $mongoId): bool
    {
        return strlen($mongoId) === 24 && ctype_xdigit($mongoId);
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
