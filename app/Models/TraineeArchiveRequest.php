<?php

namespace App\Models;

use App\Enums\ArchiveRequestStatus;
use Database\Factories\TraineeArchiveRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A store manager's request to move a trainee from the active roster to the
 * Archive. The trainee stays active until a super admin reviews it.
 *
 * @property int $id
 * @property int $trainee_id
 * @property int|null $requested_by
 * @property string $reason
 * @property ArchiveRequestStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon $created_at
 * @property-read Trainee $trainee
 * @property-read User|null $requester
 * @property-read User|null $reviewer
 */
class TraineeArchiveRequest extends Model
{
    /** @use HasFactory<TraineeArchiveRequestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'trainee_id', 'requested_by', 'reason', 'status',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ArchiveRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trainee, $this>
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<TraineeArchiveRequest>  $query
     * @return Builder<TraineeArchiveRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ArchiveRequestStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === ArchiveRequestStatus::Pending;
    }
}
