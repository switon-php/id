<?php

declare(strict_types=1);

namespace Switon\Id\Command;

use Switon\Core\Attribute\Autowired;
use Switon\Command\Attribute\Hidden;
use Switon\Core\ConsoleInterface;
use Switon\Id\Snowflake;

/**
 * Generate and parse Snowflake IDs
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see \Switon\Id\Snowflake
 */
#[Hidden]
class IdCommand
{
    #[Autowired] protected ConsoleInterface $console;
    #[Autowired] protected Snowflake $snowflake;

    /**
     * Generate one or more Snowflake IDs, one per line
     *
     * @param int $count Number of IDs to generate (min 1)
     */
    public function generateAction(int $count = 1): int
    {
        if ($count < 1) {
            $count = 1;
        }
        $ids = $this->snowflake->nextN($count);
        foreach ($ids as $id) {
            $this->console->writeLn((string)$id);
        }
        return 0;
    }

    /**
     * Parse one Snowflake ID and print its fields
     *
     * @param string $id Snowflake ID to parse
     */
    public function parseAction(string $id): int
    {
        $id = (int)$id;
        $parts = $this->snowflake->parse($id);
        $this->console->writeLn('timestamp:  ' . $parts['timestamp']);
        $this->console->writeLn('datetime:   ' . $parts['datetime'] . ' UTC');
        $this->console->writeLn('shard:      ' . $parts['shard']);
        $this->console->writeLn('redisIndex: ' . $parts['redisIndex']);
        $this->console->writeLn('sequence:   ' . $parts['sequence']);
        return 0;
    }
}
