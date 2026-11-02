<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use DateTime;

class TelegramFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $yesterday = (new DateTime())->modify('-1 day')->format('Y-m-d');
        $defaultMessage = "По сверке ок ($yesterday). Реестры отправлены";

        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Текст сообщения',
                'data' => $defaultMessage,
                'attr' => [
                    'rows' => 10
                ],
            ])
            ->add('create', SubmitType::class, [
                'label' => 'Отправить',
                'attr' => ['class' => 'btn btn-sm btn-outline-primary float-end']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
