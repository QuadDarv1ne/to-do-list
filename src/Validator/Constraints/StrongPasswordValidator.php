<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class StrongPasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) to take care of that
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $errors = [];

        // Check minimum length
        if (strlen($value) < $constraint->minLength) {
            $errors[] = "at least {$constraint->minLength} characters";
        }

        // Check for uppercase letters
        if ($constraint->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            $errors[] = 'uppercase letters';
        }

        // Check for lowercase letters
        if ($constraint->requireLowercase && !preg_match('/[a-z]/', $value)) {
            $errors[] = 'lowercase letters';
        }

        // Check for numbers
        if ($constraint->requireNumbers && !preg_match('/[0-9]/', $value)) {
            $errors[] = 'numbers';
        }

        // Check for special characters
        if ($constraint->requireSpecialChars) {
            $pattern = '/[' . preg_quote(implode('', $constraint->allowedSpecialChars), '/') . ']/';
            if (!preg_match($pattern, $value)) {
                $errors[] = 'special characters';
            }
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123', 'password123',
            'admin', 'letmein', 'welcome', 'monkey', 'dragon', 'master'
        ];
        
        if (in_array(strtolower($value), $weakPasswords)) {
            $this->context->buildViolation('This password is too common and easily guessable.')
                ->addViolation();
            return;
        }

        // Check for sequential characters
        if (preg_match('/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $value)) {
            $errors[] = 'sequential characters';
        }

        // Check for repeated characters
        if (preg_match('/(.)\\1{2,}/', $value)) {
            $errors[] = 'repeated characters';
        }

        if (!empty($errors)) {
            $message = 'Password must contain ' . implode(', ', $errors) . '.';
            $this->context->buildViolation($message)
                ->setParameter('{{ min_length }}', (string) $constraint->minLength)
                ->addViolation();
        }
    }
}