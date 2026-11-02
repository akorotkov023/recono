<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchToolsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('start', DateType::class, [
                'label' => 'Укажите дату',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'attr' => ['class' => 'datepicker']
            ])
            ->add('selector', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Поиск в реестрах (по provider_id)' => 1,
                    'Поиск по api (по id)' => 2,
                    'Поиск по api (по outertxid)' => 3,
                    'Поиск реестра по имени' => 4
                ]
            ])
            ->add('tx_id', TextType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('btn_tx_id', SubmitType::class, [
                'label' => 'Найти в реестре',
                'attr' => ['class' => 'btn btn-outline-primary form-control mb-3']
            ])
//            ->add('any_id', TextType::class, [
//                'label' => 'Поиск по api',
//                'required' => false,
//            ])
//            ->add('btn_any_id', SubmitType::class, [
//                'label' => 'Найти по api',
//                'attr' => ['class' => 'btn btn-outline-primary form-control mb-3']
//            ])
            ->add('any_card', TextType::class, [
                'label' => 'Введите карту',
                'required' => false,
                'attr' => [
                    'placeholder' => '123456%1234',
//                    'maxlength' => 4
                ]
            ])
            ->add('btn_any_card', SubmitType::class, [
                'label' => 'Найти по карте',
                'attr' => ['class' => 'btn btn-outline-primary form-control mb-3']
            ])
//            ->add('find_file', TextType::class, [
//                'label' => 'Поиск реестра по имени',
//                'required' => false,
//            ])
//            ->add('btn_find_file', SubmitType::class, [
//                'label' => 'Найти реестр',
//                'attr' => ['class' => 'btn btn-outline-primary form-control mb-3']
//            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
