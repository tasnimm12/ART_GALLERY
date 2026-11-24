<?php

namespace App\Form;

use App\DTO\ArtworkSearchDTO;
use App\Entity\Artist;
use App\Entity\ArtworkColor;
use App\Entity\Category;
use App\Repository\ArtworkColorRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArtworkSearchType extends AbstractType
{
    public function __construct(private readonly ArtworkColorRepository $colorRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Rechercher par titre, mot-clé ou artiste...',
                    'class' => 'form-control search-input',
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => 'Toutes les catégories',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('artist', EntityType::class, [
                'class' => Artist::class,
                'choice_label' => 'name',
                'label' => 'Artiste',
                'required' => false,
                'placeholder' => 'Tous les artistes',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('minPrice', NumberType::class, [
                'label' => 'Prix Min (€)',
                'required' => false,
                'attr' => ['placeholder' => '0', 'class' => 'form-control', 'min' => 0],
            ])
            ->add('maxPrice', NumberType::class, [
                'label' => 'Prix Max (€)',
                'required' => false,
                'attr' => ['placeholder' => '10000', 'class' => 'form-control', 'min' => 0],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Disponibilité',
                'required' => false,
                'placeholder' => 'Tous les statuts',
                'choices' => [
                    'Disponible à l\'achat' => 'AVAILABLE',
                    'En enchère' => 'AUCTION',
                    'Réservé' => 'RESERVED',
                    'Vendu' => 'SOLD',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('color', ChoiceType::class, [
                'label' => 'Couleur',
                'required' => false,
                'placeholder' => 'Toutes les couleurs',
                'choices' => $this->getColorChoices(),
                'attr' => ['class' => 'color-filter-select'],
            ])
        ;
    }

    /**
     * @return array<string, string>
     */
    private function getColorChoices(): array
    {
        $choices = [];
        foreach ($this->colorRepository->findBy([], ['name' => 'ASC']) as $color) {
            if ($color instanceof ArtworkColor) {
                $choices[$color->getName()] = $color->getSlug();
            }
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArtworkSearchDTO::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
