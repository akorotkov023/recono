<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Button;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrySearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('partner_id', ChoiceType::class, [
                'label' => 'Выбрать партнера',
                'choices' => $options['help_attr']['partners'],
                'attr' => ['class' => 'form-control']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Формат реестра',
                'required' => false,
                'placeholder' => false,
                'choices' => [
                    'xlsx' => 'xlsx',
                    'csv' => 'csv',
                ],
                'data' => 'csv',
                'attr' => ['class' => 'form-control']
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Статус выборки',
                'required' => false,
                'placeholder' => false,
                'choices' => [
                    'charged' => 'charged',
                    'refunded' => 'refunded'
                ]
            ])
            ->add('month', DateType::class, [
                'label' => 'Месяц',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'format' => 'MM-yyyy',
                'attr' => ['class' => 'datepicker-month']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }

}
