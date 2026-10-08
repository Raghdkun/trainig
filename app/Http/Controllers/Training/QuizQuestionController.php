<?php

namespace App\Http\Controllers\Training;

use App\Actions\Training\ReviseQuiz;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\QuizQuestionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Every edit goes through ReviseQuiz, so a version whose link has already
 * been sent is never changed — the edit lands on a new version instead.
 */
class QuizQuestionController extends Controller
{
    /** A "quick" quiz stays quick — cap it at 5 questions. */
    private const MAX_QUESTIONS = 5;

    public function store(QuizQuestionRequest $request, Quiz $quiz, ReviseQuiz $revise): RedirectResponse
    {
        $version = $revise->handle($quiz, function (Quiz $editable) use ($request): void {
            if ($editable->questions()->count() >= self::MAX_QUESTIONS) {
                throw ValidationException::withMessages([
                    'prompt' => __('A quiz can have at most :max questions.', ['max' => self::MAX_QUESTIONS]),
                ]);
            }

            $question = $editable->questions()->create([
                'prompt' => $request->validated('prompt'),
                'explanation' => $request->validated('explanation'),
                'type' => $request->validated('type'),
                'order' => (int) $editable->questions()->max('order') + 1,
            ]);

            $this->createOptions($question, $request->validated('options'), $request->correctIndexes());
        });

        $this->flashIfNewVersion($quiz, $version);

        return back();
    }

    public function update(QuizQuestionRequest $request, QuizQuestion $question, ReviseQuiz $revise): RedirectResponse
    {
        $version = $revise->handle($question->quiz, function (Quiz $editable, array $copies) use ($request, $question): void {
            $target = $copies[$question->id] ?? $question;

            $target->update([
                'prompt' => $request->validated('prompt'),
                'explanation' => $request->validated('explanation'),
                'type' => $request->validated('type'),
            ]);
            // Safe: this version has never been sent, so no answers point at these options.
            $target->options()->delete();
            $this->createOptions($target, $request->validated('options'), $request->correctIndexes());
        });

        $this->flashIfNewVersion($question->quiz, $version);

        return back();
    }

    public function destroy(QuizQuestion $question, ReviseQuiz $revise): RedirectResponse
    {
        $version = $revise->handle($question->quiz, function (Quiz $editable, array $copies) use ($question): void {
            ($copies[$question->id] ?? $question)->delete();
        });

        $this->flashIfNewVersion($question->quiz, $version);

        return back();
    }

    /**
     * @param  array<int, string>  $options
     * @param  array<int, int>  $correctIndexes
     */
    private function createOptions(QuizQuestion $question, array $options, array $correctIndexes): void
    {
        foreach (array_values($options) as $index => $text) {
            $question->options()->create([
                'text' => $text,
                'is_correct' => in_array($index, $correctIndexes, true),
                'order' => $index,
            ]);
        }
    }

    private function flashIfNewVersion(Quiz $original, Quiz $version): void
    {
        if ($version->is($original)) {
            return;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Saved as version :new. Links already sent keep version :old.', [
                'new' => $version->version,
                'old' => $original->version,
            ]),
        ]);
    }
}
