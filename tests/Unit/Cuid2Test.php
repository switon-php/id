<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Core\Exception\InvalidArgumentException;
use Switon\Id\Cuid2;
use Switon\Id\Tests\TestCase;

class Cuid2Test extends TestCase
{
    public function testCuid2Generation(): void
    {
        $generator = $this->container->make(Cuid2::class);

        // Generate a few CUID2s
        $cuid2s = [];
        for ($i = 0; $i < 10; $i++) {
            $cuid2s[] = $generator->next();
        }

        // Test basic format
        foreach ($cuid2s as $cuid2) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-zA-Z]{24}$/',
                $cuid2,
                "CUID2 format is invalid: $cuid2"
            );
            $this->assertEquals(24, strlen($cuid2), "CUID2 should be exactly 24 characters: $cuid2");
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($cuid2s), 'Generated CUID2s should be unique');

        // Test only uses allowed characters
        $allowedChars = str_split(Cuid2::DEFAULT_ALPHABET);
        foreach ($cuid2s as $cuid2) {
            $chars = str_split($cuid2);
            foreach ($chars as $char) {
                $this->assertContains($char, $allowedChars, "CUID2 contains invalid character: $char in $cuid2");
            }
        }

        echo "CUID2 Test Results:\n";
        echo "==================\n";
        foreach (array_slice($cuid2s, 0, 3) as $i => $cuid2) {
            echo ($i + 1) . ": $cuid2\n";
        }
        echo "\n";
    }

    public function testCuid2DeterministicGeneration(): void
    {
        $generator = $this->container->make(Cuid2::class);

        // Generate CUID2s and verify they are different
        $cuid21 = $generator->next();
        $cuid22 = $generator->next();

        $this->assertNotEquals($cuid21, $cuid22, 'Consecutive CUID2s should be different');
        $this->assertEquals(Cuid2::DEFAULT_LENGTH, strlen($cuid21), 'First CUID2 should be default length');
        $this->assertEquals(Cuid2::DEFAULT_LENGTH, strlen($cuid22), 'Second CUID2 should be default length');
    }

    public function testCuid2CollisionResistance(): void
    {
        $generator = $this->container->make(Cuid2::class);

        // Generate a larger set to test collision resistance
        $cuid2s = [];
        for ($i = 0; $i < 1000; $i++) {
            $cuid2s[] = $generator->next();
        }

        // Should have no collisions
        $this->assertCount(1000, array_unique($cuid2s), 'Generated CUID2s should have no collisions in 1000 samples');

        echo "CUID2 Collision Test:\n";
        echo "=====================\n";
        echo "Generated 1000 CUID2s, unique count: " . count(array_unique($cuid2s)) . "\n";
        echo "\n";
    }

    public function testCuid2CharacterDistribution(): void
    {
        $generator = $this->container->make(Cuid2::class);

        // Generate multiple CUID2s to test character distribution
        $allChars = '';
        for ($i = 0; $i < 100; $i++) {
            $allChars .= $generator->next();
        }

        // Should use a good variety of characters
        $uniqueChars = count(array_unique(str_split($allChars)));
        $this->assertGreaterThan(30, $uniqueChars, 'CUID2 should use a wide variety of characters');

        // Should not have obvious patterns (basic sanity check)
        $charCounts = array_count_values(str_split($allChars));
        $totalChars = strlen($allChars);
        $maxCount = max($charCounts);

        // Heuristic: no single character should dominate too much.
        // CUID2 includes time/structure components and is not expected to be perfectly uniform.
        $this->assertLessThan(
            $totalChars * 0.20,
            $maxCount,
            'Character distribution should be reasonably even'
        );

        echo "CUID2 Character Distribution Test:\n";
        echo "===================================\n";
        echo "Unique characters used: $uniqueChars\n";
        echo "Total characters analyzed: $totalChars\n";
        echo "\n";
    }

    public function testCuid2Configuration(): void
    {
        // Test default configuration
        $generator = $this->container->make(Cuid2::class);
        $this->assertEquals(Cuid2::DEFAULT_ALPHABET, $generator->getAlphabet());
        $this->assertEquals(Cuid2::DEFAULT_LENGTH, $generator->getLength());

        // Test configuration accessors
        $this->assertIsString($generator->getAlphabet());
        $this->assertIsInt($generator->getLength());
        $this->assertGreaterThan(0, $generator->getLength());
    }

    public function testCuid2NoSpecialCharacters(): void
    {
        $generator = $this->container->make(Cuid2::class);

        // Generate several CUID2s
        $cuid2s = [];
        for ($i = 0; $i < 50; $i++) {
            $cuid2s[] = $generator->next();
        }

        // Ensure no special characters that could cause issues in URLs, filesystems, etc.
        $forbiddenChars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|', ' ', '\t', '\n', '\r'];
        foreach ($cuid2s as $cuid2) {
            foreach ($forbiddenChars as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $cuid2, "CUID2 should not contain forbidden character: $forbidden");
            }
        }

        echo "CUID2 Special Characters Test:\n";
        echo "===============================\n";
        echo "Tested 50 CUID2s for forbidden characters - all passed\n";
        echo "\n";
    }

    public function testCuid2RejectsLengthBelowInjectedPositions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be at least 21');

        $this->container->make(Cuid2::class, ['length' => 20]);
    }

    public function testCuid2RejectsEmptyAlphabet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alphabet must not be empty');

        $this->container->make(Cuid2::class, ['alphabet' => '']);
    }
}
