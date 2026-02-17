<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Validator\Constraints\StrongPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Пароли должны совпадать',
                'first_options' => [
                    'label' => 'Новый пароль',
                    'attr' => [
                        'class' => 'form-control password-field',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Введите новый пароль',
                        'minlength' => 8,
                        'maxlength' => 4096,
                        'data-password-field' => 'true',
                    ],
                    'constraints' => [
                        new NotBlank(
                            message: 'Пожалуйста, введите пароль'
                        ),
                        new Length(
                            min: 8,
                            max: 4096,
                            minMessage: 'Пароль должен содержать минимум {{ limit }} символов',
                            maxMessage: 'Пароль слишком длинный'
                        ),
                        new StrongPassword(),
                        new Regex(
                            pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/',
                            message: 'Пароль должен содержать хотя бы одну букву и одну цифру'
                        ),
                    ],
                    'help' => 'Минимум 8 символов, буквы и цифры',
                    'help_attr' => ['class' => 'form-text text-muted small'],
                ],
                'second_options' => [
                    'label' => 'Подтверждение пароля',
                    'attr' => [
                        'class' => 'form-control password-confirm-field',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Повторите новый пароль',
                        'minlength' => 8,
                        'maxlength' => 4096,
                    ],
                    'help' => 'Введите пароль еще раз для подтверждения',
                    'help_attr' => ['class' => 'form-text text-muted small'],
                ],
                'attr' => [
                    'class' => 'password-repeat-group',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'change_password',
            'attr' => [
                'class' => 'password-change-form',
                'novalidate' => 'novalidate', // Отключаем браузерную валидацию в пользу Symfony
            ],
        ]);
    }
}
