<?php

namespace App\Services;

use InvalidArgumentException;

class StringFieldTransformer
{
    /** @param array<string, mixed> $intent */
    public function replace(?string $value, array $intent): ?string
    {
        $mode = $intent['mode'] ?? 'literal';
        $search = $intent['search'] ?? null;
        $replacement = $intent['replacement'] ?? '';
        $flags = $intent['flags'] ?? '';
        $occurrences = $intent['occurrences'] ?? 'all';
        if (! in_array($mode, ['literal', 'regex'], true) || ! is_string($search) || $search === '' || ! is_string($replacement) || ! is_string($flags) || preg_match('/\A[msux]*\z/D', $flags) !== 1 || ! in_array($occurrences, ['first', 'all'], true)) {
            throw new InvalidArgumentException('Choose a nonempty pattern and supported replacement options.');
        }
        $pattern = '~(*LIMIT_MATCH=100000)(*LIMIT_DEPTH=1000)'.($mode === 'literal' ? preg_quote($search, '~') : str_replace('~', '\\~', $search)).'~u'.(($intent['case_sensitive'] ?? true) ? '' : 'i').($mode === 'regex' ? $flags : '');
        $limit = $occurrences === 'first' ? 1 : -1;
        set_error_handler(static fn (): bool => true);
        try {
            $result = $mode === 'literal'
                ? preg_replace_callback($pattern, static fn (): string => $replacement, $value ?? '', $limit)
                : preg_replace($pattern, $replacement, $value ?? '', $limit);
            if ($result === null) {
                throw new InvalidArgumentException('Pattern could not run: '.preg_last_error_msg());
            }

            return $value === null ? null : $result;
        } finally {
            restore_error_handler();
        }
    }
}
