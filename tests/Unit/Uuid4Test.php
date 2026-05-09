<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Id\Tests\TestCase;
use Switon\Id\Uuid4;

class Uuid4Test extends TestCase
{
    public function testUuid4Generation(): void
    {
        $generator = $this->container->make(Uuid4::class);

        // Generate a few UUIDs
        $uuids = [];
        for ($i = 0; $i < 10; $i++) {
            $uuids[] = $generator->next();
        }

        // Test basic format
        foreach ($uuids as $uuid) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $uuid,
                "UUID4 format is invalid: $uuid"
            );
        }

        // Test uniqueness
        $this->assertCount(10, array_unique($uuids), 'Generated UUIDs should be unique');

        // Test version is 4
        foreach ($uuids as $uuid) {
            $parts = explode('-', $uuid);
            $this->assertEquals('4', $parts[2][0], "UUID version should be 4: $uuid");
        }

        // Test variant (should be 8, 9, a, or b)
        foreach ($uuids as $uuid) {
            $parts = explode('-', $uuid);
            $variant = $parts[3][0];
            $this->assertContains($variant, ['8', '9', 'a', 'b'], "UUID variant should be 8-9-a-b: $uuid");
        }

        echo "UUID4 Test Results:\n";
        echo "==================\n";
        foreach (array_slice($uuids, 0, 3) as $i => $uuid) {
            echo ($i + 1) . ": $uuid\n";
        }
        echo "\n";
    }

    public function testUuid4DeterministicGeneration(): void
    {
        $generator = $this->container->make(Uuid4::class);

        // Generate UUIDs and verify they are different
        $uuid1 = $generator->next();
        $uuid2 = $generator->next();

        $this->assertNotEquals($uuid1, $uuid2, 'Consecutive UUIDs should be different');
    }
}