<?php

namespace App\Tests\Admin;

use App\Entity\Artwork;
use App\Entity\Auction;
use App\Repository\ArtworkRepository;
use App\Repository\AuctionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminAccessTest extends WebTestCase
{
    public function testAnonymousAccessToAdminIsRedirected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/login');

        $client->request('GET', '/admin/auctions');
        $this->assertResponseRedirects('/login');
    }

    public function testClientUserDeniedFromAdmin(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $clientUser = $userRepo->findOneBy(['email' => 'client@aag.com']);

        $this->assertNotNull($clientUser);
        $client->loginUser($clientUser);

        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin/auctions');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessDashboardAndAuctionsList(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $adminUser = $userRepo->findOneBy(['email' => 'admin@aag.com']);

        $this->assertNotNull($adminUser);
        $client->loginUser($adminUser);

        $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/admin/auctions');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Gestion des Lots d\'Enchères');
    }

    public function testAdminCanAccessNewAuctionLotForm(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $adminUser = $userRepo->findOneBy(['email' => 'admin@aag.com']);

        $this->assertNotNull($adminUser);
        $client->loginUser($adminUser);

        $client->request('GET', '/admin/auctions/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Créer un Lot d\'Enchère');
    }

    public function testAdminCanAccessLiveAuctionMonitor(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $auctionRepo = static::getContainer()->get(AuctionRepository::class);

        $adminUser = $userRepo->findOneBy(['email' => 'admin@aag.com']);
        $auction = $auctionRepo->findOneBy([]);

        if ($auction === null) {
            $this->markTestSkipped('Aucune enchère en base.');
        }

        $this->assertNotNull($adminUser);
        $client->loginUser($adminUser);

        $client->request('GET', '/admin/auctions/' . $auction->getId() . '/live');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.live-badge', 'SALLE DE CONTRÔLE LIVE');
    }

    public function testEasyAdminCrudPagesAccessibleByAdmin(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $adminUser = $userRepo->findOneBy(['email' => 'admin@aag.com']);

        $this->assertNotNull($adminUser);
        $client->loginUser($adminUser);

        $crudUrls = [
            '/admin/category',
            '/admin/user',
            '/admin/artwork',
            '/admin/artist',
            '/admin/order',
            '/admin/payment',
            '/admin/notification',
            '/admin/audit-log',
            '/admin/artwork/new',
            '/admin/artist/new',
            '/admin/category/new',
        ];

        foreach ($crudUrls as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful(sprintf('Failed to load EasyAdmin CRUD page: %s', $url));
        }
    }
}

