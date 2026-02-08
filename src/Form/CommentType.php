<?php

namespace App\Form;

use App\Entity\Comment;
use App\Entity\CommentAttachment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => false,
                'attr' => [
                    'class' => 'form-control comment-textarea',
                    'rows' => 4,
                    'placeholder' => 'Введите ваш комментарий...',
                    'minlength' => 2,
                    'maxlength' => 2000,
                    'data-comment-target' => 'content',
                    'aria-label' => 'Текст комментария',
                    'aria-describedby' => 'commentHelp',
                    'style' => 'resize: vertical;',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, введите текст комментария',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Комментарий должен содержать минимум {{ limit }} символа',
                        'max' => 2000,
                        'maxMessage' => 'Комментарий не должен превышать {{ limit }} символов',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}\p{N}\p{P}\p{Z}\p{Sm}\p{Sc}\p{Sk}]+$/u',
                        'message' => 'Комментарий содержит недопустимые символы',
                    ]),
                ],
                'help' => 'Максимальная длина комментария: 2000 символов',
                'help_attr' => [
                    'id' => 'commentHelp',
                    'class' => 'form-text text-muted small',
                ],
            ])
        ;

        // Добавляем родительский комментарий (для ответов)
        if ($options['parent_comment']) {
            $builder->add('parent', HiddenType::class, [
                'data' => $options['parent_comment']->getId(),
                'mapped' => false,
            ]);
        }

        // Добавляем поле для загрузки файлов, если нужно
        if ($options['allow_attachments']) {
            $builder->add('attachment', FileType::class, [
                'label' => 'Прикрепить файл',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'maxSizeMessage' => 'Файл слишком большой. Максимальный размер: {{ limit }}',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => 'Пожалуйста, загрузите файл допустимого формата',
                    ]),
                ],
                'help' => 'Допустимые форматы: JPG, PNG, GIF, PDF, DOC, DOCX, TXT (макс. 5 МБ)',
                'help_attr' => ['class' => 'form-text text-muted small'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'comment_form',
            'attr' => [
                'novalidate' => 'novalidate',
                'class' => 'comment-form',
                'data-controller' => 'comment-form',
                'data-action' => 'submit->comment-form#validate',
            ],
            'validation_groups' => ['Default'],
            'parent_comment' => null,
            'allow_attachments' => true,
            'allow_mentions' => true,
        ]);
    }
}