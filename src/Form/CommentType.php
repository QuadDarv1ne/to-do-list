<?php

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Комментарий',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Введите ваш комментарий...',
                    'aria-label' => 'Текст комментария',
                    'aria-describedby' => 'commentHelp',
                    'style' => 'resize: vertical;',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(
                        min: 2,
                        max: 1000,
                        minMessage: 'Комментарий должен содержать минимум {{ limit }} символа',
                        maxMessage: 'Комментарий не должен превышать {{ limit }} символов',
                    ),
                ],
                'help' => 'Минимум 2 символа, максимум 1000',
                'help_attr' => ['id' => 'commentHelp', 'class' => 'form-text text-muted small'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}