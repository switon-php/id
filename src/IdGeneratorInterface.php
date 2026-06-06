<?php

declare(strict_types=1);

namespace Switon\Id;

/**
 * Defines a shared contract for ID generators.
 *
 * Guidance:
 * - inject this contract when callers only need "next ID" semantics
 * - resolve named bindings when a specific format is required
 *
 * @see \Switon\Id\Snowflake
 * @see \Switon\Id\Uuid4
 * @see \Switon\Id\Uuid7
 * @see \Switon\Id\Ulid
 * @see \Switon\Id\NanoId
 * @see \Switon\Id\Ksuid
 * @see \Switon\Id\MongoId
 * @see \Switon\Id\Cuid2
 * @see \Switon\Http\Filter\RequestIdFilter Typical consumer
 * @see \Switon\Orm\IdGenerator Typical consumer
 */
interface IdGeneratorInterface
{
    /**
     * Returns the next generated ID in the implementation's native format.
     *
     * @return int|string The next generated ID
     */
    public function next(): int|string;

    /**
     * Returns multiple generated IDs using the same strategy.
     *
     * @param positive-int $count Number of IDs to generate
     *
     * @return list<int|string> List of generated IDs
     */
    public function nextN(int $count): array;
}
