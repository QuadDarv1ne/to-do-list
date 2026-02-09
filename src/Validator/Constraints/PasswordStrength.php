<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class PasswordStrength extends Constraint
{
    public string $message = 'Пароль должен содержать не менее 8 символов, включая заглавную букву, строчную букву, цифру и специальный символ.';
}