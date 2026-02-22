<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Текущий пароль',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'placeholder' => 'Введите текущий пароль',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Введите текущий пароль',
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        new NotBlank(
                            message: 'Введите новый пароль',
                        ),
                        new Length(
                            min: 8,
                            max: 4096,
                            minMessage: 'Пароль должен содержать минимум {{ limit }} символов',
                        ),
                    ],
                    'label' => 'Новый пароль',
                    'attr' => [
                        'placeholder' => 'Введите новый пароль',
                    ],
                ],
                'second_options' => [
                    'label' => 'Повторите новый пароль',
                    'attr' => [
                        'placeholder' => 'Повторите новый пароль',
                    ],
                ],
                'invalid_message' => 'Пароли не совпадают',
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
