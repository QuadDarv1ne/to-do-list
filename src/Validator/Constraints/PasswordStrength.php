<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PasswordStrength extends Constraint
{
    public string $message = 'Пароль должен содержать не менее 8 символов, включая заглавные буквы, строчные буквы, цифры и специальные символы.';
    
    public int $minLength = 8;
    
    public bool $requireUppercase = true;
    
    public bool $requireLowercase = true;
    
    public bool $requireNumbers = true;
    
    public bool $requireSpecialChars = true;

    public function validatedBy(): string
    {
        return PasswordStrengthValidator::class;
    }

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
