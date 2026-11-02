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

class ActSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('partner_id', ChoiceType::class, [
                'label' => 'Выбрать партнера',
                'choices' => $options['help_attr']['partners'],
                'attr' => ['class' => 'form-control']
            ])
            ->add('provider', ChoiceType::class, [
                'label' => 'Провайдер',
                'choices' => $options['help_attr']['providers'],
                'attr' => ['class' => 'form-control']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип акта',
                'required' => false,
                'placeholder' => false,
                'choices' => [
                    'Платежи' => 'payment',
                    'Выплаты' => 'payout',
                ],
                'data' => 'payment',
                'attr' => ['class' => 'form-control']
            ])
            ->add('month', DateType::class, [
                'label' => 'Месяц',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'format' => 'MM-yyyy',
                'attr' => ['class' => 'datepicker-month']
            ])
            ->add('show_more', ButtonType::class, [
                'label' => 'Добавить партнера',
                'attr' => ['class' => 'btn btn-outline-secondary form-control mt-4' ]
            ])
            ->add('ids', ChoiceType::class, [
                'label' => false,
                'choices' => $options['help_attr']['partners'],
                'expanded' => true,
                'multiple' => true,
                'attr' => ['class' => 'additional-input' ]
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
