<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Id\Exception\GmpExtensionRequiredException;
use Switon\Id\Ksuid;
use Switon\Id\Tests\Support\GmpFunctionOverride;
use Switon\Id\Tests\TestCase;

class KsuidTest extends TestCase
{
    public function testKsuidNextThrowsWhenGmpExtensionMissing(): void
    {
        GmpFunctionOverride::forceMissing(true);

        try {
            $generator = $this->container->make(Ksuid::class);

            $this->expectException(GmpExtensionRequiredException::class);
            $this->expectExceptionMessage('gmp extension is required for KSUID generation and parsing');
            $generator->next();
        } finally {
            GmpFunctionOverride::forceMissing(false);
        }
    }

    public function testKsuidExtractTimestampThrowsWhenGmpExtensionMissing(): void
    {
        GmpFunctionOverride::forceMissing(true);

        try {
            $generator = $this->container->make(Ksuid::class);

            $this->expectException(GmpExtensionRequiredException::class);
            $this->expectExceptionMessage('gmp extension is required for KSUID generation and parsing');
            $generator->extractTimestamp('111111111111111111111111111');
        } finally {
            GmpFunctionOverride::forceMissing(false);
        }
    }

    public function testKsuidGeneration(): void
    {
        $generator = $this->container->make(Ksuid::class);

        // Generate a few KSUIDs
        $ksuids = [];
        for ($i = 0; $i < 10; $i++) {
            $ksuids[] = $generator->next();
        }

        // Test basic format
        foreach ($ksuids as $ksuid) {
            $this->assertMatchesRegularExpression(
                '/^[0-9A-Za-z]{27}$/',
                $ksuid,
                "KSUID format is invalid: $ksuid"
            );
            $this->assertEquals(27, strlen($ksuid), "KSUID should be exactly 27 characters: $ksuid");
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($ksuids), 'Generated KSUIDs should be unique');

        // Test only uses Base62 characters
        $allowedChars = str_split(Ksuid::BASE62_ALPHABET);
        foreach ($ksuids as $ksuid) {
            $chars = str_split($ksuid);
            foreach ($chars as $char) {
                $this->assertContains($char, $allowedChars, "KSUID contains invalid character: $char in $ksuid");
            }
        }

        echo "KSUID Test Results:\n";
        echo "==================\n";
        foreach (array_slice($ksuids, 0, 3) as $i => $ksuid) {
            echo ($i + 1) . ": $ksuid\n";
        }
        echo "\n";
    }

    public function testKsuidTimeOrdering(): void
    {
        $generator = $this->container->make(Ksuid::class);

        // Generate multiple KSUIDs to check general time ordering trend
        $ksuids = [];
        for ($i = 0; $i < 10; $i++) {
            $ksuids[] = $generator->next();
            usleep(1000); // Small delay between generations
        }

        // Sort the KSUIDs and check that they maintain reasonable ordering
        $sorted = $ksuids;
        sort($sorted);

        // At least some KSUIDs should be in order (allowing for randomness)
        $inOrder = 0;
        for ($i = 0; $i < count($ksuids) - 1; $i++) {
            if ($ksuids[$i] <= $ksuids[$i + 1]) {
                $inOrder++;
            }
        }

        // Should have some ordering (at least 30% in order, due to randomness)
        $this->assertGreaterThanOrEqual(3, $inOrder, 'KSUIDs should show some time-based ordering');

        echo "KSUID Time Ordering Test:\n";
        echo "=========================\n";
        echo "Generated 10 KSUIDs, $inOrder consecutive pairs are in order\n";
        echo "First KSUID: {$ksuids[0]}\n";
        echo "Last KSUID:  {$ksuids[9]}\n";
        echo "\n";
    }

    public function testKsuidTimestampExtraction(): void
    {
        $generator = $this->container->make(Ksuid::class);
        $ksuid = $generator->next();

        $extractedTimestamp = $generator->extractTimestamp($ksuid);

        // Should return a reasonable timestamp (not zero or negative)
        $this->assertIsInt($extractedTimestamp, 'Extracted timestamp should be an integer');
        $this->assertGreaterThan(0, $extractedTimestamp, 'Extracted timestamp should be positive');

        echo "KSUID Timestamp Extraction Test:\n";
        echo "===============================\n";
        echo "KSUID: $ksuid\n";
        echo "Extracted timestamp: $extractedTimestamp\n";
        echo "Current time: " . time() . "\n";
        echo "\n";
    }

    public function testKsuidDeterministicGeneration(): void
    {
        $generator = $this->container->make(Ksuid::class);

        // Generate KSUIDs and verify they are different
        $ksuid1 = $generator->next();
        $ksuid2 = $generator->next();

        $this->assertNotEquals($ksuid1, $ksuid2, 'Consecutive KSUIDs should be different');
    }

    public function testKsuidCollisionResistance(): void
    {
        $generator = $this->container->make(Ksuid::class);

        // Generate a larger set to test collision resistance
        $ksuids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ksuids[] = $generator->next();
        }

        // Should have no collisions
        $this->assertCount(1000, array_unique($ksuids), 'Generated KSUIDs should have no collisions in 1000 samples');

        echo "KSUID Collision Test:\n";
        echo "=====================\n";
        echo "Generated 1000 KSUIDs, unique count: " . count(array_unique($ksuids)) . "\n";
        echo "\n";
    }

    public function testKsuidBase62Encoding(): void
    {
        $generator = $this->container->make(Ksuid::class);

        // Test that KSUID only contains Base62 characters
        $ksuid = $generator->next();
        $base62Pattern = '/^[' . preg_quote(Ksuid::BASE62_ALPHABET, '/') . ']{27}$/';

        $this->assertMatchesRegularExpression($base62Pattern, $ksuid, 'KSUID should only contain Base62 characters');

        // Test that we can decode and get reasonable data
        $timestamp = $generator->extractTimestamp($ksuid);
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testKsuidExtractTimestampInvalidLength(): void
    {
        // Arrange
        $generator = $this->container->make(Ksuid::class);

        // Act - Test with invalid length KSUID
        $result = $generator->extractTimestamp('tooshort');

        // Assert
        $this->assertEquals(0, $result, 'Invalid KSUID length should return 0');
    }

    public function testKsuidExtractTimestampEmptyString(): void
    {
        // Arrange
        $generator = $this->container->make(Ksuid::class);

        // Act - Test with empty string
        $result = $generator->extractTimestamp('');

        // Assert
        $this->assertEquals(0, $result, 'Empty string should return 0');
    }

    public function testKsuidExtractTimestampValid(): void
    {
        // Arrange
        $generator = $this->container->make(Ksuid::class);
        $beforeTime = time();

        // Act - Generate KSUID and extract timestamp
        $ksuid = $generator->next();
        $extractedTime = $generator->extractTimestamp($ksuid);
        $afterTime = time();

        // Assert - Extracted timestamp should be within reasonable range
        $this->assertGreaterThanOrEqual($beforeTime, $extractedTime, 'Extracted timestamp should be >= generation start time');
        $this->assertLessThanOrEqual($afterTime + 1, $extractedTime, 'Extracted timestamp should be <= generation end time + 1 sec');
    }
}
