<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BalanceProcessType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            ->add('form', SubmitType::class, [
                'label' => 'Показать',
                'attr' => ['class' => 'btn btn-outline-primary form-control mt-4']
            ])
            ->add('create_data', DateType::class, [
                'label' => 'Создание баланса за',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
                'attr' => ['class' => 'datepicker']
            ])
            ->add('choose', ChoiceType::class, [
                'label' => false,
                'choices' => $options['help_attr'],
                'attr' => ['class' => 'form-control']
            ])
            ->add('create_balance', SubmitType::class, [
                'label' => 'Создать баланс',
                'attr' => ['class' => 'btn btn-outline-primary form-control mt-4']
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
