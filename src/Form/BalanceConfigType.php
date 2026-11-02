<?php


namespace App\Form;


use App\Entity\BalanceConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BalanceConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
//        dd($options);
        $builder
            ->add('partner_id', ChoiceType::class, [
                'label' => 'Добавить',
                'choices' => $options['help_attr'],
                'attr' => ['class' => 'form-control']
            ])
            ->add('partner_name', TextType::class, [
                'label' => 'Имя партнера',
                'attr' => ['placeholder' => 'Название партнера']
            ])
            ->add('wallet_id', TextType::class, [
                'label' => 'ID кошелька для пополнения',
                'attr' => ['placeholder' => '106']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип кошелька',
                'choices' => [
                    'internal' => 'internal',
                    'provider' => 'provider',
                    'rolling' => 'rolling'
                ]
            ])
            ->add('provider', TextType::class, [
                'label' => 'Провайдер (для rolling)',
                'required' => false,
                'attr' => ['placeholder' => 'имя провайдера']
            ])
            ->add('is_active', ChoiceType::class, [
                'label' => 'Сделать активным',
                'choices' => [
                    'true' => true,
                    'false' => false
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextType::class, [
                'label' => 'Добавить описание',
                'attr' => ['placeholder' => 'описание']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BalanceConfig::class,
            'user_exists' => false,
        ]);
    }

}

