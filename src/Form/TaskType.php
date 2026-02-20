<?php

namespace App\Form;

use App\Entity\Tag;
use App\Entity\TaskCategory;
use App\Entity\User;
use App\Repository\TagRepository;
use App\Repository\TaskCategoryRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaskType extends AbstractType
{
    public function __construct(
        private UserRepository $userRepository,
        private TaskCategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Название задачи',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите название задачи',
                    'autofocus' => true,
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(
                        max: 100,
                        maxMessage: 'Название не должно превышать {{ limit }} символов',
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание задачи',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Опишите задачу подробнее...',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Статус',
                'choices' => [
                    'В ожидании' => 'pending',
                    'В процессе' => 'in_progress',
                    'Завершено' => 'completed',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => 'Выберите статус',
                'expanded' => false,
                'multiple' => false,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Приоритет',
                'choices' => [
                    'Низкий' => 'low',
                    'Средний' => 'medium',
                    'Высокий' => 'high',
                    'Критический' => 'urgent',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => 'Выберите приоритет',
                'expanded' => false,
                'multiple' => false,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('dueDate', DateTimeType::class, [
                'label' => 'Срок выполнения',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new GreaterThanOrEqual(
                        value: 'today',
                        message: 'Срок не может быть в прошлом',
                    ),
                ],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категория',
                'class' => TaskCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => 'Выберите категорию',
                'query_builder' => function (TaskCategoryRepository $er) use ($options) {
                    return $er->createQueryBuilder('c')
                        ->where('c.user = :user')
                        ->setParameter('user', $options['user'])
                        ->orderBy('c.name', 'ASC');
                },
            ])
            ->add('assignedUser', EntityType::class, [
                'label' => 'Назначить пользователю',
                'class' => User::class,
                'choice_label' => 'fullName',
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'user-select',
                ],
                'placeholder' => 'Выберите исполнителя',
            ])
            ->add('progress', IntegerType::class, [
                'label' => 'Прогресс (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-range',
                    'min' => 0,
                    'max' => 100,
                    'step' => 5,
                    'type' => 'range',
                    'data-progress-slider' => 'true',
                ],
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Теги',
                'class' => Tag::class,
                'choice_label' => 'name',
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-select',
                    'data-placeholder' => 'Выберите теги...',
                ],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) use ($options) {
                    $qb = $er->createQueryBuilder('t')
                        ->orderBy('t.name', 'ASC');

                    if (isset($options['user']) && $options['user']) {
                        $qb->andWhere('t.user = :user')
                           ->setParameter('user', $options['user']);
                    }

                    return $qb;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\Task::class,
            'user' => null,
        ]);
    }
}
