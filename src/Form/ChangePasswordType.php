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
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите текущий пароль'
                ],
                'mapped' => false,
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Пароли должны совпадать',
                'options' => [
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'required' => true,
                'first_options' => [
                    'label' => 'Новый пароль',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Введите новый пароль'
                    ]
                ],
                'second_options' => [
                    'label' => 'Подтвердите новый пароль',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Подтвердите новый пароль'
                    ]
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите пароль',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов',
                        'max' => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}