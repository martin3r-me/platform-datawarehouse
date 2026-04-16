<?php

namespace Platform\Datawarehouse\Providers;

use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseStream;

/**
 * Bundles everything a provider needs for one fetch() call.
 *
 * Providers should be pure with respect to this context — they must not
 * read from the database themselves beyond what is passed in here.
 */
class PullContext
{
    /**
     * @param  array<string, mixed>|null  $cursor            Cursor from last_cursor on the stream.
     * @param  bool                       $incremental       Full vs incremental fetch.
     * @param  string|null                $incrementalField  Field name to filter on, if any.
     * @param  \DateTimeInterface|null    $since             Incremental-since timestamp.
     */
    public function __construct(
        public readonly DatawarehouseConnection $connection,
        public readonly DatawarehouseStream $stream,
        public readonly Endpoint $endpoint,
        public readonly ?array $cursor = null,
        public readonly bool $incremental = false,
        public readonly ?string $incrementalField = null,
        public readonly ?\DateTimeInterface $since = null,
    ) {}
}
