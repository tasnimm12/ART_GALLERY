<?php

namespace App\Form\Admin;

use App\Entity\Artwork;
use App\Entity\Auction;
use App\Repository\ArtworkRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Type as TypeConstraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AuctionLotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('artwork', EntityType::class, [
                'class' => Artwork::class,
                'choice_label' => function (Artwork $artwork) {
                    return sprintf('%s — Par %s (%s)', $artwork->getTitle(), $artwork->getArtist()->getName(), $artwork->getStatus());
                },
                'query_builder' => function (ArtworkRepository $repo) {
                    return $repo->createQueryBuilder('a')
                        ->where('a.status = :status')
                        ->setParameter('status', 'AVAILABLE')
                        ->orderBy('a.title', 'ASC');
                },
                'label' => 'Œuvre d\'art associée',
                'placeholder' => 'Sélectionnez une œuvre disponible',
                'required' => true,
                // Server-side French message when value is missing or invalid
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner une œuvre disponible.']),
                ],
                'invalid_message' => 'Veuillez sélectionner une œuvre disponible.',
            ])
            ->add('startingPrice', NumberType::class, [
                'label' => 'Prix de départ (€)',
                'scale' => 2,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez définir un prix de départ.']),
                    new GreaterThan(['value' => 0, 'message' => 'Le prix de départ doit être supérieur à 0.']),
                ],
            ])
            ->add('reservePrice', NumberType::class, [
                'label' => 'Prix de réserve (€) [Optionnel]',
                'scale' => 2,
                'required' => false,
                'help' => 'Prix minimal secret pour valider la vente.',
                // If present, must be > 0. Null (empty) is allowed.
                'constraints' => [
                    new GreaterThan(['value' => 0, 'message' => 'Le prix de réserve doit être supérieur à 0.']),
                ],
            ])
            ->add('minIncrement', NumberType::class, [
                'label' => 'Incrément minimum (€)',
                'scale' => 2,
                'data' => 10.00,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez définir un incrément minimum.']),
                    new GreaterThan(['value' => 0, 'message' => 'L\'incrément minimum doit être supérieur à 0.']),
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Date & Heure d\'ouverture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                // Ensure server receives a DateTimeImmutable instance
                'constraints' => [
                    new TypeConstraint(['type' => \DateTimeImmutable::class, 'message' => 'La date d\'ouverture doit être une date valide.']),
                ],
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date & Heure de clôture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'constraints' => [
                    new TypeConstraint(['type' => \DateTimeImmutable::class, 'message' => 'La date de clôture doit être une date valide.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Auction::class,
            // Class-level validations for cross-field rules
            'constraints' => [
                new Callback([$this, 'validate']),
            ],
        ]);
    }

    /**
     * Class-level validation for cross-field business rules:
     *  - reservePrice >= startingPrice (if reservePrice provided)
     *  - endDate > startDate
     *  - when creating a new Auction (no id), startDate must be in the future
     */
    public function validate(Auction $auction, ExecutionContextInterface $context): void
    {
        // Prices
        $starting = $auction->getStartingPrice();
        $reserve = $auction->getReservePrice();

        if ($reserve !== null) {
            // Ensure numeric
            if (!is_numeric($reserve)) {
                $context->buildViolation('Le prix de réserve doit être une valeur numérique.')
                    ->atPath('reservePrice')
                    ->addViolation();
            } else {
                // GreaterThan constraint on the field will catch <= 0, but double-check for robustness
                if ($reserve <= 0) {
                    $context->buildViolation('Le prix de réserve doit être supérieur à 0.')
                        ->atPath('reservePrice')
                        ->addViolation();
                }

                if ($starting !== null && is_numeric($starting) && $reserve < $starting) {
                    $context->buildViolation('Le prix de réserve doit être supérieur ou égal au prix de départ.')
                        ->atPath('reservePrice')
                        ->addViolation();
                }
            }
        }

        // Dates
        $start = $auction->getStartDate();
        $end = $auction->getEndDate();

        // Ensure DateTimeImmutable types (field-level TypeConstraint should normally handle this)
        if ($start !== null && !($start instanceof \DateTimeImmutable)) {
            $context->buildViolation('La date d\'ouverture doit être une date valide.')
                ->atPath('startDate')
                ->addViolation();
        }

        if ($end !== null && !($end instanceof \DateTimeImmutable)) {
            $context->buildViolation('La date de clôture doit être une date valide.')
                ->atPath('endDate')
                ->addViolation();
        }

        if ($start instanceof \DateTimeImmutable && $end instanceof \DateTimeImmutable) {
            // end must be strictly greater than start
            if ($end <= $start) {
                $context->buildViolation('La date de clôture doit être postérieure à la date d\'ouverture.')
                    ->atPath('endDate')
                    ->addViolation();
            }
        }

        // Start date must be in the future when creating a new Auction (no id)
        $isNew = true;
        if (method_exists($auction, 'getId')) {
            $isNew = $auction->getId() === null;
        }

        if ($isNew && $start instanceof \DateTimeImmutable) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
            if ($start <= $now) {
                $context->buildViolation('La date d\'ouverture doit être ultérieure à la date actuelle.')
                    ->atPath('startDate')
                    ->addViolation();
            }
        }
    }
}
