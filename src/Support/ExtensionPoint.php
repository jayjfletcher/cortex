<?php

declare(strict_types=1);

namespace JayI\Cortex\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use JayI\Cortex\Contracts\ExtensionPointContract;

class ExtensionPoint implements ExtensionPointContract
{
    /**
     * @var Collection<int, mixed>
     */
    protected Collection $extensions;

    public function __construct(
        protected string $pointName,
        protected string $acceptedType,
    ) {
        $this->extensions = new Collection();
    }

    /**
     * Create a new extension point.
     */
    public static function make(string $name, string $accepts): static
    {
        return new static($name, $accepts);
    }

    /**
     * Get the name of this extension point.
     */
    public function name(): string
    {
        return $this->pointName;
    }

    /**
     * Get the interface/class that extensions must implement.
     */
    public function accepts(): string
    {
        return $this->acceptedType;
    }

    /**
     * Register an extension with this extension point.
     *
     * @throws InvalidArgumentException
     */
    public function register(mixed $extension): void
    {
        if (! $extension instanceof $this->acceptedType) {
            throw new InvalidArgumentException(sprintf(
                'Extension must implement %s, %s given',
                $this->acceptedType,
                get_debug_type($extension)
            ));
        }

        $this->extensions->push($extension);
    }

    /**
     * Get all registered extensions.
     *
     * @return Collection<int, mixed>
     */
    public function all(): Collection
    {
        return $this->extensions;
    }
}
