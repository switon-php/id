<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\RandomInterface;
use Switon\Core\Exception\InvalidArgumentException;
use Switon\Id\Exception\SequenceBackendUnavailableException;
use Switon\Redis\ClientInterface;
use Switon\Sync\Mutex;
use Switon\Sync\MutexInterface;

/**
 * Generates distributed 64-bit numeric IDs with second-level time ordering.
 *
 * Use when you need sortable integer IDs across multiple nodes and processes.
 *
 * Configure with:
 * - <code>shard</code> for logical node partitioning
 * - <code>prefix</code> for Redis key isolation across apps or tenants
 * - <code>prefetch</code> and <code>jitter</code> to balance Redis load and sequence distribution
 * - <code>redis</code> for one or two sequence backends (primary then standby)
 *
 * Bit layout (MSB to LSB):
 * - 32-bit seconds since 2020-01-01 00:00:00 UTC
 * - 6-bit shard
 * - 1-bit Redis index
 * - 24-bit sequence
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see \Switon\Redis\ClientInterface
 */
class Snowflake implements IdGeneratorInterface
{
    /** @var int Epoch: 2020-01-01 00:00:00 UTC (seconds) */
    protected const EPOCH_SECONDS = 1577836800;

    /** @var int Max sequence (24 bits) */
    protected const MAX_SEQUENCE = 0xFFFFFF;

    /** @var int Max shard (6 bits) */
    protected const MAX_SHARD = 63;

    /** @var int Extra milliseconds to wait after next-second boundary (avoid race) */
    protected const RETRY_BUFFER_MS = 20;

    #[Autowired] protected int $shard = 0;

    /** @var string Redis key prefix (default "snowflake") */
    #[Autowired] protected string $prefix = 'snowflake';

    /** @var int Sequence numbers requested per Redis INCR (default 100) */
    #[Autowired] protected int $prefetch = 100;

    /** @var int Max jitter for first batch each second (0 = disabled; default 100_000) */
    #[Autowired] protected int $jitter = 100_000;

    #[Autowired] protected RandomInterface $random;

    /** @var ClockInterface Time source for batch keys and wait calculation (testable). */
    #[Autowired] protected ClockInterface $clock;

    /** @var list<ClientInterface> 1–2 Redis instances (try first, on failure try second). */
    #[Autowired(instances: true)] protected array $redis = [ClientInterface::class];

    /** @var MutexInterface Mutex for this instance only (not shared); guards next()/nextN() in concurrent contexts. */
    protected MutexInterface $mutex;

    /** @var int High bits (timestamp + shard + redisIndex), 0 when no batch */
    protected int $base = 0;

    /** @var int Next sequence number to use in current batch */
    protected int $sequence = 0;

    /** @var int IDs left in current batch, 0 = need fetch */
    protected int $remaining = 0;

    /** @var int Unix second when current batch was fetched */
    protected int $second = 0;

    public function __construct()
    {
        $this->mutex = new Mutex();

        if ($this->shard < 0 || $this->shard > self::MAX_SHARD) {
            InvalidArgumentException::raise('Shard must be between 0 and {max}', ['max' => self::MAX_SHARD]);
        }
        if ($this->prefetch < 1 || $this->prefetch > 1000) {
            InvalidArgumentException::raise('Prefetch must be between 1 and 1000');
        }
        if ($this->jitter < 0 || $this->jitter > self::MAX_SEQUENCE) {
            InvalidArgumentException::raise('Jitter must be between 0 and {max}', ['max' => self::MAX_SEQUENCE]);
        }
        $n = is_array($this->redis) ? count($this->redis) : 0;
        if ($n < 1 || $n > 2) {
            InvalidArgumentException::raise('Redis must have 1 or 2 instances');
        }
    }

    /**
     * Get Redis client for instance index (0 or 1).
     */
    protected function getRedis(int $index): ClientInterface
    {
        $list = array_values($this->redis);
        return $list[$index % count($list)];
    }

    /**
     * Fetch a batch from Redis and set base, sequence, remaining, second.
     * Must be called with lock held.
     *
     * When the current second's sequence space is exhausted, returns wait time instead of
     * sleeping inside the critical section, so callers must release lock -> sleep -> re-acquire -> retry.
     *
     * @param int|null $requestSize Number of sequence numbers to request; null = use {@see Snowflake::$prefetch}
     * @return int|null Microseconds to wait before retry when sequence exhausted this second; null on success
     */
    protected function fetchBatch(?int $requestSize = null): ?int
    {
        $requestSize = $requestSize ?? $this->prefetch;
        if ($requestSize < 1 || $requestSize > self::MAX_SEQUENCE) {
            InvalidArgumentException::raise('Request size must be between 1 and {max}', ['max' => self::MAX_SEQUENCE]);
        }
        $list = array_values($this->redis);
        $n = count($list);

        $now = $this->clock->time();
        $ts = $now - self::EPOCH_SECONDS;
        if ($ts < 0 || $ts > 0xFFFFFFFF) {
            InvalidArgumentException::raise('Timestamp out of range (epoch 2020)');
        }

        $lastException = null;
        $newVal = null;
        $start = null;
        $redisIndex = null;
        $redis = null;

        for ($i = 0; $i < $n; $i++) {
            $redisIndex = $i;
            $redis = $list[$i];
            $key = "{$this->prefix}:seq:{$this->shard}:{$redisIndex}:{$now}";
            try {
                $newVal = $redis->incrBy($key, $requestSize);
                $firstUse = $newVal === $requestSize;
                if ($firstUse) {
                    $maxOffset = self::MAX_SEQUENCE - $requestSize + 1;
                    $offset = $this->jitter > 0 ? $this->random->int(0, min($this->jitter, $maxOffset)) : 0;
                    if ($offset > 0) {
                        $redis->incrBy($key, $offset);
                        $newVal += $offset;
                    }
                    $start = $offset;
                    $redis->expire($key, 60);
                } else {
                    $start = $newVal - $requestSize;
                }
                break;
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        if ($newVal === null || $start === null) {
            SequenceBackendUnavailableException::raise(
                'Failed to allocate Snowflake sequence from configured Redis backend: {error}',
                ['error' => $lastException?->getMessage() ?? 'No Redis instance available'],
                previous: $lastException instanceof \Exception ? $lastException : null
            );
        }

        // Sequence space exhausted this second: tell caller to wait outside the lock, then retry.
        if ($start > self::MAX_SEQUENCE) {
            $microtime = $this->clock->microtime();
            $usInSecond = (int)(($microtime - (int)$microtime) * 1_000_000);
            return 1_000_000 - $usInSecond + (self::RETRY_BUFFER_MS * 1000);
        }

        $this->base = (($ts & 0xFFFFFFFF) << 31)
            | (($this->shard & self::MAX_SHARD) << 25)
            | (($redisIndex & 1) << 24);
        $this->sequence = $start;
        $this->remaining = min($requestSize, self::MAX_SEQUENCE - $start + 1);
        $this->second = $now;
        return null;
    }

    public function next(): int|string
    {
        $now = $this->clock->time();
        // Have batch and same second → no Redis, no lock, just allocate one
        if ($this->remaining > 0 && $now === $this->second) {
            $id = $this->base | ($this->sequence & self::MAX_SEQUENCE);
            $this->sequence++;
            $this->remaining--;
            return $id;
        }
        // No batch or new second → fetch under lock; if sequence exhausted this second,
        // wait outside the lock to avoid blocking all other coroutines.
        $lock = $this->mutex->guard();
        try {
            while (true) {
                if ($this->remaining <= 0 || $now !== $this->second) {
                    $waitUs = $this->fetchBatch();
                    if ($waitUs !== null) {
                        $lock->release();
                        usleep($waitUs);
                        $lock = $this->mutex->guard();
                        continue;
                    }
                }
                $id = $this->base | ($this->sequence & self::MAX_SEQUENCE);
                $this->sequence++;
                $this->remaining--;
                return $id;
            }
        } finally {
            $lock->release();
        }
    }

    public function nextN(int $count): array
    {
        if ($count < 1 || $count > self::MAX_SEQUENCE) {
            InvalidArgumentException::raise('Count must be between 1 and {max}', ['max' => self::MAX_SEQUENCE]);
        }
        $now = $this->clock->time();
        // Enough in batch and same second → no Redis, no lock, just allocate
        if ($this->remaining >= $count && $now === $this->second) {
            $result = [];
            for ($i = 0; $i < $count; $i++) {
                $result[] = $this->base | ($this->sequence & self::MAX_SEQUENCE);
                $this->sequence++;
                $this->remaining--;
            }
            return $result;
        }
        // Not enough or wrong second:
        // - Consume any remaining IDs from the current local batch first to avoid wasting
        //   Redis-allocated sequence ranges across a second boundary.
        // - Then fetch a new batch to fill the rest.
        //
        // Note: when called just after a second boundary, the first part of the returned list
        // may have an earlier timestamp (from the previous second) if local batch remains.
        $lock = $this->mutex->guard();
        try {
            $result = [];
            if ($this->remaining > 0) {
                $take = min($this->remaining, $count);
                for ($i = 0; $i < $take; $i++) {
                    $result[] = $this->base | ($this->sequence & self::MAX_SEQUENCE);
                    $this->sequence++;
                    $this->remaining--;
                }
            }
            $need = $count - count($result);
            if ($need > 0) {
                while (true) {
                    $waitUs = $this->fetchBatch(max($need, $this->prefetch));
                    if ($waitUs !== null) {
                        $lock->release();
                        usleep($waitUs);
                        $lock = $this->mutex->guard();
                        continue;
                    }
                    break;
                }
                for ($i = 0; $i < $need; $i++) {
                    $result[] = $this->base | ($this->sequence & self::MAX_SEQUENCE);
                    $this->sequence++;
                    $this->remaining--;
                }
            }
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Parse a Snowflake ID into its components (timestamp, shard, redis index, sequence).
     * Override in subclasses if you use a different bit layout.
     *
     * @param int|string $id 64-bit Snowflake ID
     * @return array{timestamp: int, datetime: string, shard: int, redisIndex: int, sequence: int}
     * Components; <code>datetime</code> is UTC in <code>Y-m-d H:i:s</code> format
     */
    public function parse(int|string $id): array
    {
        $id = (int)$id;
        $sequence = $id & self::MAX_SEQUENCE;
        $redisIndex = ($id >> 24) & 1;
        $shard = ($id >> 25) & self::MAX_SHARD;
        $ts = ($id >> 31) & 0xFFFFFFFF;
        $unixSec = self::EPOCH_SECONDS + $ts;
        return [
            'timestamp' => $unixSec,
            'datetime' => gmdate('Y-m-d H:i:s', $unixSec),
            'shard' => $shard,
            'redisIndex' => $redisIndex,
            'sequence' => $sequence,
        ];
    }
}
