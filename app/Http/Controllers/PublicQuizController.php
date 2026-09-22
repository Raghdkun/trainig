<?php

namespace App\Http\Controllers;

use App\Enums\QuizQuestionType;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee-facing quiz — reached via an unguessable token link, no
 * account or login involved. Deliberately outside the `auth` middleware.
 */
class PublicQuizController extends Controller
{
    public function show(string $token): Response
    {
        $attempt = QuizAttempt::where('token', $token)->firstOrFail();
        $attempt->load('quiz.questions.options', 'trainee:id,name');

        return Inertia::render('quiz/show', [
            'token' => $token,
            'traineeName' => $attempt->trainee->name,
            'sectionTitle' => $attempt->quiz->section?->title,
            'completed' => $attempt->isCompleted(),
            'flaggedAsMismatch' => $attempt->isFlaggedAsMisdirected(),
            'questions' => $attempt->isCompleted()
                ? []
                : $attempt->quiz->questions->map(fn (QuizQuestion $question): array => [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'type' => $question->type->value,
                    'options' => $question->options->map(fn ($option): array => [
                        'id' => $option->id,
                        'text' => $option->text,
                    ])->values(),
                ])->values(),
        ]);
    }

    /**
     * The person opening this link said it isn't meant for them — flag it so
     * the training team can tell the manager sent it to the wrong employee.
     * Doesn't touch completion/score; the link stays valid for whoever it
     * was actually meant for.
     */
    public function reportMismatch(string $token): RedirectResponse
    {
        $attempt = QuizAttempt::where('token', $token)->firstOrFail();
        $attempt->update(['wrong_recipient_reported_at' => now()]);

        return to_route('quiz.show', $token);
    }

    /**
     * Every question submits as an array of chosen option ids, whether it's
     * single- or multi-answer — single questions are just constrained to
     * exactly one. A question is only counted correct if the chosen set is
     * an exact match for the option(s) actually marked correct.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $attempt = QuizAttempt::where('token', $token)->whereNull('completed_at')->firstOrFail();
        $attempt->load('quiz.questions.options');

        $rules = [];
        foreach ($attempt->quiz->questions as $question) {
            $rules["answers.{$question->id}"] = [
                'required',
                'array',
                $question->type === QuizQuestionType::Single ? 'size:1' : 'min:1',
            ];
            $rules["answers.{$question->id}.*"] = ['distinct', Rule::in($question->options->pluck('id'))];
        }

        $data = $request->validate($rules);

        $correct = 0;
        $total = $attempt->quiz->questions->count();

        DB::transaction(function () use ($attempt, $data, &$correct): void {
            foreach ($attempt->quiz->questions as $question) {
                $chosenIds = collect($data['answers'][$question->id])->map(fn ($id): int => (int) $id)->sort()->values();
                $correctIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values();

                if ($chosenIds->all() === $correctIds->all()) {
                    $correct++;
                }

                foreach ($chosenIds as $optionId) {
                    QuizAnswer::create([
                        'quiz_attempt_id' => $attempt->id,
                        'quiz_question_id' => $question->id,
                        'quiz_question_option_id' => $optionId,
                    ]);
                }
            }
        });

        $attempt->update([
            'completed_at' => now(),
            'score' => $total > 0 ? (int) round($correct / $total * 100) : 0,
        ]);

        return to_route('quiz.show', $token);
    }
}
