<?php

namespace App\Controller\Admin;

use App\Entity\Artist;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\File;

class ArtistCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Artist::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Artiste')
            ->setEntityLabelInPlural('Artistes')
            ->setSearchFields(['name', 'biography'])
            ->setDefaultSort(['name' => 'ASC'])
            ->overrideTemplate('crud/new', 'admin/artist/new_easyadmin.html.twig')
            ->overrideTemplate('crud/edit', 'admin/artist/edit_easyadmin.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom')
            ->setFormTypeOptions([
                'trim' => true,
                'required' => true,
                'attr' => ['maxlength' => 100, 'placeholder' => 'Nom de l\'artiste']
            ])
            ->setRequired(true);

        yield ImageField::new('photo', 'Photo')
            ->setBasePath('/uploads/artists')
            ->setUploadDir('public/uploads/artists')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setFileConstraints(new File([
                'maxSize' => '5M',
                'maxSizeMessage' => 'La photo ne doit pas dépasser 5 MB.',
                'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                'mimeTypesMessage' => 'Sélectionnez une image JPG, JPEG, PNG ou WEBP valide.',
                'notFoundMessage' => 'Le fichier sélectionné est introuvable. Veuillez le sélectionner à nouveau.',
            ]))
            ->setRequired(false)
            ->setFormTypeOptions([
                'required' => false,
                'attr' => [
                    'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp'
                ],
            ]);
        yield ChoiceField::new('country', 'Pays')
            ->setChoices([
                'France' => 'France',
                'Belgique' => 'Belgique',
                'Suisse' => 'Suisse',
                'Espagne' => 'Espagne',
                'Italie' => 'Italie',
                'Allemagne' => 'Allemagne',
                'Portugal' => 'Portugal',
                'Pays-Bas' => 'Pays-Bas',
                'Maroc' => 'Maroc',
                'Tunisie' => 'Tunisie',
                'Algérie' => 'Algérie',
                'Canada' => 'Canada',
                'États-Unis' => 'États-Unis',
                'Brésil' => 'Brésil',
                'Argentine' => 'Argentine',
                'Japon' => 'Japon',
                'Corée du Sud' => 'Corée du Sud',
                'Chine' => 'Chine',
                'Royaume-Uni' => 'Royaume-Uni',
                'Norvège' => 'Norvège',
                'Suède' => 'Suède',
                'Finlande' => 'Finlande',
                'Autre' => 'Autre',
            ]);
        yield TextField::new('website', 'Site web')->setRequired(false);
        yield TextareaField::new('biography', 'Biographie')
            ->setFormTypeOptions([
                'trim' => true,
                'required' => false,
                'attr' => ['maxlength' => 2000, 'placeholder' => 'Biographie de l\'artiste (optionnel)']
            ]);
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
