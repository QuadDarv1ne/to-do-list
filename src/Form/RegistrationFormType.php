<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\Constraints\StrongPassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Имя',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите имя'
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Имя должно содержать минимум {{ limit }} символа',
                        maxMessage: 'Имя должно содержать максимум {{ limit }} символов',
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u',
                        message: 'Имя может содержать только буквы, пробелы и дефисы'
                    ),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Фамилия',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите фамилию'
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Фамилия должна содержать минимум {{ limit }} символа',
                        maxMessage: 'Фамилия должна содержать максимум {{ limit }} символов',
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u',
                        message: 'Фамилия может содержать только буквы, пробелы и дефисы'
                    ),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Логин',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите логин'
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(
                        min: 3,
                        max: 50,
                        minMessage: 'Логин должен содержать минимум {{ limit }} символа',
                        maxMessage: 'Логин должен содержать максимум {{ limit }} символов',
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Z0-9_\-\.]+$/',
                        message: 'Логин может содержать только латинские буквы, цифры, точки, дефисы и подчеркивания'
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@example.com'
                ],
                'constraints' => [
                    new NotBlank(),
                    new Email(
                        message: 'Пожалуйста, введите корректный email'
                    ),
                    new Length(
                        max: 180,
                        maxMessage: 'Email должен содержать максимум {{ limit }} символов',
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => ['placeholder' => 'Введите пароль'],
                    'constraints' => [
                        new NotBlank(),
                        new Length(
                            min: 8,
                            max: 4096,
                            minMessage: 'Пароль должен содержать минимум {{ limit }} символов',
                            maxMessage: 'Пароль должен содержать максимум {{ limit }} символов',
                        ),
                        new StrongPassword(),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                            message: 'Пароль должен содержать хотя бы одну заглавную букву, одну строчную букву и одну цифру'
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => 'Подтверждение пароля',
                    'attr' => ['placeholder' => 'Повторите пароль'],
                ],
                'invalid_message' => 'Пароли должны совпадать',
                'mapped' => false,
            ])
            ->add('phone', TextType::class, [
                'label' => 'Телефон',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+7 (999) 999-99-99'
                ],
                'constraints' => [
                    new Regex(
                        pattern: '/^(\+7|8)[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}$/',
                        message: 'Введите корректный российский номер телефона'
                    ),
                    new Length(
                        max: 20,
                        maxMessage: 'Телефон должен содержать максимум {{ limit }} символов',
                    ),
                ],
            ])
            ->add('position', TextType::class, [
                'label' => 'Должность',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите должность'
                ],
                'constraints' => [
                    new Length(
                        max: 100,
                        maxMessage: 'Должность должна содержать максимум {{ limit }} символов',
                    ),
                ],
            ])
            ->add('department', TextType::class, [
                'label' => 'Отдел',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите отдел'
                ],
                'constraints' => [
                    new Length(
                        max: 100,
                        maxMessage: 'Отдел должен содержать максимум {{ limit }} символов',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
