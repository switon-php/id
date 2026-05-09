<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Switon\Core\ConsoleInterface;
use Switon\Id\Command\IdCommand;
use Switon\Id\Snowflake;

#[AllowMockObjectsWithoutExpectations]
class IdCommandTest extends TestCase
{
    protected IdCommand $command;
    protected ConsoleInterface&MockObject $console;
    protected Snowflake&MockObject $snowflake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new IdCommand();
        $this->console = $this->createMock(ConsoleInterface::class);
        $this->snowflake = $this->createMock(Snowflake::class);

        $this->injectProperty($this->command, 'console', $this->console);
        $this->injectProperty($this->command, 'snowflake', $this->snowflake);
    }

    public function testGenerateActionWritesEachGeneratedId(): void
    {
        $this->snowflake->expects($this->once())
            ->method('nextN')
            ->with(3)
            ->willReturn([101, 202, 303]);

        $lines = [];
        $this->console->expects($this->exactly(3))
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $code = $this->command->generateAction(3);

        $this->assertSame(0, $code);
        $this->assertSame(['101', '202', '303'], $lines);
    }

    public function testGenerateActionNormalizesNonPositiveCountToOne(): void
    {
        $this->snowflake->expects($this->once())
            ->method('nextN')
            ->with(1)
            ->willReturn([777]);

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with('777');

        $code = $this->command->generateAction(0);

        $this->assertSame(0, $code);
    }

    public function testGenerateActionNormalizesNegativeCountToOne(): void
    {
        $this->snowflake->expects($this->once())
            ->method('nextN')
            ->with(1)
            ->willReturn([999]);

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with('999');

        $code = $this->command->generateAction(-5);

        $this->assertSame(0, $code);
    }

    public function testParseActionPrintsStructuredFields(): void
    {
        $this->snowflake->expects($this->once())
            ->method('parse')
            ->with(42)
            ->willReturn([
                'timestamp' => 1700000000,
                'datetime' => '2023-11-14 22:13:20',
                'shard' => 7,
                'redisIndex' => 1,
                'sequence' => 99,
            ]);

        $lines = [];
        $this->console->expects($this->exactly(5))
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $code = $this->command->parseAction('42');

        $this->assertSame(0, $code);
        $this->assertSame([
            'timestamp:  1700000000',
            'datetime:   2023-11-14 22:13:20 UTC',
            'shard:      7',
            'redisIndex: 1',
            'sequence:   99',
        ], $lines);
    }

    public function testParseActionCastsStringInputToIntBeforeDelegating(): void
    {
        $this->snowflake->expects($this->once())
            ->method('parse')
            ->with(42)
            ->willReturn([
                'timestamp' => 1,
                'datetime' => '1970-01-01 00:00:01',
                'shard' => 0,
                'redisIndex' => 0,
                'sequence' => 1,
            ]);

        $this->console->expects($this->exactly(5))->method('writeLn');

        $code = $this->command->parseAction('42abc');

        $this->assertSame(0, $code);
    }

    private function injectProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setValue($object, $value);
    }
}
