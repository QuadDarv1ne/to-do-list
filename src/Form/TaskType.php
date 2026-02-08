<?php
// src/Form/TaskType.php

namespace App\Form;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\TaskCategory;
use App\Repository\UserRepository;
use App\Repository\TaskCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class TaskType extends AbstractType
{
    public function __construct(
        private UserRepository $userRepository,
        private TaskCategoryRepository $categoryRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTime();
        
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название задачи',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите название задачи',
                    'autofocus' => true,
                    'maxlength' => 100
                ],
                'constraints' => [
                    new NotBlank(message: 'Пожалуйста, введите название задачи'),
                    new Length(
                        max: 100,
                        maxMessage: 'Название не должно превышать {{ limit }} символов'
                    ),
                ],
                'help' => 'Максимальная длина: 100 символов'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание задачи',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Опишите детали задачи...',
                    'maxlength' => 1000
                ],
                'constraints' => [
                    new Length(
                        max: 1000,
                        maxMessage: 'Описание не должно превышать {{ limit }} символов'
                    ),
                ],
                'help' => 'Максимальная длина: 1000 символов'
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Срок выполнения',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => $today->format('Y-m-d')
                ],
                'html5' => true,
                'constraints' => [
                    new GreaterThanOrEqual(
                        value: $today,
                        message: 'Дата не может быть в прошлом'
                    ),
                ],
                'help' => 'Укажите дату окончания задачи'
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Приоритет задачи',
                'choices' => [
                    'Низкий' => 'low',
                    'Обычный' => 'normal',
                    'Высокий' => 'high',
                    'Срочный' => 'urgent',
                ],
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'priority-selector'
                ],
                'placeholder' => 'Выберите приоритет',
                'expanded' => false,
                'multiple' => false,
                'constraints' => [
                    new NotBlank(message: 'Пожалуйста, выберите приоритет')
                ]
            ])
            ->add('isDone', CheckboxType::class, [
                'label' => 'Отметка о выполнении',
                'required' => false,
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => [
                    'class' => 'form-check-input',
                    'data-action' => 'change->task#toggleStatus'
                ],
                'help' => 'Отметьте, если задача выполнена'
            ])
            ->add('assignedUser', EntityType::class, [
                'label' => 'Ответственный исполнитель',
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return sprintf('%s (%s)', $user->getFullName(), $user->getEmail());
                },
                'query_builder' => function(UserRepository $repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('u.fullName', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'user-select'
                ],
                'placeholder' => 'Выберите исполнителя',
                'constraints' => [
                    new NotBlank(message: 'Пожалуйста, выберите исполнителя')
                ]
            ])
            ->add('categories', EntityType::class, [
                'label' => 'Категории задачи',
                'class' => TaskCategory::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
                'query_builder' => function(TaskCategoryRepository $repository) {
                    return $repository->createQueryBuilder('c')
                        ->where('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.name', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'select2',
                    'data-placeholder' => 'Выберите категории'
                ],
                'required' => false,
                'help' => 'Удерживайте Ctrl для выбора нескольких категорий'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'task_item',
            'validation_groups' => ['Default', 'creation'],
        ]);
    }
}