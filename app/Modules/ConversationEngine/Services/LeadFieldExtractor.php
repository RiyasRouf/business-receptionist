<?php

namespace App\Modules\ConversationEngine\Services;

use App\Modules\ConversationEngine\ValueObjects\LeadField;

/**
 * Deterministic, zero-AI-call field extraction — no LLM round-trip per
 * field (token/latency budget), and no risk of an LLM "helpfully"
 * inventing a value. Pulls ONLY what the caller actually said; returns
 * null (never guessed) when the utterance doesn't contain a usable
 * value for the field being asked, so the caller is re-prompted instead
 * of having noise/off-topic speech silently stored as their name/phone/etc.
 */
class LeadFieldExtractor
{
    private const NUMBER_WORDS = [
        'zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14,
        'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18,
    ];

    private const NAME_PREFIXES = '/^\s*(my (son|daughter|child|kid)(\'s name)? is|my (son|daughter|child|kid)|my name is|it is|it\'s|i am|i\'m|this is|call me|the name is|name is|his name is|her name is)\s+/i';

    // Non-answers STT often produces when the caller hesitates — these
    // must never be stored as a name.
    private const FILLER = '/\b(hmm+|uh+|um+|let me (see|think|check)|not sure|i don\'t know|dunno|wait|hold on|one (sec|moment)|maybe|actually)\b/i';

    public function extract(LeadField $field, string $raw): ?string
    {
        $input = trim($raw);

        return match ($field) {
            LeadField::ParentName, LeadField::ChildName => $this->extractName($input),
            LeadField::ParentPhone, LeadField::AlternatePhone => $this->extractPhone($input),
            LeadField::ParentEmail => $this->extractEmail($input),
            LeadField::ChildAge => $this->extractAge($input),
            LeadField::GradeApplyingFor => $this->extractGrade($input),
            default => $this->extractFreeText($input),
        };
    }

    private function extractName(string $input): ?string
    {
        if (preg_match(self::FILLER, $input)) {
            return null; // hesitation / non-answer
        }

        $cleaned = trim((string) preg_replace(self::NAME_PREFIXES, '', $input));
        $cleaned = rtrim($cleaned, " .!\t\n\r");

        // Real names are 1-4 words, letters/spaces/hyphens/apostrophes
        // only — reject sentences, questions, digits.
        if ($cleaned === '' || str_word_count($cleaned) > 4 || str_contains($cleaned, '?')
            || ! preg_match("/^[\p{L}][\p{L}\s'.-]*$/u", $cleaned)) {
            return null;
        }

        return ucwords(strtolower($cleaned));
    }

    private function extractPhone(string $input): ?string
    {
        // Spoken digits sometimes come with separators ("052 421 7979");
        // strip everything but leading + and digits, then require a
        // plausible phone length.
        $digits = preg_replace('/[^0-9+]/', '', $input);

        return ($digits !== '' && strlen(preg_replace('/\D/', '', $digits)) >= 7) ? $digits : null;
    }

    private function extractEmail(string $input): ?string
    {
        // STT commonly renders "@"/"." as spoken words.
        $normalised = strtolower($input);
        $normalised = preg_replace('/\s+at\s+/', '@', $normalised);
        $normalised = preg_replace('/\s+dot\s+/', '.', $normalised);
        $normalised = preg_replace('/\s+/', '', $normalised);

        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', $normalised, $m)) {
            return filter_var($m[0], FILTER_VALIDATE_EMAIL) ?: null;
        }

        return null;
    }

    private function extractAge(string $input): ?string
    {
        if (preg_match('/\b(\d{1,2})\b/', $input, $m)) {
            return (int) $m[1] <= 25 ? $m[1] : null;
        }

        $lower = strtolower($input);
        foreach (self::NUMBER_WORDS as $word => $num) {
            if (preg_match('/\b'.$word.'\b/', $lower)) {
                return (string) $num;
            }
        }

        return null;
    }

    private function extractGrade(string $input): ?string
    {
        if (preg_match('/\b(kg\s?[12]?|kindergarten)\b/i', $input, $m)) {
            return $m[0];
        }

        if (preg_match('/\bgrade\s*(\d{1,2})\b/i', $input, $m)) {
            return 'Grade '.$m[1];
        }

        $lower = strtolower($input);
        foreach (self::NUMBER_WORDS as $word => $num) {
            if ($num >= 1 && $num <= 12 && preg_match('/\b'.$word.'\b/', $lower)) {
                return 'Grade '.$num;
            }
        }

        return null;
    }

    private function extractFreeText(string $input): ?string
    {
        $cleaned = trim($input, " .!\t\n\r");

        if ($cleaned === '' || str_contains($cleaned, '?') || str_word_count($cleaned) > 30) {
            return null;
        }

        return $cleaned;
    }
}
