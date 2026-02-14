<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class StrongPassword extends Constraint
{
    public string $message = 'Password must contain at least {{ min_length }} characters, including uppercase, lowercase, numbers, and special characters.';
    public int $minLength = 8;
    public bool $requireUppercase = true;
    public bool $requireLowercase = true;
    public bool $requireNumbers = true;
    public bool $requireSpecialChars = true;
    public array $allowedSpecialChars = ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '+', '=', '[', ']', '{', '}', '|', '\\', ':', ';', '"', '\'', '<', '>', ',', '.', '?', '/'];
    
    public function __construct(
        array $options = null,
        array $groups = null,
        mixed $payload = null,
        int $minLength = null,
        bool $requireUppercase = null,
        bool $requireLowercase = null,
        bool $requireNumbers = null,
        bool $requireSpecialChars = null,
        array $allowedSpecialChars = null,
        string $message = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);
        
        $this->minLength = $minLength ?? $this->minLength;
        $this->requireUppercase = $requireUppercase ?? $this->requireUppercase;
        $this->requireLowercase = $requireLowercase ?? $this->requireLowercase;
        $this->requireNumbers = $requireNumbers ?? $this->requireNumbers;
        $this->requireSpecialChars = $requireSpecialChars ?? $this->requireSpecialChars;
        $this->allowedSpecialChars = $allowedSpecialChars ?? $this->allowedSpecialChars;
        $this->message = $message ?? $this->message;
    }
}