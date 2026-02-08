<?php

namespace App\Form;

use App\Entity\TaskRecurrence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskRecurrenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('frequency', ChoiceType::class, [
                'label' => 'Частота повторения',
                'choices' => [
                    'Ежедневно' => 'daily',
                    'Еженедельно' => 'weekly',
                    'Ежемесячно' => 'monthly',
                    'Ежегодно' => 'yearly',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('interval', IntegerType::class, [
                'label' => 'Интервал',
                'data' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Например: 1 (каждый день), 2 (каждые 2 дня)'
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Дата окончания (необязательно)',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Сохранить повторение',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskRecurrence::class,
        ]);
    }
}