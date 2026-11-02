<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistryCommandType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('provider_name', ChoiceType::class, [
                'label' => 'Провайдер',
                'choices' => $options['provider_choices'],
            ])
            ->add('start', DateType::class, [
                'label' => 'Начиная с даты',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'attr' => ['class' => 'datepicker']

            ])
            ->add('end', DateType::class, [
                'label' => 'Заканчивая датой',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'attr' => ['class' => 'datepicker']
            ])
            ->add('show', SubmitType::class, [
                'label' => 'Показать',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])
            ->add('create_data', DateType::class, [
                'label' => 'За дату',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'attr' => ['class' => 'datepicker']
            ])
            ->add('registry_restart_email', SubmitType::class, [
                'label' => 'Проверить почту',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])
            ->add('registry_restart_create', SubmitType::class, [
                'label' => 'Создать реестры',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])
            ->add('registry_restart_extract', SubmitType::class, [
                'label' => 'Извлечь файлы',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])
            ->add('registry_restart_check', SubmitType::class, [
                'label' => 'Проверить реестры',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])

//            ->add('xlsx', SubmitType::class, [
//                'label' => 'В xlsx',
//                'attr' => ['class' => 'btn btn-outline-primary form-control']
//            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'provider_choices' => [],
        ]);
    }
}
