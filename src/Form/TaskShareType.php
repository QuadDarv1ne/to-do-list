<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskShareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('users', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-control select2',
                ],
                'label' => 'Пользователи для совместного доступа',
                'placeholder' => 'Выберите пользователей...',
            ])
            ->add('permission', ChoiceType::class, [
                'choices' => [
                    'Только чтение' => 'read',
                    'Чтение и комментирование' => 'comment',
                    'Чтение, комментирование и редактирование' => 'edit',
                ],
                'label' => 'Уровень доступа',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('share', SubmitType::class, [
                'label' => 'Поделиться задачей',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}