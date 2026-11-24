<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['email', 'firstName', 'lastName', 'phone'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/edit', 'admin/user/edit_easyadmin.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'Email');
        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');
        yield TextField::new('phone', 'Téléphone')->hideOnIndex();
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Actif' => 'ACTIVE',
                'Inactif' => 'INACTIVE',
                'Suspendu' => 'SUSPENDED',
                'Banni' => 'BANNED',
            ])
            ->renderAsBadges([
                'ACTIVE' => 'success',
                'INACTIVE' => 'warning',
                'SUSPENDED' => 'danger',
                'BANNED' => 'dark',
            ]);

        yield ChoiceField::new('role', 'Rôle')
            ->setChoices([
                'Utilisateur (ROLE_USER)' => 'ROLE_USER',
                'Client (ROLE_CLIENT)' => 'ROLE_CLIENT',
                'Administrateur (ROLE_ADMIN)' => 'ROLE_ADMIN',
            ])
            ->renderAsBadges([
                'ROLE_USER' => 'secondary',
                'ROLE_CLIENT' => 'info',
                'ROLE_ADMIN' => 'success',
            ]);
        // Keep roles array visible on index/detail if needed
        yield ArrayField::new('roles')->onlyOnIndex();
        yield DateTimeField::new('emailVerifiedAt', 'Email Vérifié le')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Inscrit le')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('role')->setChoices([
            'Utilisateur' => 'ROLE_USER',
            'Client' => 'ROLE_CLIENT',
            'Administrateur' => 'ROLE_ADMIN',
        ]));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
