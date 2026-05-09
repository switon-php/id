<?php

declare(strict_types=1);

namespace Switon\Id;

use Switon\Core\Attribute\Autowired;
use Switon\Core\RandomInterface;
use Switon\Core\Exception\InvalidArgumentException;

/**
 * Generates short URL-safe IDs with a configurable alphabet and length.
 *
 * Use when you need compact IDs for URLs, tokens, or public-facing identifiers.
 *
 * Configure with:
 * - <code>alphabet</code> for allowed characters
 * - <code>length</code> for output size
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see https://github.com/ai/nanoid
 */
class NanoId implements IdGeneratorInterface
{
    /** Characters allowed in generated IDs. */
    #[Autowired] protected string $alphabet = self::DEFAULT_ALPHABET;

    /** Output length for generated IDs. */
    #[Autowired] protected int $length = self::DEFAULT_LENGTH;

    /** Random source for cryptographically secure bytes. */
    #[Autowired] protected RandomInterface $random;

    /** Default URL-safe alphabet. */
    public const DEFAULT_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-';

    /** Default output length. */
    public const DEFAULT_LENGTH = 21;

    /** Cached alphabet length. */
    protected int $alphabetSize;

    public function __construct()
    {
        if ($this->alphabet === '') {
            InvalidArgumentException::raise('Alphabet must not be empty');
        }
        if ($this->length < 1) {
            InvalidArgumentException::raise('Length must be at least 1');
        }
        $this->alphabetSize = strlen($this->alphabet);
    }

    /**
     * Returns one NanoID string.
     *
     * Uses rejection sampling to reduce modulo bias.
     *
     * @return string NanoID string
     */
    public function next(): string
    {
        $result = '';
        $mask = (2 << (int)log($this->alphabetSize - 1, 2)) - 1;
        $step = (int)ceil(1.6 * $mask * $this->length / $this->alphabetSize);

        while (strlen($result) < $this->length) {
            for ($i = 0; $i < $step; $i++) {
                $randomByte = $this->random->int(0, 255);
                $randomIndex = $randomByte & $mask;

                // Rejection sampling: only use if index is within alphabet size
                if ($randomIndex < $this->alphabetSize) {
                    $result .= $this->alphabet[$randomIndex];
                    if (strlen($result) === $this->length) {
                        break;
                    }
                }
            }
        }

        return $result;
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

    /**
     * Set configuration for testing purposes.
     *
     * @internal This method is for testing only
     */
    public function setTestConfig(string $alphabet, int $length): void
    {
        $this->alphabet = $alphabet;
        $this->length = $length;
        $this->alphabetSize = strlen($alphabet);
    }
}
