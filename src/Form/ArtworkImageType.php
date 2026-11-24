<?php

namespace App\Form;

use App\Entity\ArtworkImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ArtworkImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Fichier image',
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'data-artwork-image-file' => '',
                ],
                'constraints' => [
                    new Image(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Le fichier doit être au format JPG, PNG ou WebP.',
                        maxSizeMessage: 'Chaque image doit peser au maximum 5 Mo.',
                    ),
                ],
            ])
            ->add('altText', TextType::class, [
                'label' => 'Texte alternatif',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('displayOrder', HiddenType::class, [
                'attr' => ['data-artwork-image-order' => ''],
            ])
            ->add('isPrimary', CheckboxType::class, [
                'label' => 'Image principale',
                'required' => false,
                'attr' => ['data-artwork-image-primary' => ''],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArtworkImage::class,
        ]);
    }
}
