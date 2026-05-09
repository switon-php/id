<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Core\Exception\InvalidArgumentException;
use Switon\Id\NanoId;
use Switon\Id\Tests\TestCase;

class NanoIdTest extends TestCase
{
    public function testNanoIdGeneration(): void
    {
        $generator = $this->container->make(NanoId::class);

        // Generate a few NanoIDs
        $nanoIds = [];
        for ($i = 0; $i < 10; $i++) {
            $nanoIds[] = $generator->next();
        }

        // Test basic format
        foreach ($nanoIds as $nanoId) {
            $this->assertMatchesRegularExpression(
                '/^[0-9A-Za-z_-]{21}$/',
                $nanoId,
                "NanoID format is invalid: $nanoId"
            );
            $this->assertEquals(21, strlen($nanoId), "NanoID should be exactly 21 characters: $nanoId");
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($nanoIds), 'Generated NanoIDs should be unique');

        // Test only uses allowed characters
        $allowedChars = str_split(NanoId::DEFAULT_ALPHABET);
        foreach ($nanoIds as $nanoId) {
            $chars = str_split($nanoId);
            foreach ($chars as $char) {
                $this->assertContains($char, $allowedChars, "NanoID contains invalid character: $char in $nanoId");
            }
        }

        echo "NanoID Test Results:\n";
        echo "===================\n";
        foreach (array_slice($nanoIds, 0, 3) as $i => $nanoId) {
            echo ($i + 1) . ": $nanoId\n";
        }
        echo "\n";
    }

    public function testNanoIdCustomConfiguration(): void
    {
        // Test custom configuration via dependency injection
        $customGenerator = $this->container->make(NanoId::class);
        // For testing purposes, we'll manually set the properties
        // In real usage, these would be configured via #[Autowired]
        $customGenerator->setTestConfig('ABC123', 8);

        $nanoId = $customGenerator->next();
        $this->assertEquals(8, strlen($nanoId), "NanoID should be exactly 8 characters");
        $this->assertMatchesRegularExpression(
            '/^[A-C1-3]{8}$/',
            $nanoId,
            "NanoID with custom config format is invalid: $nanoId"
        );

        echo "NanoID Custom Configuration Test:\n";
        echo "=================================\n";
        echo "Custom alphabet 'ABC123', length 8: $nanoId\n";
        echo "\n";
    }


    public function testNanoIdDeterministicGeneration(): void
    {
        $generator = $this->container->make(NanoId::class);

        // Generate NanoIDs and verify they are different
        $nanoId1 = $generator->next();
        $nanoId2 = $generator->next();

        $this->assertNotEquals($nanoId1, $nanoId2, 'Consecutive NanoIDs should be different');
    }

    public function testNanoIdConfiguration(): void
    {
        // Test default configuration
        $generator = $this->container->make(NanoId::class);
        $this->assertEquals(NanoId::DEFAULT_ALPHABET, $generator->getAlphabet());
        $this->assertEquals(NanoId::DEFAULT_LENGTH, $generator->getLength());

        // Test configuration accessors
        $this->assertIsString($generator->getAlphabet());
        $this->assertIsInt($generator->getLength());
        $this->assertGreaterThan(0, $generator->getLength());
    }

    public function testNanoIdCollisionResistance(): void
    {
        $generator = $this->container->make(NanoId::class);

        // Generate a larger set to test collision resistance
        $nanoIds = [];
        for ($i = 0; $i < 1000; $i++) {
            $nanoIds[] = $generator->next();
        }

        // Should have no collisions
        $this->assertCount(1000, array_unique($nanoIds), 'Generated NanoIDs should have no collisions in 1000 samples');

        echo "NanoID Collision Test:\n";
        echo "=====================\n";
        echo "Generated 1000 NanoIDs, unique count: " . count(array_unique($nanoIds)) . "\n";
        echo "\n";
    }

    public function testNanoIdRejectsEmptyAlphabet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alphabet must not be empty');

        $this->container->make(NanoId::class, ['alphabet' => '']);
    }

    public function testNanoIdRejectsNonPositiveLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be at least 1');

        $this->container->make(NanoId::class, ['length' => 0]);
    }
}
