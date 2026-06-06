<?php

declare(strict_types=1);

namespace Switon\Id;

/**
 * Generates RFC 4122 UUID version 4 strings.
 *
 * Use when you need random IDs and do not require time ordering.
 *
 * @see \Switon\Id\IdGeneratorInterface
 */
class Uuid4 implements IdGeneratorInterface
{
    /**
     * Returns one UUID v4 string.
     *
     * @return string UUID in canonical 36-character format
     */
    public function next(): string
    {
        $bytes = unpack('N1a/n1b/n1c/n1d/n1e/N1f', random_bytes(16));
        return sprintf(
            '%08x-%04x-%04x-%04x-%04x%08x',
            $bytes['a'],
            $bytes['b'],
            ($bytes['c'] & 0x0FFF) | 0x4000,
            ($bytes['d'] & 0x3FFF) | 0x8000,
            $bytes['e'],
            $bytes['f']
        );
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
