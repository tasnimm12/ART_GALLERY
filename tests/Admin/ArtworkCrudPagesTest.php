<?php

namespace App\Tests\Admin;

use App\Repository\ArtworkRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArtworkCrudPagesTest extends WebTestCase
{
    public function testAdminCanOpenArtworkIndexAndNewForm(): void
    {
        $client = $this->createAdminClient();

        $client->request('GET', '/admin/artwork');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body.ea-index');
        $columns = $client->getCrawler()
            ->filter('#main .datagrid thead th[data-column]')
            ->each(static fn ($header): ?string => $header->attr('data-column'));
        self::assertSame([
            'id',
            'title',
            'photo',
            'artist',
            'category',
            'technique',
            'dimensions',
            'colors',
            'price',
            'status',
            'createdAt',
        ], $columns);
        self::assertSelectorExists('.action-filters-button');
    }

    public function testAdminCanOpenArtworkNewForm(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin/artwork/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-header h1', 'Ajouter une Œuvre');
        self::assertSelectorExists('[id$="_artist"]');
        self::assertSelectorExists('[id$="_category"]');
        self::assertSelectorExists('[id$="_colors"]');
        self::assertSelectorExists('[data-analyze-uploaded-colors]');
        self::assertSelectorExists('form.artwork-form[data-color-analysis-url]');
        self::assertSelectorExists('.upload-area input[type="file"]');
        self::assertSelectorExists('[data-artwork-image-collection]');
        self::assertSelectorExists('[id$="_price"]');
        self::assertSelectorExists('[id$="_status"]');
    }

    public function testAdminCanOpenArtworkEditForm(): void
    {
        $client = $this->createAdminClient();
        $artwork = static::getContainer()->get(ArtworkRepository::class)->findOneBy([]);
        self::assertNotNull($artwork);

        $client->request('GET', sprintf('/admin/artwork/%d/edit', $artwork->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-header h1', "Modifier l'Œuvre");
        self::assertSelectorExists('[id$="_colors"]');
        self::assertSelectorExists('[id$="_price"]');
        self::assertSelectorExists('[id$="_status"]');
    }

    public function testAdminCanOpenArtworkDetailPage(): void
    {
        $client = $this->createAdminClient();
        $artwork = static::getContainer()->get(ArtworkRepository::class)->findOneBy([]);
        self::assertNotNull($artwork);

        $client->request('GET', sprintf('/admin/artwork/%d', $artwork->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body.ea-detail');
    }

    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@aag.com']);
        self::assertNotNull($admin);
        $admin->setRoles(['ROLE_ADMIN']);
        $client->loginUser($admin);

        return $client;
    }
}
