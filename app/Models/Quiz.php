<?php

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One immutable version of a station's short comprehension check. A section
 * has at most one live version (`retired_at` null); once a version's link has
 * been sent it's never edited again — edits create the next version, so old
 * links and results keep exactly the questions they were sent with.
 *
 * @property int $id
 * @property int $section_id
 * @property int $version
 * @property Carbon|null $retired_at
 * @property-read Section $section
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read Collection<int, QuizAttempt> $attempts
 */
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['section_id', 'version', 'retired_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * A version is issued once any link to it exists — from then on it's
     * frozen and edits go to a new version.
     */
    public function isIssued(): bool
    {
        return $this->attempts()->exists();
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /**
     * @param  Builder<Quiz>  $query
     * @return Builder<Quiz>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }
}
