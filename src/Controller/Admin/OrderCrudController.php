<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setSearchFields(['orderNumber', 'user.email', 'user.firstName', 'user.lastName'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/edit', 'admin/order/edit_easyadmin.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('orderNumber', 'N° Commande');
        yield AssociationField::new('user', 'Client');
        yield MoneyField::new('totalAmount', 'Montant Total')->setCurrency('EUR')->setStoredAsCents(false);
        yield ChoiceField::new('source', 'Origine')
            ->setChoices([
                'Vente Directe' => 'DIRECT',
                'Enchère' => 'AUCTION',
            ])
            ->renderAsBadges([
                'DIRECT' => 'info',
                'AUCTION' => 'primary',
            ]);
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Payée' => 'PAID',
                'En attente' => 'PENDING',
                'En attente de paiement' => 'PENDING_PAYMENT',
                'Annulée' => 'CANCELLED',
                'Remboursée' => 'REFUNDED',
            ])
            ->renderAsBadges([
                'PAID' => 'success',
                'PENDING' => 'warning',
                'PENDING_PAYMENT' => 'warning',
                'CANCELLED' => 'danger',
                'REFUNDED' => 'dark',
            ]);
        yield DateTimeField::new('createdAt', 'Date Commande')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW);
    }
}
