<?php

namespace App\Services\Vme;

use App\Models\JobType;
use App\Models\JobTypeAlias;
use Throwable;

/**
 * Maps an activity to a job type key. Fixed sources (PM, Greasing, Oil Audit,
 * Oil Audit Action) have a fixed key; Manual Activity names are matched
 * against job_type_aliases (exact first, then the alias as a whole word or
 * phrase inside the text, longest alias wins). Never throws: the Today Activity resolver calls this, so any
 * failure degrades to the generic 'manual' key.
 */
class JobTypeResolver
{
    public const SOURCE_KEYS = [
        'PM' => JobType::KEY_PM,
        'GREASING' => JobType::KEY_GREASING,
        'OIL_AUDIT' => JobType::KEY_OIL_AUDIT,
        'OIL_AUDIT_ACTION' => JobType::KEY_OIL_AUDIT_ACTION,
    ];

    /** @var list<array{alias: string, key: string}>|null */
    private ?array $aliases = null;

    public function keyFor(string $source, ?string $name = null): string
    {
        if (isset(self::SOURCE_KEYS[$source])) {
            return self::SOURCE_KEYS[$source];
        }

        if ($name === null || trim($name) === '') {
            return JobType::KEY_MANUAL;
        }

        $normalized = JobType::normalize($name);
        $best = null;

        foreach ($this->aliases() as $row) {
            if ($row['alias'] === $normalized) {
                return $row['key'];
            }

            // The alias as a whole word / phrase inside the text ("pm" matches
            // "PM mesin 3" but not "pump"); the longest alias wins.
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($row['alias'], '/').'(?![\p{L}\p{N}])/u', $normalized)
                && ($best === null || strlen($row['alias']) > strlen($best['alias']))) {
                $best = $row;
            }
        }

        return $best['key'] ?? JobType::KEY_MANUAL;
    }

    /** @return list<array{alias: string, key: string}> */
    private function aliases(): array
    {
        if ($this->aliases !== null) {
            return $this->aliases;
        }

        try {
            $this->aliases = JobTypeAlias::query()
                ->join('job_types', 'job_types.id', '=', 'job_type_aliases.job_type_id')
                ->where('job_types.is_active', true)
                ->get(['job_type_aliases.alias', 'job_types.key'])
                ->map(fn ($r) => ['alias' => (string) $r->alias, 'key' => (string) $r->key])
                ->all();
        } catch (Throwable) {
            $this->aliases = [];
        }

        return $this->aliases;
    }
}
