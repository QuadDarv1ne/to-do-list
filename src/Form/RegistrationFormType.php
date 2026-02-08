<?php

namespace App\Form;

use App\Entity\User;
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
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Логин должен содержать минимум {{ limit }} символа',
                        'max' => 50,
                    ]),
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
                    new Email([
                        'message' => 'Пожалуйста, введите корректный email',
                    ]),
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
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов',
                            'max' => 4096,
                        ]),
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
                    'placeholder' => '+7 (XXX) XXX-XX-XX'
                ],
            ])
            ->add('position', TextType::class, [
                'label' => 'Должность',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите должность'
                ],
            ])
            ->add('department', TextType::class, [
                'label' => 'Отдел',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите отдел'
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