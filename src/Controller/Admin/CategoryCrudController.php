<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setSearchFields(['name', 'slug', 'description'])
            ->setDefaultSort(['name' => 'ASC'])
            ->overrideTemplate('crud/new', 'admin/category/new_easyadmin.html.twig')
            ->overrideTemplate('crud/edit', 'admin/category/edit_easyadmin.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom')
            ->setFormTypeOptions([
                'trim' => true,
                'required' => true,
                'attr' => ['maxlength' => 100, 'data-field' => 'name']
            ]);

        yield SlugField::new('slug', 'Slug')
            ->setTargetFieldName('name')
            ->setFormTypeOptions([
                'required' => true,
                'attr' => ['maxlength' => 150, 'data-field' => 'slug']
            ]);

        yield TextareaField::new('description', 'Description')
            ->setFormTypeOptions([
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'maxlength' => 1000, 'data-field' => 'description']
            ]);
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }
}
