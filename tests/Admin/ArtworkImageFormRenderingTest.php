<?php

namespace App\Tests\Admin;

use App\Form\ArtworkImageType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

class ArtworkImageFormRenderingTest extends KernelTestCase
{
    public function testImageCollectionThemeRendersPrototypeAndAddControl(): void
    {
        self::bootKernel();

        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->createBuilder(FormType::class, null, ['csrf_protection' => false])
            ->add('images', CollectionType::class, [
                'entry_type' => ArtworkImageType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype_name' => '__name__',
                'entry_options' => ['label' => false],
                'attr' => [
                    'class' => 'artwork-images-list',
                    'data-artwork-image-collection' => '',
                ],
            ])
            ->getForm()
            ->createView();

        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->createTemplate(
            '{% form_theme form with ["@EasyAdmin/crud/form_theme.html.twig", "@EasyAdmin/symfony-form-themes/form_div_layout.html.twig"] only %}{% form_theme form.images with "admin/artwork/image_collection_theme.html.twig" %}{{ form_start(form) }}{{ form_row(form.images) }}{{ form_end(form) }}',
        )->render(['form' => $form]);

        self::assertStringContainsString('data-artwork-image-collection', $html);
        self::assertStringContainsString('data-prototype=', $html);
        self::assertStringContainsString('data-artwork-image-add', $html);
        self::assertSame(1, substr_count($html, 'data-prototype='));
    }
}
