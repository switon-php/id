<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Fixtures;

use Switon\Redis\ClientInterface;

/**
 * In-memory fake Redis for Snowflake unit tests (incrBy + expire only).
 */
class FakeRedisForSnowflake implements ClientInterface
{
    /** @var array<string, int> */
    protected array $counters = [];

    public function getUri(): ?string
    {
        return null;
    }

    public function incrBy(string $key, int $increment): int
    {
        $current = $this->counters[$key] ?? 0;
        $this->counters[$key] = $current + $increment;
        return $this->counters[$key];
    }

    public function expire(string $key, int $ttl): bool
    {
        return true;
    }
}
