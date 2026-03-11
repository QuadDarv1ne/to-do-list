<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        $password = (string) $value;
        $minLength = $constraint->minLength;

        // Проверка минимальной длины
        if (mb_strlen($password) < $minLength) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ length }}', $minLength)
                ->addViolation();

            return;
        }

        // Проверка заглавных букв
        if ($constraint->requireUppercase && !preg_match('/[A-ZА-ЯЁ]/u', $password)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ requirement }}', 'заглавную букву')
                ->addViolation();

            return;
        }

        // Проверка строчных букв
        if ($constraint->requireLowercase && !preg_match('/[a-zа-яё]/u', $password)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ requirement }}', 'строчную букву')
                ->addViolation();

            return;
        }

        // Проверка цифр
        if ($constraint->requireNumbers && !preg_match('/[0-9]/', $password)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ requirement }}', 'цифру')
                ->addViolation();

            return;
        }

        // Проверка специальных символов
        if ($constraint->requireSpecialChars && !preg_match('/[^A-Za-zА-Яа-яЁё0-9]/u', $password)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ requirement }}', 'специальный символ')
                ->addViolation();

            return;
        }
    }
}
