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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

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
            ->add('daysOfWeek', ChoiceType::class, [
                'label' => 'Дни недели (для еженедельного повторения)',
                'choices' => [
                    'Понедельник' => '1',
                    'Вторник' => '2',
                    'Среда' => '3',
                    'Четверг' => '4',
                    'Пятница' => '5',
                    'Суббота' => '6',
                    'Воскресенье' => '7',
                ],
                'multiple' => true,
                'required' => false,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('daysOfMonth', ChoiceType::class, [
                'label' => 'Дни месяца (для ежемесячного повторения)',
                'choices' => array_combine(range(1, 31), range(1, 31)),
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Сохранить повторение',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
        
        // Transform daysOfWeek from comma-separated string to array for form
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            // Transform daysOfWeek
            if ($data && $data->getDaysOfWeek()) {
                $daysOfWeekArray = $data->getDaysOfWeekArray();
                $form->get('daysOfWeek')->setData($daysOfWeekArray);
            }
            
            // Transform daysOfMonth
            if ($data && $data->getDaysOfMonth()) {
                $daysOfMonthArray = $data->getDaysOfMonthArray();
                $form->get('daysOfMonth')->setData($daysOfMonthArray);
            }
        });
        
        // Transform daysOfWeek from array to comma-separated string on submit
        $builder->get('daysOfWeek')->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $parent = $event->getForm()->getParent();
            
            if ($parent && $data && is_array($data)) {
                $recurrence = $parent->getData();
                $recurrence->setDaysOfWeekFromArray($data);
            }
        });
        
        // Transform daysOfMonth from array to comma-separated string on submit
        $builder->get('daysOfMonth')->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $parent = $event->getForm()->getParent();
            
            if ($parent && $data && is_array($data)) {
                $recurrence = $parent->getData();
                $recurrence->setDaysOfMonthFromArray($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskRecurrence::class,
        ]);
    }
}