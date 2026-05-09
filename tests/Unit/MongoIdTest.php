<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Id\MongoId;
use Switon\Id\Tests\TestCase;

class MongoIdTest extends TestCase
{
    public function testMongoIdGeneration(): void
    {
        $generator = $this->container->make(MongoId::class);

        // Generate a few MongoIds
        $mongoIds = [];
        for ($i = 0; $i < 10; $i++) {
            $mongoIds[] = $generator->next();
        }

        // Test basic format
        foreach ($mongoIds as $mongoId) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{24}$/',
                $mongoId,
                "MongoId format is invalid: $mongoId"
            );
            $this->assertEquals(24, strlen($mongoId), "MongoId should be exactly 24 characters: $mongoId");
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($mongoIds), 'Generated MongoIds should be unique');

        // Test only uses hex characters (0-9a-f)
        foreach ($mongoIds as $mongoId) {
            $this->assertTrue(ctype_xdigit($mongoId), "MongoId should only contain hex characters: $mongoId");
            $this->assertTrue($generator->isValid($mongoId), "MongoId validation should pass: $mongoId");
        }

        echo "MongoId Test Results:\n";
        echo "===================\n";
        foreach (array_slice($mongoIds, 0, 3) as $i => $mongoId) {
            echo ($i + 1) . ": $mongoId\n";
        }
        echo "\n";
    }

    public function testMongoIdDeterministicGeneration(): void
    {
        $generator = $this->container->make(MongoId::class);

        // Generate MongoIds and verify they are different
        $mongoId1 = $generator->next();
        $mongoId2 = $generator->next();

        $this->assertNotEquals($mongoId1, $mongoId2, 'Consecutive MongoIds should be different');
        $this->assertEquals(24, strlen($mongoId1), 'First MongoId should be 24 chars');
        $this->assertEquals(24, strlen($mongoId2), 'Second MongoId should be 24 chars');
    }

    public function testMongoIdCollisionResistance(): void
    {
        $generator = $this->container->make(MongoId::class);

        // Generate a larger set to test collision resistance
        $mongoIds = [];
        for ($i = 0; $i < 1000; $i++) {
            $mongoIds[] = $generator->next();
        }

        // Should have no collisions
        $this->assertCount(1000, array_unique($mongoIds), 'Generated MongoIds should have no collisions in 1000 samples');

        echo "MongoId Collision Test:\n";
        echo "======================\n";
        echo "Generated 1000 MongoIds, unique count: " . count(array_unique($mongoIds)) . "\n";
        echo "\n";
    }

    public function testMongoIdTimestampExtraction(): void
    {
        $generator = $this->container->make(MongoId::class);

        // Generate MongoId and test timestamp extraction
        $beforeTime = time();
        $mongoId = $generator->next();
        $afterTime = time();

        $extractedTimestamp = $generator->extractTimestamp($mongoId);

        // Should be close to current time (within reasonable bounds)
        $this->assertGreaterThanOrEqual($beforeTime, $extractedTimestamp, 'MongoId timestamp should be at or after generation start');
        $this->assertLessThanOrEqual($afterTime, $extractedTimestamp, 'MongoId timestamp should be at or before generation end');

        echo "MongoId Timestamp Extraction Test:\n";
        echo "==================================\n";
        echo "MongoId: $mongoId\n";
        echo "Extracted timestamp: $extractedTimestamp\n";
        echo "Current time: " . time() . "\n";
        echo "Difference: " . (time() - $extractedTimestamp) . " seconds\n";
        echo "\n";
    }

    public function testMongoIdValidation(): void
    {
        $generator = $this->container->make(MongoId::class);

        // Valid MongoIds
        $validIds = [];
        for ($i = 0; $i < 5; $i++) {
            $validIds[] = $generator->next();
        }

        foreach ($validIds as $mongoId) {
            $this->assertTrue($generator->isValid($mongoId), "Valid MongoId should pass validation: $mongoId");
        }

        // Invalid MongoIds
        $invalidIds = [
            '', // empty
            '123', // too short
            '123456789012345678901234567890', // too long
            'gggggggggggggggggggggggg', // invalid chars
            '12345678901234567890123g', // mixed valid/invalid
            '123456789012345678901234567890123456789012345678901234567890', // way too long
        ];

        foreach ($invalidIds as $invalidId) {
            $this->assertFalse($generator->isValid($invalidId), "Invalid MongoId should fail validation: $invalidId");
        }
    }

    public function testMongoIdStructure(): void
    {
        $generator = $this->container->make(MongoId::class);

        // Generate a few MongoIds and verify they have the expected structure
        $mongoIds = [];
        for ($i = 0; $i < 10; $i++) {
            $mongoIds[] = $generator->next();
        }

        foreach ($mongoIds as $mongoId) {
            // First 8 characters should represent a reasonable timestamp
            $timestampHex = substr($mongoId, 0, 8);
            $timestamp = hexdec($timestampHex);

            // Should be a reasonable Unix timestamp (after 2020, before 2030)
            $this->assertGreaterThan(1577836800, $timestamp, "MongoId timestamp should be after 2020: $mongoId");
            $this->assertLessThan(1893456000, $timestamp, "MongoId timestamp should be before 2030: $mongoId");

            // The rest should be valid hex
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', substr($mongoId, 8), "MongoId rest should be valid hex: $mongoId");
        }

        echo "MongoId Structure Test:\n";
        echo "========================\n";
        echo "All MongoIds have valid timestamp and hex structure\n";
        echo "\n";
    }

    public function testMongoIdExtractTimestampInvalidLength(): void
    {
        // Arrange
        $generator = $this->container->make(MongoId::class);

        // Act - Test with invalid lengths
        $result1 = $generator->extractTimestamp('');
        $result2 = $generator->extractTimestamp('tooshort');
        $result3 = $generator->extractTimestamp('12345678901234567890123456789');

        // Assert
        $this->assertEquals(0, $result1, 'Empty string should return 0');
        $this->assertEquals(0, $result2, 'Short string should return 0');
        $this->assertEquals(0, $result3, 'Long string should return 0');
    }

    public function testMongoIdExtractTimestampValidFormat(): void
    {
        // Arrange
        $generator = $this->container->make(MongoId::class);
        $beforeTime = time();

        // Act - Generate MongoId and extract timestamp
        $mongoId = $generator->next();
        $extractedTime = $generator->extractTimestamp($mongoId);
        $afterTime = time();

        // Assert - Extracted timestamp should be within reasonable range
        $this->assertGreaterThanOrEqual($beforeTime, $extractedTime, 'Extracted timestamp should be >= generation start time');
        $this->assertLessThanOrEqual($afterTime, $extractedTime, 'Extracted timestamp should be <= generation end time');
    }
}
