<?php

namespace App\Models;

use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One trainee's shareable link + submission for one quiz. At most one row
 * per (quiz, trainee) — completing it locks the link; the training team
 * deleting the row ("reset") is what allows sending it again.
 *
 * @property int $id
 * @property int $quiz_id
 * @property int $trainee_id
 * @property string $token
 * @property Carbon $sent_at
 * @property Carbon|null $completed_at
 * @property int|null $score
 * @property Carbon|null $wrong_recipient_reported_at
 * @property-read Quiz $quiz
 * @property-read Trainee $trainee
 * @property-read Collection<int, QuizAnswer> $answers
 */
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'quiz_id', 'trainee_id', 'token', 'sent_at', 'completed_at', 'score', 'wrong_recipient_reported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'wrong_recipient_reported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<Trainee, $this>
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    /**
     * @return HasMany<QuizAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Whether the person who opened this link said it wasn't meant for them
     * — a signal the manager sent it to the wrong employee.
     */
    public function isFlaggedAsMisdirected(): bool
    {
        return $this->wrong_recipient_reported_at !== null;
    }
}
