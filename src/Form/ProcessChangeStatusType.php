<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ProcessChangeStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('process', ChoiceType::class, [
                'label' => 'Выберите process которому нужно сменить статус',
                'choices' => [
                    'registry_command' => 'registry_command',
                    'email_process' => 'email_process'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Выберите тип процесса']),
                ],
            ])
            ->add('id', IntegerType::class, [
                'label' => 'Введите id',
                'constraints' => [
                    new NotBlank(['message' => 'Введите id']),
                    new Positive(['message' => 'id должен быть положительным числом']),
                ],
            ])
            ->add('status', TextType::class, [
                'label' => 'Введите status',
                'constraints' => [
                    new NotBlank(['message' => 'Введите статус']),
                ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Сменить статус',
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
