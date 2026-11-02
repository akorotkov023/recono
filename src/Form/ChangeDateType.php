<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangeDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('id', TextType::class, [
                'label' => 'Введите id'
            ])
            ->add('started', DateTimeType::class, [
                'label' => 'Введите дату started',
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false
            ])
            ->add('find', SubmitType::class, [
                'label' => 'Найти',
                'attr' => ['class' => 'btn btn-outline-primary mt-3 mb-2']
            ])
            ->add('change', SubmitType::class, [
                'label' => 'Изменить дату',
                'attr' => ['class' => 'btn btn-outline-primary mt-3 mb-2']
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
