<?php

namespace App\Controller\Admin;

use App\Entity\Artwork;
use App\Entity\ArtworkImage;
use App\Form\ArtworkImageType;
use App\Message\AnalyzeArtworkColors;
use App\Service\ArtworkDeletionService;
use App\Service\ArtworkImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Count;
use Psr\Log\LoggerInterface;

#[IsGranted('ROLE_ADMIN')]
class ArtworkCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ArtworkImageStorage $imageStorage,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Artwork::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Œuvre d\'art')
            ->setEntityLabelInPlural('Œuvres d\'art')
            ->setSearchFields(['title', 'technique', 'dimensions', 'artist.name', 'category.name'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/index', 'admin/artwork/index.html.twig')
            ->overrideTemplate('crud/new', 'admin/artwork/new_easyadmin.html.twig')
            ->overrideTemplate('crud/edit', 'admin/artwork/edit_easyadmin.html.twig')
            ->overrideTemplate('crud/detail', 'admin/artwork/detail_easyadmin.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm()
            ->setCssClass('field-artwork-id');
        yield TextField::new('title', 'Titre')
            ->setCssClass('field-artwork-title')
            ->setColumns(12)
            ->setFormTypeOptions([
                'trim' => true,
                'required' => true,
                'attr' => ['maxlength' => 255, 'placeholder' => 'Titre de l\'œuvre']
            ])
            ->setRequired(true);

        yield ImageField::new('photo', 'Photo')
            ->setCssClass('field-artwork-photo')
            ->setBasePath('/uploads/artworks')
            ->setUploadDir('public/uploads/artworks')
            ->setRequired(false)
            ->setFormTypeOptions([
                'required' => false,
                'data_class' => null,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'empty_data' => null,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Callback(function ($value, \Symfony\Component\Validator\Context\ExecutionContextInterface $context) {
                        if ($value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                            $mime = $value->getMimeType() ?: $value->getClientMimeType();
                            if (!in_array($mime, $allowed, true)) {
                                $context->buildViolation('Veuillez sélectionner une image JPG, PNG ou WebP.')->addViolation();
                            }
                        }
                    })
                ]
            ])
            ->setUploadedFileNamePattern('[randomhash].[extension]');

        $imageCollection = CollectionField::new('images', 'Galerie d’images')
            ->setEntryType(ArtworkImageType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOptions([
                'by_reference' => false,
                'prototype_name' => '__name__',
                'entry_options' => ['label' => false],
                'attr' => [
                    'class' => 'artwork-images-list',
                ],
                'row_attr' => ['data-artwork-image-collection' => ''],
                'constraints' => [
                    new Count(
                        min: $pageName === Crud::PAGE_NEW ? 4 : null,
                        max: 5,
                        minMessage: 'Ajoutez au moins 4 images à cette nouvelle œuvre.',
                        maxMessage: 'Une œuvre ne peut pas avoir plus de 5 images.',
                    ),
                ],
            ]);
        yield $imageCollection->hideOnIndex()->hideOnDetail();

        yield AssociationField::new('artist', 'Artiste')
            ->setCssClass('field-artwork-artist');
        yield AssociationField::new('category', 'Catégorie')
            ->setCssClass('field-artwork-category');
        yield TextField::new('technique', 'Technique')
            ->setCssClass('field-artwork-technique')
            ->setColumns(6);
        yield TextField::new('dimensions', 'Dimensions')
            ->setCssClass('field-artwork-dimensions')
            ->setColumns(6)
            ->setFormTypeOptions([
                'trim' => true,
                'required' => false,
                'attr' => ['maxlength' => 255, 'placeholder' => '120 x 90 cm']
            ]);

        yield AssociationField::new('colors', 'Couleurs principales')
            ->setCssClass('field-artwork-colors')
            ->setFormTypeOptions(['by_reference' => false])
            ->setHelp('Sélectionnez les couleurs dominantes visibles dans cette œuvre.');
        yield MoneyField::new('price', 'Prix')
            ->setCssClass('field-artwork-price')
            ->setTextAlign('right')
            ->setCurrency('EUR')
            ->setStoredAsCents(false);
        yield ChoiceField::new('status', 'Statut')
            ->setCssClass('field-artwork-status')
            ->setChoices([
                'Disponible' => 'AVAILABLE',
                'En enchère' => 'AUCTION',
                'Réservé' => 'RESERVED',
                'Vendu' => 'SOLD',
            ])
            ->setRequired(true)
            ->setFormTypeOptions([
                'placeholder' => false,
                'empty_data' => static function (FormInterface $form, mixed $viewData): string {
                    $artwork = $form->getParent()?->getData();

                    return $artwork instanceof Artwork ? $artwork->getStatus() : 'AVAILABLE';
                },
            ])
            ->renderAsBadges([
                'AVAILABLE' => 'success',
                'AUCTION' => 'primary',
                'RESERVED' => 'warning',
                'SOLD' => 'secondary',
            ]);
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield DateField::new('yearCreated', 'Date création')->hideOnIndex();
        yield BooleanField::new('dailyFeatured', 'Sélection manuelle comme œuvre du jour')
            ->hideOnIndex()
            ->setHelp('Une œuvre sélectionnée ici reste en vedette jusqu’à ce que vous choisissiez une autre œuvre.');
        yield DateTimeField::new('createdAt', 'Créé le')
            ->setCssClass('field-artwork-created-at')
            ->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title', 'Titre'))
            ->add(EntityFilter::new('artist', 'Artiste'))
            ->add(EntityFilter::new('category', 'Catégorie'))
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'Disponible' => 'AVAILABLE',
                'En enchère' => 'AUCTION',
                'Réservé' => 'RESERVED',
                'Vendu' => 'SOLD',
            ]))
            ->add(NumericFilter::new('price', 'Prix'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action
                ->setLabel('Ajouter une œuvre')
                ->setIcon('fa fa-plus')
                ->addCssClass('btn btn-primary'))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action): Action => $action
                ->setLabel('Voir')
                ->setIcon('fa fa-eye'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action): Action => $action
                ->setLabel('Modifier')
                ->setIcon('fa fa-pen'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action): Action => $action
                ->setLabel('Supprimer')
                ->setIcon('fa fa-trash')
                ->addCssClass('text-danger'))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn (Action $action): Action => $action
                ->setLabel('Modifier')
                ->setIcon('fa fa-pen'))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $action): Action => $action
                ->setLabel('Supprimer')
                ->setIcon('fa fa-trash')
                ->addCssClass('text-danger'))
            ->update(Crud::PAGE_DETAIL, Action::INDEX, static fn (Action $action): Action => $action
                ->setLabel('Retour à la liste')
                ->setIcon('fa fa-arrow-left'))
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE])
            ->reorder(Crud::PAGE_DETAIL, [Action::EDIT, Action::DELETE, Action::INDEX]);
    }

    #[Route('/admin/artwork/delete-all', name: 'admin_artwork_delete_all', methods: ['POST'])]
    public function deleteAll(
        Request $request,
        ArtworkDeletionService $artworkDeletionService,
        AdminUrlGenerator $adminUrlGenerator,
        LoggerInterface $logger,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-all-artworks', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $result = $artworkDeletionService->deleteAll();
            if ($result['deleted'] === 0) {
                $this->addFlash('info', 'Aucune œuvre à supprimer.');
            } elseif ($result['failedImageCleanup'] > 0) {
                $this->addFlash('warning', sprintf(
                    '%d œuvre(s) supprimée(s). %d fichier(s) image n’ont pas pu être supprimés ; consultez les journaux.',
                    $result['deleted'],
                    $result['failedImageCleanup'],
                ));
            } else {
                $this->addFlash('success', sprintf('%d œuvre(s) supprimée(s). Les commandes et enchères historiques ont été conservées.', $result['deleted']));
            }
        } catch (\Throwable $exception) {
            $logger->error('Bulk artwork deletion failed; the database transaction was rolled back.', ['exception' => $exception]);
            $this->addFlash('danger', 'La suppression des œuvres a échoué. Aucune modification en base n’a été conservée ; consultez les journaux.');
        }

        $indexUrl = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($indexUrl);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Artwork) {
            $this->prepareImages($entityInstance);
            $this->keepSingleDailySelection($entityManager, $entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
        $this->queueColorAnalysis($entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Artwork) {
            $this->prepareImages($entityInstance);
            $this->keepSingleDailySelection($entityManager, $entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
        $this->queueColorAnalysis($entityInstance);
    }

    private function queueColorAnalysis(Artwork $artwork): void
    {
        if ($artwork->getId() !== null && $this->hasImagePath($artwork)) {
            $this->messageBus->dispatch(new AnalyzeArtworkColors($artwork->getId()));
        }
    }

    private function hasImagePath(Artwork $artwork): bool
    {
        if ($artwork->getPhoto() !== null && $artwork->getPhoto() !== '') {
            return true;
        }

        foreach ($artwork->getImages() as $image) {
            if ($image->getFilePath() !== null && $image->getFilePath() !== '') {
                return true;
            }
        }

        return false;
    }

    private function keepSingleDailySelection(EntityManagerInterface $entityManager, Artwork $artwork): void
    {
        if (!$artwork->isDailyFeatured()) {
            return;
        }

        $queryBuilder = $entityManager->createQueryBuilder()
            ->update(Artwork::class, 'a')
            ->set('a.dailyFeatured', ':notFeatured')
            ->where('a.dailyFeatured = :featured')
            ->setParameter('notFeatured', false)
            ->setParameter('featured', true);

        if ($artwork->getId() !== null) {
            $queryBuilder
                ->andWhere('a.id != :currentArtwork')
                ->setParameter('currentArtwork', $artwork->getId());
        }

        $queryBuilder->getQuery()->execute();
    }

    private function prepareImages(Artwork $artwork): void
    {
        $images = array_values($artwork->getImages()->toArray());
        if ($images === []) {
            return;
        }

        $primaryImage = null;
        foreach ($images as $image) {
            if ($image->isPrimary()) {
                $primaryImage ??= $image;
            }
        }

        $primaryImage ??= $images[0];

        foreach ($images as $position => $image) {
            $image->setArtwork($artwork);
            $image->setDisplayOrder($position);
            $image->setIsPrimary($image === $primaryImage);
            $this->imageStorage->store($artwork, $image);
        }
    }
}
