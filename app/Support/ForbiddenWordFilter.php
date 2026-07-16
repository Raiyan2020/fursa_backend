<?php

namespace App\Support;

use App\Models\ForbiddenWord;

class ForbiddenWordFilter
{
    /**
     * @return string[]
     */
    public static function detect(?string ...$texts): array
    {
        $words = ForbiddenWord::query()->notDeleted()->get(['word_en', 'word_ar']);
        if ($words->isEmpty()) {
            return [];
        }

        $haystack = mb_strtolower(implode(' ', array_filter($texts)));
        if ($haystack === '') {
            return [];
        }

        $detected = [];
        foreach ($words as $word) {
            foreach ([$word->word_en, $word->word_ar] as $candidate) {
                if ($candidate && mb_stripos($haystack, mb_strtolower($candidate)) !== false) {
                    $detected[] = $candidate;
                }
            }
        }

        return array_values(array_unique($detected));
    }
}
