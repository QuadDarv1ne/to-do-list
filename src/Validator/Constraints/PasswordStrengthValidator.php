<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Minimum length of 8 characters
        if (strlen($value) < 8) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Contains uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Contains lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Contains digit
        if (!preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Contains special character
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}