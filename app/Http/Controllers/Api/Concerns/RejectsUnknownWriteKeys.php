<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Fails a write request that carries keys the endpoint does not recognise.
 *
 * Three rounds of frontend bug reports (BE-15 `opportunity_type`, BE-18
 * `filter_type`, BE-20/BE-22 `event_type` vs `event_type_id`) had the same root
 * cause: an unrecognised key was accepted with a 200 and silently dropped, so a
 * value never reached its column and nothing said so. Rejecting the key turns a
 * silent data-loss bug into an immediate, obvious 422.
 *
 * Disable with REJECT_UNKNOWN_WRITE_KEYS=false if it ever blocks a real client.
 */
trait RejectsUnknownWriteKeys
{
    /**
     * Keys every request may carry regardless of endpoint.
     *
     * @var list<string>
     */
    private const ALWAYS_ALLOWED = [
        '_method',
        '_token',
        'lang',
        'pass_token',
    ];

    /**
     * Dynamic key patterns (fnmatch syntax) that cannot be listed literally —
     * the indexed image upload fields.
     *
     * @var list<string>
     */
    private const ALLOWED_PATTERNS = [
        'opportunity_images_*',
        'new_opportunity_images_*',
    ];

    /**
     * Throws a ValidationException (rendered as the standard 422 envelope) when
     * the payload carries keys this endpoint does not accept.
     *
     * @param  array<string, mixed>  $rules  the same rule array passed to validate()
     * @param  list<string>  $extraAllowed  keys the controller reads directly, outside the rules
     *
     * @throws ValidationException
     */
    protected function rejectUnknownWriteKeys(
        Request $request,
        array $rules,
        array $extraAllowed = []
    ): void {
        if (! config('fursa.reject_unknown_write_keys')) {
            return;
        }

        $allowed = array_merge(
            $this->ruleKeys($rules),
            self::ALWAYS_ALLOWED,
            $extraAllowed
        );

        $unknown = [];
        foreach (array_keys($request->all()) as $key) {
            $key = (string) $key;

            if (in_array($key, $allowed, true) || $this->matchesAllowedPattern($key)) {
                continue;
            }

            $unknown[] = $key;
        }

        if ($unknown === []) {
            return;
        }

        sort($unknown);

        // One error per offending key, so the client sees exactly which field
        // names to correct rather than a single lumped message.
        throw ValidationException::withMessages(
            array_fill_keys(
                $unknown,
                [__('apis.unknown_write_field')]
            )
        );
    }

    /**
     * Turn validation rule keys into plain field names: `interest_ids.*` and
     * `time_slots.0.date` both allow the top-level `interest_ids` / `time_slots`.
     *
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    private function ruleKeys(array $rules): array
    {
        $keys = [];

        foreach (array_keys($rules) as $rule) {
            $rule = (string) $rule;
            $keys[] = $rule;

            if (str_contains($rule, '.')) {
                $keys[] = strstr($rule, '.', true);
            }
        }

        return array_values(array_unique($keys));
    }

    private function matchesAllowedPattern(string $key): bool
    {
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }
}
