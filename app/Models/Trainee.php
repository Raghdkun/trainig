<?php

namespace App\Models;

use App\Enums\DevelopmentStatus;
use Database\Factories\TraineeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string|null $position
 * @property Carbon|null $hired_at
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property int|null $archived_by
 * @property DevelopmentStatus|null $development_status
 * @property-read Store $store
 * @property-read User|null $creator
 * @property-read User|null $archivedBy
 * @property-read Collection<int, User> $managers
 * @property-read Collection<int, Evaluation> $evaluations
 * @property-read Collection<int, ChecklistItem> $developmentItems
 * @property-read Collection<int, DevelopmentEvaluation> $developmentEvaluations
 * @property-read DevelopmentEvaluation|null $latestDevelopmentEvaluation
 * @property-read Collection<int, QuizAttempt> $quizAttempts
 */
class Trainee extends Model
{
    /** @use HasFactory<TraineeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'store_id', 'name', 'position', 'hired_at', 'created_by',
        'archived_at', 'archived_by', 'development_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
            'archived_at' => 'datetime',
            'development_status' => DevelopmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who archived this trainee (null if it was never archived, or the
     * archiving user has since been removed).
     *
     * @return BelongsTo<User, $this>
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Managers assigned to evaluate this trainee.
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'manager_trainee');
    }

    /**
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * The curated subset of existing checklist items making up this
     * trainee's individualized Development Plan (only meaningful while
     * `development_status` is set). Scoring these uses the same evaluation
     * records as the standard checklist — this is a focused view into it,
     * not a separate kind of progress.
     *
     * @return BelongsToMany<ChecklistItem, $this>
     */
    public function developmentItems(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistItem::class, 'development_plan_items');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Every rubric evaluation submitted for this trainee's time in the
     * Development Zone (usually one per trip through it).
     *
     * @return HasMany<DevelopmentEvaluation, $this>
     */
    public function developmentEvaluations(): HasMany
    {
        return $this->hasMany(DevelopmentEvaluation::class);
    }

    /**
     * The evaluation that most recently brought this trainee into the
     * Development Zone.
     *
     * @return HasOne<DevelopmentEvaluation, $this>
     */
    public function latestDevelopmentEvaluation(): HasOne
    {
        return $this->hasOne(DevelopmentEvaluation::class)->latestOfMany('submitted_at');
    }

    /**
     * Limit the query to trainees the given user is allowed to see. Super admins
     * see everyone; a manager sees every trainee in any of their assigned stores,
     * plus any trainee explicitly assigned to them (the pivot is an additive
     * grant for cross-store cases). A manager with no stores sees only pivot links.
     *
     * @param  Builder<Trainee>  $query
     * @return Builder<Trainee>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $storeIds = $user->stores->pluck('id');

        return $query->where(function (Builder $q) use ($user, $storeIds): void {
            $q->whereHas('managers', fn (Builder $inner) => $inner->whereKey($user->id));

            if ($storeIds->isNotEmpty()) {
                $q->orWhereIn('store_id', $storeIds);
            }
        });
    }

    /**
     * Limit the query to a specific store (no-op when null).
     *
     * @param  Builder<Trainee>  $query
     * @return Builder<Trainee>
     */
    public function scopeInStore(Builder $query, ?int $storeId): Builder
    {
        return $query->when($storeId, fn (Builder $q) => $q->where('store_id', $storeId));
    }

    /**
     * The active roster — everyone not archived. This is the default view
     * everywhere (index, dashboard); archived trainees are opt-in.
     *
     * @param  Builder<Trainee>  $query
     * @return Builder<Trainee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Trainee>  $query
     * @return Builder<Trainee>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Trainees currently in the Development Zone, regardless of status (the
     * Dashboard's Development Zone panel and the Development Zone page).
     *
     * @param  Builder<Trainee>  $query
     * @return Builder<Trainee>
     */
    public function scopeInDevelopmentZone(Builder $query): Builder
    {
        return $query->whereNotNull('development_status');
    }

    /**
     * @param  Builder<Trainee>  $query
     * @return Builder<Trainee>
     */
    public function scopeDevelopmentStatus(Builder $query, DevelopmentStatus $status): Builder
    {
        return $query->where('development_status', $status);
    }
}
