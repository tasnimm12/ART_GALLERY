<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CheckoutFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('address', TextType::class, [
                'label' => 'Adresse de livraison',
                'attr' => ['placeholder' => '12 rue des Beaux-Arts', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse de livraison est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'L\'adresse de livraison est trop courte.',
                        'maxMessage' => 'L\'adresse de livraison est trop longue.',
                    ]),
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'attr' => ['placeholder' => '75006', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le code postal est obligatoire.']),
                    new Regex([
                        'pattern' => '/^\d{4,5}$/',
                        'message' => 'Le code postal doit contenir 4 ou 5 chiffres.',
                    ]),
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'attr' => ['placeholder' => 'Paris', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'La ville est obligatoire.']),
                    new Length([
                        'min' => 2,
                        'max' => 120,
                        'minMessage' => 'Le nom de la ville est trop court.',
                        'maxMessage' => 'Le nom de la ville est trop long.',
                    ]),
                ],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'data' => 'France',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le pays est obligatoire.']),
                    new Length([
                        'min' => 2,
                        'max' => 80,
                        'minMessage' => 'Le nom du pays est trop court.',
                        'maxMessage' => 'Le nom du pays est trop long.',
                    ]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone de contact pour transporteur',
                'attr' => ['placeholder' => '+33 6 12 34 56 78', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le numéro de téléphone est obligatoire.']),
                    new Regex([
                        'pattern' => '/^(?:\+\d{1,3}\s?)?(?:\d[\s.-]?){8,20}$/',
                        'message' => 'Veuillez saisir un numéro de téléphone valide.',
                    ]),
                ],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Carte Bancaire Sécurisée (Visa / Mastercard)' => 'CARD',
                    'Stripe Checkout Instantané' => 'STRIPE',
                    'Virement Bancaire Garanti' => 'TRANSFER',
                ],
                'expanded' => true,
                'data' => 'CARD',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez choisir un mode de paiement.']),
                    new Choice([
                        'choices' => ['CARD', 'STRIPE', 'TRANSFER'],
                        'message' => 'Veuillez choisir un mode de paiement valide.',
                    ]),
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Instructions particulières pour la livraison (optionnel)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Étage, digicode, horaires de présence...', 'class' => 'form-control'],
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Les instructions de livraison sont trop longues.',
                    ]),
                ],
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
