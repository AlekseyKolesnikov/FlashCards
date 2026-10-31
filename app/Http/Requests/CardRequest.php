<?php

namespace App\Http\Requests;

use App\Services\Vocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Card fields from SPECIFICATION.md §4.1, plus the duplicate rule from §4.3.
 */
class CardRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $complexity = config('flashcards.complexity');

        return [
            'lang1_id' => ['required', 'integer', 'exists:languages,id'],
            'word1' => ['required', 'string', 'max:255'],
            'notes1' => ['nullable', 'string', 'max:255'],
            'examples1' => ['nullable', 'string', 'max:2000'],
            'lang2_id' => ['required', 'integer', 'exists:languages,id', 'different:lang1_id'],
            'word2' => ['required', 'string', 'max:255'],
            'notes2' => ['nullable', 'string', 'max:255'],
            'examples2' => ['nullable', 'string', 'max:2000'],
            'complexity' => ['required', 'integer', "between:{$complexity['min']},{$complexity['max']}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lang1_id' => 'language 1', 'word1' => 'word 1', 'notes1' => 'notes 1', 'examples1' => 'examples 1',
            'lang2_id' => 'language 2', 'word2' => 'word 2', 'notes2' => 'notes 2', 'examples2' => 'examples 2',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $duplicate = app(Vocabulary::class)->findDuplicate(
                    $this->only('lang1_id', 'word1', 'lang2_id', 'word2'),
                    $this->route('card')?->id,
                );

                if ($duplicate) {
                    $validator->errors()->add('word1', 'This card already exists.');
                }
            },
        ];
    }
}
