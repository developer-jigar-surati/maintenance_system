<?php

namespace App\Support;

use App\Models\Society;

/**
 * Holds the society the current request is acting within.
 *
 * Every society-owned model reads this through the BelongsToSociety trait to
 * scope its queries, which is what keeps one society's data out of another's.
 * Registered as a singleton, so it lives for exactly one request or one job.
 */
class SocietyContext
{
    protected ?Society $society = null;

    /** Suspends scoping for platform-wide work (super admin, console, jobs). */
    protected bool $suppressed = false;

    public function set(?Society $society): void
    {
        $this->society = $society;
    }

    public function get(): ?Society
    {
        return $this->society;
    }

    public function id(): ?int
    {
        return $this->society?->id;
    }

    public function has(): bool
    {
        return $this->society !== null;
    }

    public function check(): Society
    {
        return $this->society ?? throw new \RuntimeException(
            'No society is active in this context. Use SocietyContext::set() or run inside withSociety().'
        );
    }

    /** True when queries should be filtered by the active society. */
    public function shouldScope(): bool
    {
        return ! $this->suppressed && $this->society !== null;
    }

    /**
     * Runs a callback with scoping disabled, then restores the previous state.
     * Used by super-admin screens and cross-society reporting.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->suppressed;
        $this->suppressed = true;

        try {
            return $callback();
        } finally {
            $this->suppressed = $previous;
        }
    }

    /** Runs a callback as though the given society were active. */
    public function withSociety(Society $society, callable $callback): mixed
    {
        $previousSociety = $this->society;
        $previousSuppressed = $this->suppressed;

        $this->society = $society;
        $this->suppressed = false;

        try {
            return $callback($society);
        } finally {
            $this->society = $previousSociety;
            $this->suppressed = $previousSuppressed;
        }
    }

    public function forget(): void
    {
        $this->society = null;
        $this->suppressed = false;
    }
}
