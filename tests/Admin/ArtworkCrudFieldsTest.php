<?php

namespace App\Tests\Admin;

use App\Controller\Admin\ArtworkCrudController;
use App\Entity\Artwork;
use App\Service\ArtworkImageStorage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Form\FormInterface;

class ArtworkCrudFieldsTest extends TestCase
{
    public function testImageCollectionHasCreationMinimumAndMaximumOnBothForms(): void
    {
        $storage = new ArtworkImageStorage(sys_get_temp_dir(), new AsciiSlugger());
        $controller = new ArtworkCrudController($storage, $this->createMock(MessageBusInterface::class));

        foreach ([Crud::PAGE_NEW, Crud::PAGE_EDIT] as $pageName) {
            $fields = iterator_to_array($controller->configureFields($pageName));
            $imageFields = array_values(array_filter(
                $fields,
                static fn ($field): bool => $field instanceof CollectionField
                    && $field->getAsDto()->getProperty() === 'images',
            ));

            self::assertCount(1, $imageFields);
            $constraints = $imageFields[0]->getAsDto()->getFormTypeOptions()['constraints'];
            self::assertCount(1, $constraints);
            self::assertInstanceOf(Count::class, $constraints[0]);
            self::assertSame(5, $constraints[0]->max);
            self::assertSame($pageName === Crud::PAGE_NEW ? 4 : null, $constraints[0]->min);
        }
    }

    public function testArtworkStatusFieldNeverMapsAnEmptyValueToNull(): void
    {
        $storage = new ArtworkImageStorage(sys_get_temp_dir(), new AsciiSlugger());
        $controller = new ArtworkCrudController($storage, $this->createMock(MessageBusInterface::class));
        $fields = iterator_to_array($controller->configureFields(Crud::PAGE_EDIT));
        $statusField = array_values(array_filter(
            $fields,
            static fn ($field): bool => $field instanceof ChoiceField
                && $field->getAsDto()->getProperty() === 'status',
        ))[0];
        $options = $statusField->getAsDto()->getFormTypeOptions();

        self::assertTrue($options['required']);
        self::assertFalse($options['placeholder']);
        self::assertIsCallable($options['empty_data']);

        $artwork = (new Artwork())->setStatus('AUCTION');
        $parentForm = $this->createMock(FormInterface::class);
        $parentForm->method('getData')->willReturn($artwork);
        $statusForm = $this->createMock(FormInterface::class);
        $statusForm->method('getParent')->willReturn($parentForm);

        self::assertSame('AUCTION', ($options['empty_data'])($statusForm, null));

        $newArtworkForm = $this->createMock(FormInterface::class);
        $newArtworkForm->method('getParent')->willReturn(null);
        self::assertSame('AVAILABLE', ($options['empty_data'])($newArtworkForm, null));
    }
}
