<?php

namespace App\Form;

use App\Entity\Task;
use App\Entity\TaskRecurrence;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TaskRecurrenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('task', EntityType::class, [
                'class' => Task::class,
                'label' => 'Шаблон задачи',
                'placeholder' => 'Выберите задачу как шаблон',
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Выберите задачу как шаблон',
                    ),
                ],
            ])
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
                    'data-frequency-selector' => 'true',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Выберите частоту повторения',
                    ),
                ],
            ])
            ->add('interval', IntegerType::class, [
                'label' => 'Интервал',
                'data' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 365,
                ],
                'help' => 'Например: 1 = каждый день, 2 = каждые 2 дня',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Дата окончания',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ДД.ММ.ГГГГ',
                ],
                'help' => 'Оставьте пустым для бесконечного повторения',
            ])
            ->add('daysOfWeek', ChoiceType::class, [
                'label' => 'Дни недели',
                'choices' => [
                    'Понедельник' => 1,
                    'Вторник' => 2,
                    'Среда' => 3,
                    'Четверг' => 4,
                    'Пятница' => 5,
                    'Суббота' => 6,
                    'Воскресенье' => 7,
                ],
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'days-checkbox-group',
                ],
                'help' => 'Выберите дни недели для еженедельного повторения',
            ])
            ->add('daysOfMonth', ChoiceType::class, [
                'label' => 'Дни месяца',
                'choices' => $this->getDaysOfMonthChoices(),
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'days-checkbox-group',
                ],
                'help' => 'Выберите дни месяца для ежемесячного повторения',
            ])
        ;
    }

    private function getDaysOfMonthChoices(): array
    {
        $choices = [];
        $ordinals = [
            1 => 'первое', 2 => 'второе', 3 => 'третье', 4 => 'четвёртое', 5 => 'пятое',
            6 => 'шестое', 7 => 'седьмое', 8 => 'восьмое', 9 => 'девятое', 10 => 'десятое',
            11 => 'одиннадцатое', 12 => 'двенадцатое', 13 => 'тринадцатое', 14 => 'четырнадцатое',
            15 => 'пятнадцатое', 16 => 'шестнадцатое', 17 => 'семнадцатое', 18 => 'восемнадцатое',
            19 => 'девятнадцатое', 20 => 'двадцатое', 21 => 'двадцать первое', 22 => 'двадцать второе',
            23 => 'двадцать третье', 24 => 'двадцать четвёртое', 25 => 'двадцать пятое',
            26 => 'двадцать шестое', 27 => 'двадцать седьмое', 28 => 'двадцать восьмое',
            29 => 'двадцать девятое', 30 => 'тридцатое', 31 => 'тридцать первое',
        ];

        for ($i = 1; $i <= 31; $i++) {
            $choices["{$i}-е ({$ordinals[$i]})"] = $i;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskRecurrence::class,
            'attr' => [
                'class' => 'task-recurrence-form',
            ],
        ]);
    }
}
