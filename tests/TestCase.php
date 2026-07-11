<?php

declare(strict_types=1);

namespace Switon\Id\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Switon\Core\App;
use Switon\Core\ContextManagerInterface;
use Switon\Core\InjectorInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Eventing\EventDispatcher as RealEventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Id\ServiceProvider;
use Switon\Testing\EventDispatcher as TestEventDispatcher;
use Switon\Testing\InMemoryContextManager;

abstract class TestCase extends BaseTestCase
{
    protected DiContainer $container;
    protected InjectorInterface $injector;

    /** Output-buffer depth this test case opened; restored in tearDown(). */
    protected int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        require_once __DIR__ . '/gmp-function-override.php';

        parent::setUp();

        if (getenv('SWITON_ID_TESTS_ALLOW_OUTPUT') === false) {
            $this->outputBufferLevel = ob_get_level();
            ob_start();
        }

        $this->container = new DiContainer();
        $diProvider = new DiServiceProvider();
        $diProvider->register($this->container);
        $diProvider->boot();

        App::setContainer($this->container);
        $this->container->set(ContextManagerInterface::class, InMemoryContextManager::class);

        $realDispatcher = $this->container->make(RealEventDispatcher::class);
        $eventDispatcher = new TestEventDispatcher($realDispatcher);
        $eventDispatcher->setDispatchEvents(false);
        $this->container->set(EventDispatcherInterface::class, $eventDispatcher);
        $this->container->set(PsrEventDispatcherInterface::class, $eventDispatcher);

        $this->injector = $this->container->get(InjectorInterface::class);
        $this->setUpContainer();
        $this->injector->inject($this);
    }

    protected function tearDown(): void
    {
        if (getenv('SWITON_ID_TESTS_ALLOW_OUTPUT') === false) {
            // Close only the buffers this test opened. A single blind ob_end_clean() lets the
            // depth drift when tested code leaves its own buffer open, which PHPUnit then
            // reports as a risky test (and failOnRisky turns into a failure).
            while (ob_get_level() > $this->outputBufferLevel) {
                ob_end_clean();
            }
        }

        parent::tearDown();
    }

    protected function setUpContainer(): void
    {
        (new ServiceProvider())->register($this->container);
    }

    protected function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }
}
