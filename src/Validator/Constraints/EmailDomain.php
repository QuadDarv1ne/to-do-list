<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class EmailDomain extends Constraint
{
    public string $message = 'The email "{{ value }}" contains an invalid domain.';
    public array $allowedDomains = [];
    public array $blockedDomains = [];
    
    public function __construct(
        ?array $options = null,
        ?array $groups = null,
        mixed $payload = null,
        array $allowedDomains = [],
        array $blockedDomains = [],
        ?string $message = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);
        
        $this->allowedDomains = $allowedDomains;
        $this->blockedDomains = $blockedDomains;
        $this->message = $message ?? $this->message;
    }
}
