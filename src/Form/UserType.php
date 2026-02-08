<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Логин',
                'attr' => [
                    'placeholder' => 'Введите логин',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'min' => 3,
                        'max' => 180,
                        'minMessage' => 'Логин должен содержать минимум {{ limit }} символа',
                        'maxMessage' => 'Логин не может быть длиннее {{ limit }} символов',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'user@example.com',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Имя',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Иван',
                    'class' => 'form-control',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Фамилия',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Иванов',
                    'class' => 'form-control',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Телефон',
                'required' => false,
                'attr' => [
                    'placeholder' => '+79993332211',
                    'class' => 'form-control',
                    'data-mask' => '+7 (999) 999-99-99',
                ],
            ])
            ->add('position', TextType::class, [
                'label' => 'Должность',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Менеджер',
                    'class' => 'form-control',
                ],
            ])
            ->add('department', TextType::class, [
                'label' => 'Отдел',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Отдел продаж',
                    'class' => 'form-control',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Роли',
                'choices' => [
                    'Пользователь' => 'ROLE_USER',
                    'Менеджер' => 'ROLE_MANAGER',
                    'Администратор' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активный',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Заметки',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Дополнительная информация о пользователе',
                    'class' => 'form-control',
                ],
            ]);

        // Добавляем поле пароля только при создании пользователя
        if ($options['is_new']) {
            $builder->add('plainPassword', PasswordType::class, [
                'label' => 'Пароль',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов',
                        'max' => 4096,
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
        ]);
    }
}