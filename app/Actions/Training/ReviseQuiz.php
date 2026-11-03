<?php

namespace App\Actions\Training;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviseQuiz
{
    /**
     * Apply an edit to a quiz without ever changing a version that's already
     * been sent. A never-sent version is edited in place. A sent one is
     * frozen: it's retired and its questions and options are copied into the
     * next version, and the edit is applied to that copy instead — so links
     * already shared keep loading exactly what they were sent with, and past
     * answers and scores stay intact. The copy stays editable until someone
     * sends it, so a run of edits only creates one new version.
     *
     * Runs under a row lock on the version so a link can't be sent halfway
     * through an edit (sending takes the same lock).
     *
     * The edit receives the editable version and, when that's a fresh copy, a
     * map of original question id => its copy. Returns the version the edit
     * landed on.
     *
     * @param  Closure(Quiz, array<int, QuizQuestion>): void  $edit
     */
    public function handle(Quiz $quiz, Closure $edit): Quiz
    {
        return DB::transaction(function () use ($quiz, $edit): Quiz {
            $current = Quiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail();

            if ($current->isRetired()) {
                throw ValidationException::withMessages([
                    'prompt' => __('This quiz was updated since you opened it. Refresh the page and try again.'),
                ]);
            }

            if (! $current->isIssued()) {
                $edit($current, []);

                return $current;
            }

            [$next, $copies] = $this->copyToNextVersion($current);
            $current->update(['retired_at' => now()]);

            $edit($next, $copies);

            return $next;
        });
    }

    /**
     * @return array{0: Quiz, 1: array<int, QuizQuestion>}
     */
    private function copyToNextVersion(Quiz $quiz): array
    {
        $next = Quiz::create([
            'section_id' => $quiz->section_id,
            'version' => (int) Quiz::where('section_id', $quiz->section_id)->max('version') + 1,
        ]);

        $copies = [];

        foreach ($quiz->questions()->with('options')->get() as $question) {
            $copy = $next->questions()->create($question->only(['prompt', 'explanation', 'type', 'order']));

            foreach ($question->options as $option) {
                $copy->options()->create($option->only(['text', 'is_correct', 'order']));
            }

            $copies[$question->id] = $copy;
        }

        return [$next, $copies];
    }
}
