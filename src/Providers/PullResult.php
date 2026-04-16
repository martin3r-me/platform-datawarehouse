<?php

namespace Platform\Datawarehouse\Providers;

/**
 * One page worth of results returned by a provider's fetch() call.
 *
 * The `rows` array must already be normalized to flat associative arrays
 * whose keys correspond to the source_key of the stream's columns.
 *
 * `nextCursor === null` signals the end of the fetch.
 */
class PullResult
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $nextCursor
     * @param  array<int|string>          $seenExternalIds  Natural-key values seen so far (for soft-delete).
     * @param  array<string, mixed>       $meta             Provider diagnostics (rate limit, etc.).
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?array $nextCursor = null,
        public readonly array $seenExternalIds = [],
        public readonly array $meta = [],
    ) {}

    public function isLastPage(): bool
    {
        return $this->nextCursor === null;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
