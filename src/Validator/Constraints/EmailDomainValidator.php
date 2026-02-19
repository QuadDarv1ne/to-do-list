<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class EmailDomainValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EmailDomain) {
            throw new UnexpectedTypeException($constraint, EmailDomain::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) to take care of that
        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Extract domain from email
        $emailParts = explode('@', $value);
        if (\count($emailParts) !== 2) {
            $this->context->buildViolation('Invalid email format.')
                ->setParameter('{{ value }}', $value)
                ->addViolation();

            return;
        }

        $domain = strtolower($emailParts[1]);

        // Check blocked domains
        if (!empty($constraint->blockedDomains)) {
            $blockedDomains = array_map('strtolower', $constraint->blockedDomains);
            if (\in_array($domain, $blockedDomains)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();

                return;
            }
        }

        // Check allowed domains
        if (!empty($constraint->allowedDomains)) {
            $allowedDomains = array_map('strtolower', $constraint->allowedDomains);
            if (!\in_array($domain, $allowedDomains)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();

                return;
            }
        }

        // Additional domain validation (basic format check)
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->context->buildViolation('Invalid email format.')
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
