<?php

declare(strict_types=1);

namespace Switon\Id\Exception;

use Switon\Id\Exception as BaseException;

/**
 * Exception for ID generators when the sequence backend cannot allocate values.
 *
 * @see \Switon\Id\Snowflake
 */
class SequenceBackendUnavailableException extends BaseException
{
}
