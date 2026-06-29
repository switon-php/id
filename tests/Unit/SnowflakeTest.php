<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Core\ClockInterface;
use Switon\Id\Exception\SequenceBackendUnavailableException;
use Switon\Id\Snowflake;
use Switon\Id\Tests\Fixtures\FakeRedisForSnowflake;
use Switon\Id\Tests\TestCase;
use Switon\Redis\ClientInterface;
use Switon\Testing\MockClock;
use RuntimeException;

class SnowflakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->set(ClientInterface::class, new FakeRedisForSnowflake());
    }

    public function testSnowflakeNextNZeroReturnsEmptyArray(): void
    {
        $generator = $this->container->make(Snowflake::class);

        $this->assertSame([], $generator->nextN(0));
    }

    public function testSnowflakeAcceptsOutOfRangeConfiguration(): void
    {
        $generator = $this->container->make(Snowflake::class, [
            'shard' => 64,
            'prefetch' => 5,
        ]);

        $this->assertIsInt($generator->next());
    }

    public function testSnowflakeNextWithFakeRedis(): void
    {
        $generator = $this->container->make(Snowflake::class, [
            'shard' => 0,
            'prefetch' => 10,
        ]);

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $generator->next();
        }

        $this->assertCount(5, $ids);
        $this->assertCount(5, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        }
    }

    public function testSnowflakeNextNWithFakeRedis(): void
    {
        $generator = $this->container->make(Snowflake::class, ['shard' => 1]);

        $ids = $generator->nextN(3);

        $this->assertCount(3, $ids);
        $this->assertCount(3, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertIsInt($id);
        }
    }

    public function testSnowflakeNextNSingleElement(): void
    {
        $generator = $this->container->make(Snowflake::class);

        $ids = $generator->nextN(1);

        $this->assertCount(1, $ids);
        $this->assertIsInt($ids[0]);
        $this->assertGreaterThan(0, $ids[0]);
    }

    public function testSnowflakeNextNLargerThanPrefetch(): void
    {
        $generator = $this->container->make(Snowflake::class, ['prefetch' => 5]);

        $ids = $generator->nextN(8);

        $this->assertCount(8, $ids);
        $this->assertCount(8, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        }
    }

    public function testSnowflakeNextAndNextNInterleavedProduceUniqueIds(): void
    {
        $generator = $this->container->make(Snowflake::class, ['prefetch' => 10]);

        $a = $generator->next();
        $b = $generator->nextN(2);
        $c = $generator->next();

        $all = array_merge([$a], $b, [$c]);
        $this->assertCount(4, $all);
        $this->assertCount(4, array_unique($all), 'Interleaved next() and nextN() must produce unique IDs');
    }

    public function testSnowflakeNextNConsumesRemainingAcrossSecondBoundary(): void
    {
        // Arrange: MockClock at "current" second so time is deterministic; inject old batch for previous second.
        $epoch = 1577836800;
        $oldSecond = $epoch + 100;
        $currentSecond = $oldSecond + 1;
        $clock = new MockClock((float)$currentSecond);
        $this->container->set(ClockInterface::class, $clock);

        $generator = new class () extends Snowflake {
            public function primeBatch(int $base, int $sequence, int $remaining, int $second): void
            {
                $this->base = $base;
                $this->sequence = $sequence;
                $this->remaining = $remaining;
                $this->second = $second;
            }
        };
        $this->injector->inject($generator, ['prefetch' => 10]);

        $ts = $oldSecond - $epoch;
        $oldSequenceStart = 123;
        $oldRemaining = 3;
        $oldBase = (($ts & 0xFFFFFFFF) << 31) | (0 << 25) | (0 << 24);
        $generator->primeBatch($oldBase, $oldSequenceStart, $oldRemaining, $oldSecond);

        // Act: request more than remaining; "now" is current second, so we cross second boundary.
        $ids = $generator->nextN($oldRemaining + 2);

        // Assert: first N IDs come from old batch (not discarded), then new batch fills the rest.
        $this->assertCount($oldRemaining + 2, $ids);
        $this->assertCount($oldRemaining + 2, array_unique($ids), 'IDs must be unique');
        for ($i = 0; $i < $oldRemaining; $i++) {
            $expected = $oldBase | (($oldSequenceStart + $i) & 0xFFFFFF);
            $this->assertSame($expected, $ids[$i], 'Must consume remaining IDs before fetching a new batch');
        }
    }

    public function testSnowflakeJitterZero(): void
    {
        $generator = $this->container->make(Snowflake::class, ['jitter' => 0, 'prefetch' => 10]);

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $generator->next();
        }

        $this->assertCount(5, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        }
    }

    public function testSnowflakeParseRoundTrip(): void
    {
        $generator = $this->container->make(Snowflake::class, ['shard' => 0, 'prefetch' => 10]);
        $id = $generator->next();
        $this->assertIsInt($id);

        $parts = $generator->parse($id);

        $this->assertArrayHasKey('timestamp', $parts);
        $this->assertArrayHasKey('datetime', $parts);
        $this->assertArrayHasKey('shard', $parts);
        $this->assertArrayHasKey('redisIndex', $parts);
        $this->assertArrayHasKey('sequence', $parts);
        $this->assertGreaterThanOrEqual(time() - 2, $parts['timestamp']);
        $this->assertLessThanOrEqual(time() + 2, $parts['timestamp']);
    }

    public function testSnowflakeParseRoundTripWithMockClock(): void
    {
        $fixedSecond = 1577836900;
        $this->container->set(ClockInterface::class, new MockClock((float)$fixedSecond));
        $generator = $this->container->make(Snowflake::class, ['shard' => 0, 'prefetch' => 10]);

        $id = $generator->next();
        $parts = $generator->parse($id);

        $this->assertSame($fixedSecond, $parts['timestamp'], 'Parsed timestamp must match clock time');
        $this->assertSame('2020-01-01 00:01:40', $parts['datetime']);
    }

    public function testSnowflakeParseAcceptsStringId(): void
    {
        $generator = $this->container->make(Snowflake::class);
        $id = 807558947177038242;

        $fromInt = $generator->parse($id);
        $fromString = $generator->parse((string)$id);

        $this->assertSame($fromInt, $fromString);
    }

    public function testSnowflakeParse(): void
    {
        // Arrange: ID with ts=0 (2020-01-01 00:00:00 UTC), shard=2, redisIndex=1, sequence=100
        $ts = 0;
        $shard = 2;
        $redisIndex = 1;
        $sequence = 100;
        $id = ($ts << 31) | ($shard << 25) | ($redisIndex << 24) | $sequence;
        $generator = $this->container->make(Snowflake::class);

        // Act
        $parts = $generator->parse($id);

        // Assert
        $this->assertSame(1577836800, $parts['timestamp']);
        $this->assertSame('2020-01-01 00:00:00', $parts['datetime']);
        $this->assertSame(2, $parts['shard']);
        $this->assertSame(1, $parts['redisIndex']);
        $this->assertSame(100, $parts['sequence']);
    }

    public function testSnowflakeRaisesFrameworkExceptionWhenRedisBackendsFail(): void
    {
        $failingRedis = new class () implements ClientInterface {
            public function getUri(): ?string
            {
                return null;
            }

            public function getTransient(): static
            {
                return $this;
            }

            public function incrBy(string $key, int $increment): int
            {
                throw new RuntimeException('redis down');
            }

            public function expire(string $key, int $ttl): bool
            {
                throw new RuntimeException('redis down');
            }
        };

        $this->container->set(ClientInterface::class, $failingRedis);

        $this->expectException(SequenceBackendUnavailableException::class);
        $this->expectExceptionMessage('Failed to allocate Snowflake sequence from configured Redis backend');

        $generator = $this->container->make(Snowflake::class);
        $generator->next();
    }

    public function testSnowflakeFallsBackToSecondRedisBackendWhenPrimaryFails(): void
    {
        $primary = new class () implements ClientInterface {
            public function getUri(): ?string
            {
                return 'redis://primary';
            }

            public function getTransient(): static
            {
                return $this;
            }

            public function incrBy(string $key, int $increment): int
            {
                throw new RuntimeException('primary unavailable');
            }

            public function expire(string $key, int $ttl): bool
            {
                throw new RuntimeException('primary unavailable');
            }
        };
        $secondary = new FakeRedisForSnowflake();

        $this->container->set('redis.primary', $primary);
        $this->container->set('redis.secondary', $secondary);

        $generator = new class () extends Snowflake {
            public function setRedisBackends(array $redis): void
            {
                $this->redis = $redis;
            }
        };
        $this->injector->inject($generator, ['prefetch' => 10]);
        $generator->setRedisBackends([$primary, $secondary]);

        $id = (int)$generator->next();
        $parts = $generator->parse($id);

        $this->assertSame(1, $parts['redisIndex']);
    }

    public function testSnowflakeAcceptsMoreThanTwoRedisBackends(): void
    {
        $this->container->set('redis.primary', new FakeRedisForSnowflake());
        $this->container->set('redis.secondary', new FakeRedisForSnowflake());
        $this->container->set('redis.third', new FakeRedisForSnowflake());

        $generator = $this->container->make(Snowflake::class, [
            'redis' => ['redis.primary', 'redis.secondary', 'redis.third'],
            'prefetch' => 5,
        ]);

        $this->assertIsInt($generator->next());
    }

    public function testSnowflakeMaxShard63ProducesIdsWithMatchingShard(): void
    {
        $generator = $this->container->make(Snowflake::class, [
            'shard' => 63,
            'prefetch' => 5,
        ]);

        $id = $generator->next();
        $parts = $generator->parse($id);

        $this->assertSame(63, $parts['shard']);
    }

    public function testSnowflakeCustomPrefixUsedInRedisSequenceKey(): void
    {
        $fixedSecond = 1577836900;
        $this->container->set(ClockInterface::class, new MockClock((float)$fixedSecond));

        $inner = new FakeRedisForSnowflake();
        $recording = new class ($inner) implements ClientInterface {
            public function __construct(private FakeRedisForSnowflake $inner)
            {
            }

            public ?string $lastIncrKey = null;

            public function getUri(): ?string
            {
                return $this->inner->getUri();
            }

            public function getTransient(): static
            {
                return $this;
            }

            public function incrBy(string $key, int $increment): int
            {
                $this->lastIncrKey = $key;

                return $this->inner->incrBy($key, $increment);
            }

            public function expire(string $key, int $ttl): bool
            {
                return $this->inner->expire($key, $ttl);
            }
        };
        $this->container->set(ClientInterface::class, $recording);

        $generator = $this->container->make(Snowflake::class, [
            'prefix' => 'app_tenant',
            'shard' => 12,
            'jitter' => 0,
            'prefetch' => 10,
        ]);
        $generator->next();

        $this->assertSame("app_tenant:seq:12:0:{$fixedSecond}", $recording->lastIncrKey);
    }

    public function testSnowflakeJitterZeroFirstBatchStartsAtZeroWithoutExtraIncr(): void
    {
        $fixedSecond = 1577837000;
        $this->container->set(ClockInterface::class, new MockClock((float)$fixedSecond));

        $inner = new FakeRedisForSnowflake();
        $recording = new class ($inner) implements ClientInterface {
            public function __construct(private FakeRedisForSnowflake $inner)
            {
            }

            /** @var list<array{key: string, increment: int}> */
            public array $incrCalls = [];

            public function getUri(): ?string
            {
                return $this->inner->getUri();
            }

            public function getTransient(): static
            {
                return $this;
            }

            public function incrBy(string $key, int $increment): int
            {
                $this->incrCalls[] = ['key' => $key, 'increment' => $increment];

                return $this->inner->incrBy($key, $increment);
            }

            public function expire(string $key, int $ttl): bool
            {
                return $this->inner->expire($key, $ttl);
            }
        };
        $this->container->set(ClientInterface::class, $recording);

        $generator = $this->container->make(Snowflake::class, [
            'shard' => 0,
            'jitter' => 0,
            'prefetch' => 7,
        ]);
        $generator->next();

        $this->assertCount(1, $recording->incrCalls);
        $this->assertSame(7, $recording->incrCalls[0]['increment']);
    }
}
