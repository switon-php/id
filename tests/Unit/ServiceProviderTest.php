<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use Switon\Id\Cuid2;
use Switon\Id\IdGeneratorInterface;
use Switon\Id\Ksuid;
use Switon\Id\MongoId;
use Switon\Id\NanoId;
use Switon\Id\ServiceProvider;
use Switon\Id\Snowflake;
use Switon\Id\Tests\TestCase;
use Switon\Id\Ulid;
use Switon\Id\Uuid4;
use Switon\Id\Uuid7;

class ServiceProviderTest extends TestCase
{
    public function testServiceProviderRegistersAllGenerators(): void
    {
        // Arrange
        $provider = new ServiceProvider();

        // Act - Boot the provider (should do nothing but we test it)
        $provider->boot();

        // Assert - Test that all named generators can be resolved
        $snowflake = $this->container->get(IdGeneratorInterface::class . '#snowflake');
        $uuid4 = $this->container->get(IdGeneratorInterface::class . '#uuid4');
        $uuid7 = $this->container->get(IdGeneratorInterface::class . '#uuid7');
        $ulid = $this->container->get(IdGeneratorInterface::class . '#ulid');
        $nanoId = $this->container->get(IdGeneratorInterface::class . '#nanoId');
        $ksuid = $this->container->get(IdGeneratorInterface::class . '#ksuid');
        $mongoId = $this->container->get(IdGeneratorInterface::class . '#mongoId');
        $cuid2 = $this->container->get(IdGeneratorInterface::class . '#cuid2');

        $this->assertInstanceOf(Snowflake::class, $snowflake);
        $this->assertInstanceOf(Uuid4::class, $uuid4);
        $this->assertInstanceOf(Uuid7::class, $uuid7);
        $this->assertInstanceOf(Ulid::class, $ulid);
        $this->assertInstanceOf(NanoId::class, $nanoId);
        $this->assertInstanceOf(Ksuid::class, $ksuid);
        $this->assertInstanceOf(MongoId::class, $mongoId);
        $this->assertInstanceOf(Cuid2::class, $cuid2);
    }

    public function testServiceProviderDefaultGenerator(): void
    {
        // Arrange & Act - Resolve default generator (should be uuid4)
        $defaultGenerator = $this->container->get(IdGeneratorInterface::class);

        // Assert
        $this->assertInstanceOf(Uuid4::class, $defaultGenerator);
    }

    public function testAllGeneratorsProduceValidIds(): void
    {
        // Arrange
        $generators = [
            'snowflake' => $this->container->get(IdGeneratorInterface::class . '#snowflake'),
            'uuid4' => $this->container->get(IdGeneratorInterface::class . '#uuid4'),
            'uuid7' => $this->container->get(IdGeneratorInterface::class . '#uuid7'),
            'ulid' => $this->container->get(IdGeneratorInterface::class . '#ulid'),
            'nanoId' => $this->container->get(IdGeneratorInterface::class . '#nanoId'),
            'ksuid' => $this->container->get(IdGeneratorInterface::class . '#ksuid'),
            'mongoId' => $this->container->get(IdGeneratorInterface::class . '#mongoId'),
            'cuid2' => $this->container->get(IdGeneratorInterface::class . '#cuid2'),
        ];

        // Act & Assert
        foreach ($generators as $name => $generator) {
            try {
                $id = $generator->next();
            } catch (\Throwable $e) {
                if (
                    $name === 'snowflake'
                    && (
                        $e::class === 'Switon\\Pooling\\Exception\\PoolNotFoundException'
                        || $e instanceof \Switon\Id\Exception\SequenceBackendUnavailableException
                    )
                ) {
                    $this->markTestSkipped('Snowflake requires Redis pool');
                }
                throw $e;
            }
            $this->assertTrue(
                is_string($id) || is_int($id),
                "Generator '$name' should produce string or int ID, got: " . gettype($id)
            );
            $this->assertNotEmpty($id, "Generator '$name' should produce non-empty ID");
        }
    }
}
