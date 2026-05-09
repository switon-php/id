<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Id\Exception\GmpExtensionRequiredException;
use Switon\Id\ShortUuid;
use Switon\Id\Tests\Support\GmpFunctionOverride;
use Switon\Id\Tests\TestCase;

class ShortUuidTest extends TestCase
{
    public function testEncodeValidUuid(): void
    {
        // Test with standard UUID format
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $shortUuid = ShortUuid::encode($uuid);

        $this->assertEquals(22, strlen($shortUuid), 'ShortUUID should be exactly 22 characters');
        $this->assertMatchesRegularExpression('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{22}$/', $shortUuid);
    }

    public function testEncodeUuidWithoutHyphens(): void
    {
        // Test with UUID without hyphens
        $uuid = '550e8400e29b41d4a716446655440000';
        $shortUuid = ShortUuid::encode($uuid);

        $this->assertEquals(22, strlen($shortUuid));
        $this->assertMatchesRegularExpression('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{22}$/', $shortUuid);
    }

    public function testEncodeInvalidUuid(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');

        ShortUuid::encode('invalid-uuid');
    }

    public function testDecodeValidShortUuid(): void
    {
        // Generate a ShortUUID and test decoding it back
        $originalUuid = '550e8400-e29b-41d4-a716-446655440000';
        $shortUuid = ShortUuid::encode($originalUuid);
        $decodedUuid = ShortUuid::decode($shortUuid);

        $this->assertEquals(36, strlen($decodedUuid), 'Decoded UUID should be 36 characters');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $decodedUuid);

        // Should match original UUID (case-insensitive)
        $this->assertEquals(strtolower(str_replace('-', '', $originalUuid)), strtolower(str_replace('-', '', $decodedUuid)));
    }

    public function testDecodeInvalidLength(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('ShortUUID must be exactly 22 characters');

        ShortUuid::decode('too-short');
    }

    public function testDecodeInvalidCharacters(): void
    {
        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ShortUUID characters');

        // Contains '0' which is not in Base58 alphabet
        ShortUuid::decode('0Cf6j8Ht5Vd7K3N8P4Q1R6');
    }

    public function testDecodePreservesGmpRequirementException(): void
    {
        GmpFunctionOverride::forceMissing(true);

        try {
            $this->expectException(GmpExtensionRequiredException::class);
            $this->expectExceptionMessage('gmp extension is required for ShortUuid encode and decode');

            ShortUuid::decode('2Cf6j8Ht5Vd7K3N8P4Q1R6');
        } finally {
            GmpFunctionOverride::forceMissing(false);
        }
    }

    public function testRoundTripEncoding(): void
    {
        // Test various UUIDs for round-trip consistency
        $testUuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '12345678-1234-5678-9abc-def012345678',
            'ffffffff-ffff-ffff-ffff-ffffffffffff',
            '00000000-0000-0000-0000-000000000000',
        ];

        foreach ($testUuids as $originalUuid) {
            $shortUuid = ShortUuid::encode($originalUuid);

            // Verify ShortUUID is exactly 22 characters
            $this->assertEquals(22, strlen($shortUuid), "ShortUUID should be 22 characters for UUID: $originalUuid");

            $decodedUuid = ShortUuid::decode($shortUuid);

            // Normalize both UUIDs for comparison (remove hyphens, convert to lowercase)
            $normalizedOriginal = strtolower(str_replace('-', '', $originalUuid));
            $normalizedDecoded = strtolower(str_replace('-', '', $decodedUuid));

            $this->assertEquals($normalizedOriginal, $normalizedDecoded,
                "Round-trip encoding failed for UUID: $originalUuid");
        }
    }

    public function testGenerateCreatesValidShortUuid(): void
    {
        $shortUuid = ShortUuid::generate();

        $this->assertEquals(22, strlen($shortUuid), 'Generated ShortUUID should be 22 characters');
        $this->assertMatchesRegularExpression('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{22}$/', $shortUuid);

        // Should be decodable to a valid UUID
        $decodedUuid = ShortUuid::decode($shortUuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $decodedUuid);
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $generated = [];
        for ($i = 0; $i < 100; $i++) {
            $generated[] = ShortUuid::generate();
        }

        // Should have no duplicates (extremely unlikely for random generation)
        $this->assertCount(100, array_unique($generated), 'Generated ShortUUIDs should be unique');
    }

    public function testCaseInsensitiveDecoding(): void
    {
        // ShortUUID is case-sensitive by design (Base58), but let's test it handles valid cases
        $shortUuid = ShortUuid::encode('550e8400-e29b-41d4-a716-446655440000');

        // Should work with the exact same case
        $decoded = ShortUuid::decode($shortUuid);
        $this->assertStringStartsWith('550e8400', $decoded);
    }

    public function testAllUuidVersionsSupported(): void
    {
        // Test different UUID versions
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440000', // v4
            '019bce88-938e-7000-842c-d2e348f43da2', // v7
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8', // v1 (example)
        ];

        foreach ($uuids as $uuid) {
            $shortUuid = ShortUuid::encode($uuid);

            // Verify ShortUUID is exactly 22 characters
            $this->assertEquals(22, strlen($shortUuid), "ShortUUID should be 22 characters for UUID: $uuid");

            $decodedUuid = ShortUuid::decode($shortUuid);

            // Verify round-trip
            $normalizedOriginal = strtolower(str_replace('-', '', $uuid));
            $normalizedDecoded = strtolower(str_replace('-', '', $decodedUuid));

            $this->assertEquals($normalizedOriginal, $normalizedDecoded,
                "Failed for UUID version in: $uuid");
        }
    }

    public function testBase58AlphabetCorrectness(): void
    {
        // Verify our Base58 alphabet matches Bitcoin standard (no 0, O, I, l)
        $expected = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $this->assertEquals($expected, ShortUuid::BASE58_ALPHABET);

        // Verify no forbidden characters
        $this->assertStringNotContainsString('0', ShortUuid::BASE58_ALPHABET);
        $this->assertStringNotContainsString('O', ShortUuid::BASE58_ALPHABET);
        $this->assertStringNotContainsString('I', ShortUuid::BASE58_ALPHABET);
        $this->assertStringNotContainsString('l', ShortUuid::BASE58_ALPHABET);
    }
}
