<?php

declare(strict_types=1);

namespace Switon\Id\Tests;

use Switon\Id\ServiceProvider;
use Switon\Testing\TestCase as BaseTestCase;

/**
 * Base test case for Id tests.
 *
 * Provides common functionality for all Id tests, including Container initialization
 * with required services registered.
 */
abstract class TestCase extends BaseTestCase
{
    protected string|false|null $providerAutoRegisterEnv = null;

    protected function setUp(): void
    {
        $this->providerAutoRegisterEnv = getenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER');
        putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=1');

        parent::setUp();

        // Many ID tests print sample output for debugging (echo).
        // PHPUnit marks any output as "risky" (beStrictAboutOutputDuringTests=true).
        // Swallow test output unless explicitly requested.
        if (getenv('SWITON_ID_TESTS_ALLOW_OUTPUT') === false) {
            ob_start();
        }
    }

    protected function tearDown(): void
    {
        if (getenv('SWITON_ID_TESTS_ALLOW_OUTPUT') === false) {
            // Discard buffered output
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        parent::tearDown();

        if ($this->providerAutoRegisterEnv === false || $this->providerAutoRegisterEnv === null) {
            putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER');
        } else {
            putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=' . $this->providerAutoRegisterEnv);
        }
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $provider = new ServiceProvider();
        $provider->register($this->container);
    }
}
