<?php

namespace App\Services\Vme\Ai;

use Carbon\CarbonInterface;

final class AiAnalysisResult
{
    public const WORKING = 'WORKING';

    public const ALERT = 'ALERT';

    public const IDLE = 'IDLE';

    public const STATUSES = [self::WORKING, self::ALERT, self::IDLE];

    /**
     * @param  array<string, mixed>  $input  compact description of what was read
     * @param  list<array{label: string, value: string}>  $details  human-readable result lines
     * @param  array{type: string, id: int}|null  $sourceRef  e.g. ['type' => 'ai_forecast_runs', 'id' => 12]
     */
    public function __construct(
        public readonly string $status,
        public readonly string $summary,
        public readonly array $input = [],
        public readonly array $details = [],
        public readonly ?array $sourceRef = null,
        public readonly ?CarbonInterface $startedAt = null,
        public readonly ?CarbonInterface $finishedAt = null,
    ) {}

    /** Stable fingerprint of the OUTCOME (not of when it was computed). */
    public function hash(): string
    {
        return sha1(json_encode([$this->status, $this->summary, $this->details, $this->sourceRef]));
    }
}
