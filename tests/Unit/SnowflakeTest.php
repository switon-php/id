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

class SnowflakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->set(ClientInterface::class, new FakeRedisForSnowflake());
    }

    public function testSnowflakeInvalidShard(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shard must be between 0 and 63');

        $this->container->make(Snowflake::class, ['shard' => 64]);
    }

    public function testSnowflakeInvalidShardNegative(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shard must be between 0 and 63');

        $this->container->make(Snowflake::class, ['shard' => -1]);
    }

    public function testSnowflakeInvalidPrefetchTooLow(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefetch must be between 1 and 1000');

        $this->container->make(Snowflake::class, ['prefetch' => 0]);
    }

    public function testSnowflakeInvalidPrefetchTooHigh(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefetch must be between 1 and 1000');

        $this->container->make(Snowflake::class, ['prefetch' => 1001]);
    }

    public function testSnowflakeInvalidJitterNegative(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jitter must be between 0 and');

        $this->container->make(Snowflake::class, ['jitter' => -1]);
    }

    public function testSnowflakeInvalidJitterTooHigh(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jitter must be between 0 and');

        $this->container->make(Snowflake::class, ['jitter' => 16_777_216]);
    }

    public function testSnowflakeNextNInvalidCountZero(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Count must be between 1 and');

        $generator = $this->container->make(Snowflake::class);
        $generator->nextN(0);
    }

    public function testSnowflakeNextNInvalidCountTooHigh(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Count must be between 1 and');

        $generator = $this->container->make(Snowflake::class);
        $generator->nextN(16_777_216);
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

        $generator = $this->container->make(Snowflake::class, ['shard' => 0, 'prefetch' => 10]);
        $ts = $oldSecond - $epoch;
        $oldSequenceStart = 123;
        $oldRemaining = 3;
        $oldBase = (($ts & 0xFFFFFFFF) << 31) | (0 << 25) | (0 << 24);

        $set = function (string $name, mixed $value) use ($generator): void {
            $p = new \ReflectionProperty(Snowflake::class, $name);
            $p->setValue($generator, $value);
        };
        $set('base', $oldBase);
        $set('sequence', $oldSequenceStart);
        $set('remaining', $oldRemaining);
        $set('second', $oldSecond);

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
        $failingRedis = new class implements ClientInterface {
            public function getUri(): ?string
            {
                return null;
            }

            public function incrBy(string $key, int $increment): int
            {
                throw new \RuntimeException('redis down');
            }

            public function expire(string $key, int $ttl): bool
            {
                throw new \RuntimeException('redis down');
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
        $primary = new class implements ClientInterface {
            public function getUri(): ?string
            {
                return 'redis://primary';
            }

            public function incrBy(string $key, int $increment): int
            {
                throw new \RuntimeException('primary unavailable');
            }

            public function expire(string $key, int $ttl): bool
            {
                throw new \RuntimeException('primary unavailable');
            }
        };
        $secondary = new FakeRedisForSnowflake();

        $this->container->set('redis.primary', $primary);
        $this->container->set('redis.secondary', $secondary);

        $generator = $this->container->make(Snowflake::class, ['prefetch' => 10]);
        $redisProperty = new \ReflectionProperty(Snowflake::class, 'redis');
        $redisProperty->setValue($generator, [$primary, $secondary]);

        $id = (int)$generator->next();
        $parts = $generator->parse($id);

        $this->assertSame(1, $parts['redisIndex']);
    }
}
