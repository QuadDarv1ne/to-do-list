<?php
// src/Form/TaskType.php

namespace App\Form;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите название задачи'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите название задачи'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Введите описание задачи'
                ]
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Срок выполнения',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Приоритет',
                'choices' => [
                    'Низкий' => 'low',
                    'Обычный' => 'normal',
                    'Высокий' => 'high',
                    'Срочный' => 'urgent',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('isDone', CheckboxType::class, [
                'label' => 'Выполнено',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('assignedUser', EntityType::class, [
                'label' => 'Назначена пользователю',
                'class' => User::class,
                'choice_label' => 'fullName',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);
    }
}