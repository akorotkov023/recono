<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailProcessType extends AbstractType
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
                'label' => 'Создание реестра за',
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
            ->add('create_registry', SubmitType::class, [
                'label' => 'Создать реестр',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])
            ->add('check_registry', SubmitType::class, [
                'label' => 'Проверить все',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
            ])
            ->add('send_registry', SubmitType::class, [
                'label' => 'Отправить все',
                'attr' => ['class' => 'btn btn-outline-primary form-control']
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
