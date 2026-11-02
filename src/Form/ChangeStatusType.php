<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangeStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
//            ->add('token', TextType::class, [
//                'label' => 'Введите токен'
//            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Выберите status',
                'choices' => [
                    'charged' => 'charged',
                    'rejected' => 'rejected'
                ]
            ])
            ->add('description', TextType::class, [
                'label' => 'Введите описание'
            ])
            ->add('ids', TextareaType::class, [
                'label' => 'Введите id'
            ])
            ->add('is_active', ChoiceType::class, [
                'label' => 'Оповестить мерча',
                'required' => true,
                'choices' => [
                    'Да' => true,
                    'Нет' => false
                ]
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Запустить',
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
