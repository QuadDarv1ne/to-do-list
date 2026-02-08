<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Введите новый пароль'
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Пожалуйста, введите пароль',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов',
                            // max length allowed by Symfony for security reasons
                            'max' => 4096,
                        ]),
                    ],
                    'label' => 'Новый пароль',
                ],
                'second_options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Подтвердите новый пароль'
                    ],
                    'label' => 'Подтверждение пароля',
                ],
                'invalid_message' => 'Пароли не совпадают',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}