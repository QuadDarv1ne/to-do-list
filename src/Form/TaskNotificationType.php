<?php

namespace App\Form;

use App\Entity\TaskNotification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Тип уведомления',
                'choices' => [
                    'Назначение задачи' => 'assignment',
                    'Обновление задачи' => 'update',
                    'Выполнение задачи' => 'completion',
                    'Напоминание' => 'reminder',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Тема',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите тему уведомления'
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Сообщение',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Введите текст сообщения уведомления'
                ],
            ])
            ->add('isSent', CheckboxType::class, [
                'label' => 'Отправлено',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('isRead', CheckboxType::class, [
                'label' => 'Прочитано',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskNotification::class,
        ]);
    }
}
