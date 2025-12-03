<?php

namespace X2nx\WebmanAnnotation\Injector;

/**
 * LazyInjectProxy
 *
 * Lazy loading proxy for #[Inject(lazy: true)]:
 * - Resolves real service from container only on first access
 * - Returns null and logs error on resolution failure
 *
 * Note: To avoid type mismatch, it's recommended to use lazy loading only on properties without specific type declarations,
 * e.g.: `#[Inject(lazy: true)] protected $service;`
 */
class LazyInjectProxy
{
    private ?object $instance = null;
    private bool $resolved = false;

    public function __construct(
        private readonly string $serviceId,
        private readonly ?string $hintClass = null
    ) {
    }

    /**
     * Get real instance (resolve only once)
     */
    protected function resolve(): ?object
    {
        if ($this->resolved) {
            return $this->instance;
        }
        $this->resolved = true;

        $container = DependencyInjector::getContainer();
        if (!$container) {
            DependencyInjector::logError('webman-annotation: Lazy proxy container not available', [
                'id' => $this->serviceId,
                'hint' => $this->hintClass,
            ]);
            return null;
        }

        try {
            if (method_exists($container, 'get')) {
                $this->instance = $container->get($this->serviceId);
            } elseif (method_exists($container, 'make')) {
                $this->instance = $container->make($this->serviceId);
            }
        } catch (\Throwable $e) {
            DependencyInjector::logError('webman-annotation: Lazy proxy resolve failed', [
                'id' => $this->serviceId,
                'hint' => $this->hintClass,
                'error' => $e->getMessage(),
            ]);
            $this->instance = null;
        }

        return $this->instance;
    }

    public function __call(string $name, array $arguments)
    {
        $target = $this->resolve();
        if (!$target) {
            return null;
        }
        return $target->$name(...$arguments);
    }

    public function __get(string $name)
    {
        $target = $this->resolve();
        if (!$target) {
            return null;
        }
        return $target->$name;
    }

    public function __set(string $name, $value): void
    {
        $target = $this->resolve();
        if ($target) {
            $target->$name = $value;
        }
    }

    public function __isset(string $name): bool
    {
        $target = $this->resolve();
        return $target ? isset($target->$name) : false;
    }

    public function __invoke(...$args)
    {
        $target = $this->resolve();
        if (!$target || !is_callable($target)) {
            return null;
        }
        return $target(...$args);
    }
}


