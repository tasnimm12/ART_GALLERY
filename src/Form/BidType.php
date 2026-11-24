<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class BidType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $minAmount = $options['min_amount'] ?? 0;

        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Votre offre (€)',
                'scale' => 2,
                'attr' => [
                    'class' => 'bid-input',
                    'min' => $minAmount,
                    'step' => '0.01',
                    'placeholder' => number_format($minAmount, 2, '.', ''),
                    'data-min' => $minAmount,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un montant.']),
                    new GreaterThan([
                        'value' => $minAmount - 0.01,
                        'message' => sprintf('Votre mise doit être d\'au moins %.2f €.', $minAmount),
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'min_amount' => 0,
        ]);
        $resolver->setAllowedTypes('min_amount', ['int', 'float']);
    }
}
