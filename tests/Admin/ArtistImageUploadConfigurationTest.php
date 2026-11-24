<?php

namespace App\Tests\Admin;

use App\Controller\Admin\ArtistCrudController;
use App\Entity\Artist;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Constraints\File;
use Twig\Environment;

class ArtistImageUploadConfigurationTest extends KernelTestCase
{
    public function testArtistPhotoUsesFileConstraintsAndStoresAUniqueFilename(): void
    {
        $photoField = null;

        foreach ((new ArtistCrudController())->configureFields(Crud::PAGE_NEW) as $field) {
            if ('photo' === $field->getAsDto()->getProperty()) {
                $photoField = $field;
                break;
            }
        }

        self::assertInstanceOf(ImageField::class, $photoField);

        $fieldDto = $photoField->getAsDto();
        $constraints = $fieldDto->getCustomOption(ImageField::OPTION_FILE_CONSTRAINTS);
        $constraints = is_array($constraints) ? $constraints : [$constraints];
        self::assertCount(1, $constraints);
        self::assertInstanceOf(File::class, $constraints[0]);
        self::assertSame(5_000_000, $constraints[0]->maxSize);
        self::assertSame(['image/jpeg', 'image/png', 'image/webp'], $constraints[0]->mimeTypes);
        self::assertArrayNotHasKey('constraints', $fieldDto->getFormTypeOptions());
        self::assertSame('public/uploads/artists', $fieldDto->getCustomOption(ImageField::OPTION_UPLOAD_DIR));
        self::assertSame('[randomhash].[extension]', $fieldDto->getCustomOption(ImageField::OPTION_UPLOADED_FILE_NAME_PATTERN));
        self::assertSame(
            '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
            $fieldDto->getFormTypeOption('attr')['accept'],
        );
    }

    public function testArtistPhotoPropertyStoresOnlyARelativeFilename(): void
    {
        $artist = new Artist();
        $artist->setName('Artiste Test');
        $artist->setPhoto('9f3a2b1c.jpg');

        self::assertSame('9f3a2b1c.jpg', $artist->getPhoto());
    }

    public function testEasyAdminFileUploadRendersMultipartFormAndCreatesUploadDirectory(): void
    {
        self::bootKernel();

        $uploadDirectory = self::getContainer()->getParameter('kernel.project_dir')
            .DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'test-artist-upload-'.bin2hex(random_bytes(8));

        try {
            $formFactory = self::getContainer()->get(FormFactoryInterface::class);
            $form = $formFactory->createBuilder(FormType::class, null, ['csrf_protection' => false])
                ->add('photo', FileUploadType::class, [
                    'upload_dir' => $uploadDirectory,
                    'file_constraints' => new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                    ]),
                ])
                ->getForm()
                ->createView();

            $html = self::getContainer()->get(Environment::class)->createTemplate(
                '{% form_theme form with ["@EasyAdmin/crud/form_theme.html.twig", "@EasyAdmin/symfony-form-themes/form_div_layout.html.twig"] only %}{{ form_start(form) }}{{ form_end(form) }}',
            )->render(['form' => $form]);

            self::assertStringContainsString('enctype="multipart/form-data"', $html);
            self::assertDirectoryExists($uploadDirectory);
        } finally {
            (new Filesystem())->remove($uploadDirectory);
        }
    }
}
