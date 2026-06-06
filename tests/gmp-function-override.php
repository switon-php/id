<?php

declare(strict_types=1);

namespace Switon\Id\Tests\Support {

    final class GmpFunctionOverride
    {
        protected static bool $missing = false;

        public static function forceMissing(bool $missing): void
        {
            self::$missing = $missing;
        }

        public static function isMissing(): bool
        {
            return self::$missing;
        }
    }
}

namespace Switon\Id {

    use Switon\Id\Tests\Support\GmpFunctionOverride;

    function function_exists(string $function): bool
    {
        if ($function === 'gmp_init' && GmpFunctionOverride::isMissing()) {
            return false;
        }

        return \function_exists($function);
    }
}
