<?php

namespace App\Form;

use App\Entity\Webhook;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class WebhookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $availableEvents = Webhook::getAvailableEvents();

        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Например: Уведомления в Slack',
                    'maxlength' => 255,
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Название webhook обязательно',
                    ),
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'Название не может быть длиннее {{ limit }} символов',
                    ),
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL webhook',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://example.com/webhook',
                    'maxlength' => 2048,
                ],
                'help' => 'URL, на который будут отправляться уведомления',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'URL webhook обязателен',
                    ),
                    new Assert\Url(
                        message: 'Некорректный URL формат',
                    ),
                ],
            ])
            ->add('secret', TextType::class, [
                'label' => 'Секретный ключ',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Будет сгенерирован автоматически',
                    'maxlength' => 64,
                ],
                'help' => 'Используется для HMAC-подписи запросов. Оставьте пустым для автогенерации.',
            ])
            ->add('events', ChoiceType::class, [
                'label' => 'События',
                'choices' => array_flip($availableEvents),
                'multiple' => true,
                'attr' => [
                    'class' => 'form-select',
                    'size' => count($availableEvents),
                ],
                'help' => 'Выберите события, на которые будет реагировать webhook. Выберите "*" для всех событий.',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Выберите хотя бы одно событие',
                    ),
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Выберите хотя бы {{ limit }} событие',
                    ),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активен',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Webhook::class,
            'attr' => [
                'class' => 'webhook-form',
            ],
        ]);
    }
}
