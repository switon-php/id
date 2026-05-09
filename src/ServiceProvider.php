<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\ContainerInterface;
use Switon\Core\ServiceProviderInterface;

/**
 * Registers named ID generator bindings via IdGeneratorInterface#name.
 *
 * Road-signs:
 * - IdGeneratorInterface#default|snowflake|uuid4|uuid7
 * - IdGeneratorInterface#ulid|nanoId|ksuid|mongoId|cuid2
 *
 * @see \Switon\Core\ServiceProviderInterface
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(ContainerInterface $container): void
    {
        $type = IdGeneratorInterface::class;

        $container->set($type . '#snowflake', Snowflake::class);
        $container->set($type . '#uuid4', Uuid4::class);
        $container->set($type . '#uuid7', Uuid7::class);
        $container->set($type . '#ulid', Ulid::class);
        $container->set($type . '#nanoId', NanoId::class);
        $container->set($type . '#ksuid', Ksuid::class);
        $container->set($type . '#mongoId', MongoId::class);
        $container->set($type . '#cuid2', Cuid2::class);

        $container->set($type . '#default', '#uuid4');
        $container->set($type, '#default');
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        // ID services don't require boot logic
    }
}
