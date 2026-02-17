<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class StrongPasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Check minimum length
        if (strlen($value) < $constraint->minLength) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check uppercase letter
        if ($constraint->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check lowercase letter
        if ($constraint->requireLowercase && !preg_match('/[a-z]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check digit
        if ($constraint->requireDigit && !preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check special character
        if ($constraint->requireSpecialChar && !preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}
