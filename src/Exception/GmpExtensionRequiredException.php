<?php

declare(strict_types=1);

namespace Switon\Id\Exception;

use Switon\Id\Exception as BaseException;

/**
 * Exception for KSUID or ShortUuid usage when ext-gmp is unavailable.
 *
 * @see \Switon\Id\Ksuid
 * @see \Switon\Id\ShortUuid
 */
class GmpExtensionRequiredException extends BaseException
{
}
