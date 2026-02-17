<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StrongPassword extends Constraint
{
    public string $message = 'Пароль должен содержать минимум 8 символов, включая заглавные и строчные буквы, цифры и специальные символы.';
    public int $minLength = 8;
    public bool $requireUppercase = true;
    public bool $requireLowercase = true;
    public bool $requireDigit = true;
    public bool $requireSpecialChar = true;
}
