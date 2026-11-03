<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quiz_attempt_id
 * @property int $quiz_question_id
 * @property int $quiz_question_option_id
 * @property-read QuizAttempt $attempt
 * @property-read QuizQuestion $question
 * @property-read QuizQuestionOption $option
 */
class QuizAnswer extends Model
{
    /** @var list<string> */
    protected $fillable = ['quiz_attempt_id', 'quiz_question_id', 'quiz_question_option_id'];

    /**
     * @return BelongsTo<QuizAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * @return BelongsTo<QuizQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    /**
     * @return BelongsTo<QuizQuestionOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionOption::class, 'quiz_question_option_id');
    }
}
