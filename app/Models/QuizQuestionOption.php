<?php

namespace App\Models;

use Database\Factories\QuizQuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quiz_question_id
 * @property string $text
 * @property bool $is_correct
 * @property int $order
 * @property-read QuizQuestion $question
 */
class QuizQuestionOption extends Model
{
    /** @use HasFactory<QuizQuestionOptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['quiz_question_id', 'text', 'is_correct', 'order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<QuizQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
