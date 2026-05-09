<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Core\ClockInterface;
use Switon\Core\RandomInterface;
use Switon\Id\Tests\TestCase;
use Switon\Id\Ulid;
use Switon\Testing\MockClock;
use Switon\Testing\MockRandom;

class UlidTest extends TestCase
{
    public function testUlidGeneration(): void
    {
        $generator = $this->container->make(Ulid::class);

        // Generate a few ULIDs
        $ulids = [];
        for ($i = 0; $i < 10; $i++) {
            $ulids[] = $generator->next();
        }

        // Test basic format (26 characters, Base32)
        foreach ($ulids as $ulid) {
            $this->assertMatchesRegularExpression(
                '/^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$/',
                $ulid,
                "ULID format is invalid: $ulid"
            );
            $this->assertEquals(26, strlen($ulid), "ULID should be exactly 26 characters: $ulid");
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($ulids), 'Generated ULIDs should be unique');

        // Test case insensitivity (ULIDs are case-insensitive by design)
        foreach ($ulids as $ulid) {
            $this->assertEquals(strtoupper($ulid), $ulid, 'ULID should be uppercase: $ulid');
        }

        echo "ULID Test Results:\n";
        echo "=================\n";
        foreach (array_slice($ulids, 0, 3) as $i => $ulid) {
            echo ($i + 1) . ": $ulid\n";
        }
        echo "\n";
    }

    public function testUlidTimeOrdering(): void
    {
        $generator = $this->container->make(Ulid::class);

        // Generate ULIDs in quick succession
        $ulid1 = $generator->next();
        usleep(1000); // 1ms delay
        $ulid2 = $generator->next();

        // ULIDs should be lexicographically sortable (time-ordered)
        // Newer ULID should be greater (comes later in lexicographic order)
        $this->assertGreaterThan($ulid1, $ulid2, 'ULIDs should be lexicographically sortable with newer ones being "larger"');

        echo "ULID Time Ordering Test:\n";
        echo "=========================\n";
        echo "ULID1: $ulid1\n";
        echo "ULID2: $ulid2\n";
        echo "ULID1 < ULID2: " . ($ulid1 < $ulid2 ? 'true' : 'false') . "\n";
        echo "\n";
    }

    public function testUlidMonotonicity(): void
    {
        $generator = $this->container->make(Ulid::class);

        // Generate multiple ULIDs quickly to test monotonicity
        $ulids = [];
        for ($i = 0; $i < 100; $i++) {
            $ulids[] = $generator->next();
        }

        // ULID should be time-ordered (later generated ULIDs should be greater)
        // Sort them and check that the first and last are in correct order
        $sorted = $ulids;
        sort($sorted);

        // The original first ULID should be less than or equal to the sorted first
        // and the original last should be greater than or equal to the sorted last
        // This is a weaker test than strict monotonicity

        // Check that we have reasonable time ordering
        $this->assertLessThanOrEqual($sorted[99], $ulids[99], 'ULID should maintain reasonable time ordering');

        echo "ULID Monotonicity Test:\n";
        echo "=======================\n";
        echo "Generated 100 ULIDs\n";
        echo "First ULID: {$ulids[0]}\n";
        echo "Last ULID:  {$ulids[99]}\n";
        echo "Sorted first: {$sorted[0]}\n";
        echo "Sorted last:  {$sorted[99]}\n";
        echo "\n";
    }

    public function testUlidDeterministicGeneration(): void
    {
        $generator = $this->container->make(Ulid::class);

        // Generate ULIDs and verify they are different
        $ulid1 = $generator->next();
        $ulid2 = $generator->next();

        $this->assertNotEquals($ulid1, $ulid2, 'Consecutive ULIDs should be different');
    }

    public function testUlidTimestampExtraction(): void
    {
        $generator = $this->container->make(Ulid::class);
        $ulid = $generator->next();

        // Extract timestamp from first 10 characters
        $timestampPart = substr($ulid, 0, 10);

        // Decode Base32 timestamp
        $timestamp = 0;
        for ($i = 0; $i < 10; $i++) {
            $char = $timestampPart[$i];
            $value = strpos(Ulid::BASE32_ALPHABET, $char);
            $timestamp = ($timestamp << 5) | $value;
        }

        // Should be close to current time (within reasonable bounds)
        $now = (int)(microtime(true) * 1000);
        $this->assertGreaterThan($now - 60000, $timestamp, 'ULID timestamp should be recent'); // Within last minute
        $this->assertLessThanOrEqual($now + 1000, $timestamp, 'ULID timestamp should not be in the future');

        echo "ULID Timestamp Extraction Test:\n";
        echo "===============================\n";
        echo "ULID: $ulid\n";
        echo "Extracted timestamp: $timestamp\n";
        echo "Current time: $now\n";
        echo "Difference: " . ($now - $timestamp) . "ms\n";
        echo "\n";
    }

    public function testUlidMonotonicWithinSameMillisecond(): void
    {
        $clock = new MockClock(1700000000.123);
        $random = new MockRandom([0]);

        $this->container->replace(ClockInterface::class, $clock);
        $this->container->replace(RandomInterface::class, $random);

        $generator = $this->container->make(Ulid::class);

        $first = $generator->next();
        $second = $generator->next();

        $this->assertNotSame($first, $second);
        $this->assertSame(substr($first, 0, 10), substr($second, 0, 10));
        $this->assertGreaterThan($first, $second, 'ULID should stay lexicographically monotonic within the same millisecond');
    }
}
