<?php

declare(strict_types=1);

namespace Switon\Id\Command;

use Switon\Command\Attribute\Hidden;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Id\Snowflake;

/**
 * Generates and inspects Snowflake IDs from the CLI.
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
     * Generates one or more Snowflake IDs and prints one ID per line.
     *
     * @param int $count Requested number of IDs; values below 1 are normalized to 1.
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
     * Parses one Snowflake ID and prints its decoded fields.
     *
     * @param string $id Snowflake ID to parse
     */
    public function parseAction(string $id): int
    {
        $parts = $this->snowflake->parse($id);
        $this->console->writeLn('timestamp:  ' . $parts['timestamp']);
        $this->console->writeLn('datetime:   ' . $parts['datetime'] . ' UTC');
        $this->console->writeLn('shard:      ' . $parts['shard']);
        $this->console->writeLn('redisIndex: ' . $parts['redisIndex']);
        $this->console->writeLn('sequence:   ' . $parts['sequence']);
        return 0;
    }
}
