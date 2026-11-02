<?php


namespace App\Form;


use App\Entity\EmailConfig;
use App\Helper\BaseHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class EmailConfigType extends AbstractType
{
    public function __construct(private BaseHelper $baseHelper)
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void
    {
//        dd($options);
        $builder
            ->add('slug', TextType::class, [
                'label' => 'Имя (логин)',
                'attr' => ['placeholder' => 'Уникальное имя']
            ])
            ->add('email_to', TextType::class, [
                'label' => 'Кому',
                'attr' => ['placeholder' => 'test@test.ru']
            ])
            ->add('email_cc', EmailType::class, [
                'label' => 'Копия',
                'attr' => ['placeholder' => 'test@test.ru']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип реестра',
                'choices' => [
                    'payment' => 'payment',
                    'payout' => 'payout'
                ],
                'expanded' => true,
                'multiple' => true,
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('provider', ChoiceType::class, [
                'label' => 'Выбрать провайдера',
                'choices' => $options['provider'],
                'expanded' => true,
                'multiple' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('terminal', TextType::class, [
                'label' => 'Добавить терминал'
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Формат реестра',
                'required' => true,
                'choices' => [
                    'csv' => 'csv',
                    'xlsx' => 'xlsx'
                ]
            ])
            ->add('registry_type', ChoiceType::class, [
                'label' => 'Стратегия реестра',
                'required' => false,
                'choices' => [
                    'Комиссия 0.4% от суммы' => 'percent',
                    'Зачисление provider_amount' => 'provider_amount',
                    'Зачисление merchant_amount' => 'merchant_amount'
                ]
            ])
            ->add('description', TextType::class, [
                'label' => 'Добавить описание',
                'attr' => ['placeholder' => 'описание']
            ])
            ->add('is_active', ChoiceType::class, [
                'label' => 'Сделать активным',
                'required' => true,
                'choices' => [
                    'true' => true,
                    'false' => false
                ]
            ])
            ->add('partner_id', ChoiceType::class, [
                'label' => 'Добавить партнера в выборку для реестров',
                'choices' => $options['help_attr'],
                'expanded' => true,
                'multiple' => true,
                'attr' => ['class' => 'form-control']
            ])
        ;
        $builder
            ->get('email_to')
            ->addModelTransformer(new CallbackTransformer(
                function ($emailToArray) {
                    $res = $this->baseHelper->validateEmailArray(explode(", ",$emailToArray[0]));
                    return count($emailToArray)? $res: null;
                },
                function ($emailTosString) {
                    return [$emailTosString];
                }
            ));
        $builder
            ->get('email_cc')
            ->addModelTransformer(new CallbackTransformer(
                function ($emailCcArray) {
                    return count($emailCcArray)? $emailCcArray[0]: null;
                },
                function ($emailCcsString) {
                    return [$emailCcsString];
                }
            ));
        $builder
            ->get('terminal')
            ->addModelTransformer(new CallbackTransformer(
                // array → string (для формы)
                function ($terminalToArray) {
                    if (!$terminalToArray) {
                        return null;
                    }
                    return implode(', ', array_filter($terminalToArray));
                },
                // string → array (из формы в модель)
                function ($terminalToString) {
                    if (!$terminalToString) {
                        return [];
                    }
                    return array_map(
                        'intval',
                        array_filter(
                            array_map('trim', explode(',', $terminalToString))
                        )
                    );
                }
            ));

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailConfig::class,
            'provider' => [],
            'user_exists' => false,
        ]);
    }

}
