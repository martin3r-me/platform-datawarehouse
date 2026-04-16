<?php

namespace Platform\Datawarehouse\Providers;

/**
 * Declarative description of one field required in a Connection's credentials.
 * Consumed by the Connection-edit UI to render the correct form element.
 */
class AuthField
{
    public const TYPE_STRING   = 'string';
    public const TYPE_PASSWORD = 'password';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SELECT   = 'select';
    public const TYPE_URL      = 'url';

    /**
     * @param  array<string, string>  $options  For TYPE_SELECT: value => label.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TYPE_STRING,
        public readonly bool $required = true,
        public readonly ?string $description = null,
        public readonly ?string $placeholder = null,
        public readonly array $options = [],
    ) {}
}
