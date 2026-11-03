<?php

namespace App\Services\Vme\Ai;

/** What an analysis may look at besides the database: the PIC states the map was built from. */
final class AiContext
{
    /**
     * @param  list<array{user_id: int, status: string, area_id?: int|null}>  $pics
     * @param  int|null  $areaId  set for a character that belongs to one Area (the per-area Koordinator)
     */
    public function __construct(
        public readonly array $pics = [],
        public readonly bool $activitySync = false,
        public readonly int $unmapped = 0,
        public readonly ?int $areaId = null,
        public readonly ?string $areaName = null,
    ) {}

    /** The same context, narrowed to one Area (its PICs only). */
    public function forArea(?int $areaId, ?string $areaName): self
    {
        if ($areaId === null) {
            return $this;
        }

        return new self(
            array_values(array_filter($this->pics, fn (array $p) => ($p['area_id'] ?? null) === $areaId)),
            $this->activitySync,
            $this->unmapped,
            $areaId,
            $areaName,
        );
    }

    public function countStatus(string $status): int
    {
        return count(array_filter($this->pics, fn (array $p) => $p['status'] === $status));
    }
}
