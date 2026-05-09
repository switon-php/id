<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Core\ClockInterface;
use Switon\Id\Tests\TestCase;
use Switon\Id\Uuid7;
use Switon\Testing\MockClock;

class Uuid7Test extends TestCase
{
    public function testUuid7Generation(): void
    {
        $generator = $this->container->make(Uuid7::class);

        // Generate a few UUIDs
        $uuids = [];
        for ($i = 0; $i < 10; $i++) {
            $uuids[] = $generator->next();
        }

        // Test basic format
        foreach ($uuids as $uuid) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $uuid,
                "UUID7 format is invalid: $uuid"
            );
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($uuids), 'Generated UUIDs should be unique');

        // Test version is 7
        foreach ($uuids as $uuid) {
            $parts = explode('-', $uuid);
            $this->assertEquals('7', $parts[2][0], "UUID version should be 7: $uuid");
        }

        // Test variant (should be 8, 9, a, or b)
        foreach ($uuids as $uuid) {
            $parts = explode('-', $uuid);
            $variant = $parts[3][0];
            $this->assertContains($variant, ['8', '9', 'a', 'b'], "UUID variant should be 8-9-a-b: $uuid");
        }
    }

    public function testUuid7TimeOrdering(): void
    {
        $generator = $this->container->make(Uuid7::class);

        // Generate UUIDs in quick succession
        $uuid1 = $generator->next();
        usleep(1000); // 1ms delay
        $uuid2 = $generator->next();

        // Extract timestamp parts (first 8 hex chars = first 32 bits of timestamp)
        $parts1 = explode('-', $uuid1);
        $parts2 = explode('-', $uuid2);

        $timestamp1 = hexdec(substr($parts1[0], 0, 8));
        $timestamp2 = hexdec(substr($parts2[0], 0, 8));

        // UUIDs should be time-ordered (allowing for same millisecond)
        $this->assertGreaterThanOrEqual($timestamp1, $timestamp2,
            'UUID7 should maintain time ordering');
    }

    public function testUuid7Monotonicity(): void
    {
        $generator = $this->container->make(Uuid7::class);

        // Generate multiple UUIDs quickly to test monotonicity
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = $generator->next();
        }

        // Convert to sortable strings for comparison
        $sortable = [];
        foreach ($uuids as $uuid) {
            $sortable[] = str_replace('-', '', $uuid);
        }

        $sorted = $sortable;
        sort($sorted);

        $this->assertSame($sorted, $sortable, 'UUID7 should remain lexicographically monotonic');
    }

    public function testUuid7ClockRollbackPreservesOrdering(): void
    {
        $clock = new MockClock(1000.002);
        $this->container->replace(ClockInterface::class, $clock);
        $generator = $this->container->make(Uuid7::class);

        $first = $generator->next();
        $clock->setTime(1000.001);
        $second = $generator->next();

        $this->assertGreaterThan($first, $second, 'UUID7 should remain ordered when the clock moves backward');
    }

    public function testUuid7SequenceOverflowAdvancesLogicalTimestamp(): void
    {
        $clock = new MockClock(1000.001);
        $this->container->replace(ClockInterface::class, $clock);
        $generator = $this->container->make(Uuid7::class);

        $ids = [];
        for ($i = 0; $i < 4097; $i++) {
            $ids[] = $generator->next();
        }

        $this->assertGreaterThan(
            $ids[4095],
            $ids[4096],
            'UUID7 should remain ordered after exhausting the 12-bit same-millisecond sequence'
        );
    }
}
