<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
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

class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Paiement')
            ->setEntityLabelInPlural('Paiements')
            ->setSearchFields(['transactionReference', 'method', 'order.orderNumber'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/edit', 'admin/payment/edit_easyadmin.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('order', 'Commande');
        yield TextField::new('transactionReference', 'Réf. Transaction');
        yield MoneyField::new('amount', 'Montant')->setCurrency('EUR')->setStoredAsCents(false);
        yield ChoiceField::new('method', 'Moyen de Paiement')
            ->setChoices([
                'Carte Bancaire' => 'CARD',
                'Virement Bancaire' => 'BANK_TRANSFER',
                'PayPal' => 'PAYPAL',
            ]);
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Validé' => 'PAID',
                'En attente' => 'PENDING',
                'Échoué' => 'FAILED',
                'Remboursé' => 'REFUNDED',
            ])
            ->renderAsBadges([
                'PAID' => 'success',
                'PENDING' => 'warning',
                'FAILED' => 'danger',
                'REFUNDED' => 'dark',
            ]);
        yield DateTimeField::new('paidAt', 'Payé le')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Date')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW);
    }
}
