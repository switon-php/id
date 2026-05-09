<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\RandomInterface;
use Switon\Core\Exception\InvalidArgumentException;

/**
 * Generates CUID2-style identifiers with configurable length and alphabet.
 *
 * Use when you need compact, URL-safe IDs with stronger collision resistance than naive random strings.
 *
 * Configure with:
 * - <code>alphabet</code> for allowed characters
 * - <code>length</code> for output size
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see https://github.com/paralleldrive/cuid2
 */
class Cuid2 implements IdGeneratorInterface
{
    protected const TIME_POSITIONS = [2, 7, 12, 17];

    protected const COUNTER_POSITIONS = [5, 10, 15, 20];

    /** Characters allowed in generated IDs. */
    #[Autowired] protected string $alphabet = self::DEFAULT_ALPHABET;

    /** Output length for generated IDs. */
    #[Autowired] protected int $length = self::DEFAULT_LENGTH;

    /** Random source for per-character selection. */
    #[Autowired] protected RandomInterface $random;

    /** Default CUID2 alphabet. */
    public const DEFAULT_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Default output length. */
    public const DEFAULT_LENGTH = 24;

    /** Counter for calls in the same millisecond. */
    protected int $counter = 0;

    /** Last timestamp used for local monotonic ordering. */
    protected int $lastTimestamp = 0;

    public function __construct()
    {
        if ($this->alphabet === '') {
            InvalidArgumentException::raise('Alphabet must not be empty');
        }
        if ($this->length < 21) {
            InvalidArgumentException::raise('Length must be at least 21');
        }
    }

    /**
     * Returns one CUID2-style ID string.
     *
     * @return string CUID2-style ID
     */
    public function next(): string
    {
        $timestamp = (int)(microtime(true) * 1000);

        // Reset counter if timestamp changed
        if ($timestamp !== $this->lastTimestamp) {
            $this->counter = 0;
            $this->lastTimestamp = $timestamp;
        } else {
            $this->counter++;
        }

        // Generate the full random string first
        $result = $this->generateRandomString($this->length);

        // Inject timestamp and counter information into specific positions
        // This provides time-based uniqueness while maintaining randomness
        $result = $this->injectTimeInfo($result, $timestamp, $this->counter);

        return $result;
    }

    /**
     * Builds a random string with the current alphabet.
     */
    protected function generateRandomString(int $length): string
    {
        $result = '';
        $alphabetSize = strlen($this->alphabet);

        for ($i = 0; $i < $length; $i++) {
            $randomByte = $this->random->int(0, 255);
            $result .= $this->alphabet[$randomByte % $alphabetSize];
        }

        return $result;
    }

    /**
     * Mixes timestamp and counter bits into selected character positions.
     */
    protected function injectTimeInfo(string $randomString, int $timestamp, int $counter): string
    {
        $chars = str_split($randomString);
        $alphabetSize = strlen($this->alphabet);

        $timeValue = $timestamp % ($alphabetSize * $alphabetSize * $alphabetSize * $alphabetSize);

        for ($i = 0; $i < count(self::TIME_POSITIONS); $i++) {
            $charIndex = ($timeValue >> ($i * 6)) & 63; // 6 bits per char
            $chars[self::TIME_POSITIONS[$i]] = $this->alphabet[$charIndex % $alphabetSize];
        }

        $counterValue = $counter % ($alphabetSize * $alphabetSize * $alphabetSize * $alphabetSize);

        for ($i = 0; $i < count(self::COUNTER_POSITIONS); $i++) {
            $charIndex = ($counterValue >> ($i * 6)) & 63;
            $chars[self::COUNTER_POSITIONS[$i]] = $this->alphabet[$charIndex % $alphabetSize];
        }

        return implode('', $chars);
    }

    /**
     * Returns the current alphabet.
     */
    public function getAlphabet(): string
    {
        return $this->alphabet;
    }

    /**
     * Returns the configured length.
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /** {@inheritDoc} */
    public function nextN(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->next();
        }
        return $result;
    }
}
