<?php

namespace App\Models;

use App\Support\AreaAuthorizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PredictiveWorkOrder extends Model
{
    public const CATEGORY_VIBRATION = 'VIBRATION';

    public const CATEGORY_AIR_LEAKED = 'AIR_LEAKED';

    public const CATEGORIES = [
        self::CATEGORY_VIBRATION => 'Vibration',
        self::CATEGORY_AIR_LEAKED => 'Air Leaked',
    ];

    /** Due Date default when none is given: WO date + 14 days (H+14). */
    public const DEFAULT_DUE_DAYS = 14;

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'category',
        'order_number',
        'machine_id',
        'machine_number',
        'area',
        'machine_type',
        'wo_date',
        'problem_description',
        'due_date',
        'status',
        'pic_user_id',
        'claimed_at',
        'closed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pic_user_id' => 'integer',
            'machine_id' => 'integer',
            'wo_date' => 'date',
            'due_date' => 'date',
            'claimed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function defaultDueDate(string $woDate): string
    {
        return Carbon::createFromFormat('Y-m-d', $woDate)->addDays(self::DEFAULT_DUE_DAYS)->toDateString();
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PredictiveAction::class, 'work_order_id')->orderBy('action_number');
    }

    public function reopens(): HasMany
    {
        return $this->hasMany(PredictiveReopen::class, 'work_order_id')->orderBy('reopened_at');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Area visibility only. The PIC column is deliberately not passed to the
     * shared scope: every PIC of the area must see unclaimed WOs in order to
     * claim them.
     */
    public function scopeVisibleToUser(Builder $query, User $user, ?string $adminAreaFilter = null): Builder
    {
        return AreaAuthorizationScope::apply(
            $query,
            $user,
            $query->getModel()->getTable().'.area',
            null,
            $adminAreaFilter,
        );
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->seesAllAreas()) {
            return $user->hasArea($this->area);
        }

        return ($user->isKoordinator() || $user->isPic())
            && $user->area?->name === $this->area;
    }

    /** ADMIN may manage every area, a KOORDINATOR only their own. */
    public function isManageableBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isKoordinator() && $user->area?->name === $this->area;
    }

    public function isClaimedBy(User $user): bool
    {
        return $this->pic_user_id !== null && (int) $this->pic_user_id === (int) $user->id;
    }

    public function canBeClaimedBy(User $user): bool
    {
        return $user->isPic()
            && $this->isVisibleTo($user)
            && $this->isOpen()
            && $this->pic_user_id === null;
    }

    public function canBeActionedBy(User $user): bool
    {
        return $user->isPic()
            && $this->isVisibleTo($user)
            && $this->isOpen()
            && $this->isClaimedBy($user);
    }

    /** Reopen: ADMIN (any area) or the KOORDINATOR of the WO's own area. */
    public function canBeReopenedBy(User $user): bool
    {
        return ($user->isAdmin() || $user->isKoordinator())
            && $this->isVisibleTo($user)
            && $this->isClosed();
    }
}
