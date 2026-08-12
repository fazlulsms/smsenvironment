<?php

namespace App\Support;

use App\Models\BusinessEntity;
use Illuminate\Support\Facades\Session;

/**
 * Central access point for the active business entity.
 *
 * Resolution order: explicitly set id → session → default entity → first active.
 * Registered as a singleton so a request resolves the entity once and every
 * model global scope / service reads the same value.
 */
class CurrentEntity
{
    private ?int $entityId = null;

    private bool $resolved = false;

    private ?BusinessEntity $cached = null;

    public const SESSION_KEY = 'business_entity_id';

    /** Force a specific entity for the remainder of the request (no session write). */
    public function use(?int $entityId): void
    {
        $this->entityId = $entityId;
        $this->resolved = true;
        $this->cached = null;
    }

    /** Persist the selected entity to the session (the entity switcher). */
    public function switchTo(int $entityId): void
    {
        Session::put(self::SESSION_KEY, $entityId);
        $this->use($entityId);
    }

    public function id(): ?int
    {
        if (! $this->resolved) {
            $this->entityId = $this->resolve();
            $this->resolved = true;
        }

        return $this->entityId;
    }

    public function get(): ?BusinessEntity
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        if ($this->cached?->getKey() === $id) {
            return $this->cached;
        }

        return $this->cached = BusinessEntity::query()->find($id);
    }

    public function isSet(): bool
    {
        return $this->id() !== null;
    }

    /** Active entities for the switcher, default first. */
    public function options()
    {
        return BusinessEntity::query()
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function resolve(): ?int
    {
        $sessionId = Session::has(self::SESSION_KEY) ? (int) Session::get(self::SESSION_KEY) : null;

        if ($sessionId && BusinessEntity::query()->where('id', $sessionId)->where('active', true)->exists()) {
            return $sessionId;
        }

        return BusinessEntity::query()
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }
}
