<?php


namespace App\Form;


use App\Entity\BalanceConfig;
use App\Entity\RegistryConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistryConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
//        dd($options);
        $builder
            ->add('provider', ChoiceType::class, [
                'label' => 'Добавить провайдера',
                'choices' => $options['help_attr']['provider'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Название реестра',
                'attr' => ['placeholder' => 'provider_payment_']
            ])
            ->add('providerWalletId', ChoiceType::class, [
                'label' => 'Выбрать банк (provider_wallet_id)',
                'choices' => $options['help_attr']['name'],
            ])
            ->add('description', TextType::class, [
                'label' => 'Добавить описание',
                'attr' => ['placeholder' => 'описание']
            ])
            ->add('is_active', ChoiceType::class, [
                'label' => 'Сделать активным',
                'choices' => [
                    'true' => true,
                    'false' => false
                ],
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'user_exists' => false,
        ]);
    }

}

