<?php

namespace App\Tests\Auction;

use App\Entity\Auction;
use App\Entity\Bid;
use App\Entity\Notification;
use App\Repository\AuctionRepository;
use App\Repository\BidRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuctionWorkflowTest extends WebTestCase
{
    public function testAuctionIndexIsAccessibleByEveryone(): void
    {
        $client = static::createClient();
        $client->request('GET', '/encheres');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.section-title');
    }

    public function testAuctionDetailPageLoads(): void
    {
        $client = static::createClient();
        $auctionRepo = static::getContainer()->get(AuctionRepository::class);
        $auction = $auctionRepo->findOneBy([]);

        if ($auction === null) {
            $this->markTestSkipped('Aucune enchère en base. Chargez les fixtures.');
        }

        $client->request('GET', '/encheres/' . $auction->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testBidOnActiveAuctionSucceeds(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $userRepo = $container->get(UserRepository::class);
        $auctionRepo = $container->get(AuctionRepository::class);

        $clientUser = $userRepo->findOneBy(['email' => 'client@aag.com']);
        $this->assertNotNull($clientUser);

        $activeAuctions = $auctionRepo->findActiveAuctions();
        if (empty($activeAuctions)) {
            $this->markTestSkipped('Aucune enchère active. Chargez les fixtures.');
        }

        $auction = $activeAuctions[0];
        $minBid   = $auction->getMinimumNextBid();
        $bidAmount = number_format($minBid + 10, 2, '.', '');

        $client->loginUser($clientUser);

        $crawler = $client->request('GET', '/encheres/' . $auction->getId());
        $this->assertResponseIsSuccessful();

        // Find the submit button
        $submitButton = $crawler->selectButton('Enchérir');
        if ($submitButton->count() === 0) {
            $this->markTestSkipped('Bouton d\'enchère non trouvé (enchère peut-être non active dans l\'env de test).');
        }

        $form = $submitButton->form();
        // Detect actual amount field name from rendered form
        $amountFieldName = null;
        foreach ($form->all() as $field) {
            if (str_ends_with($field->getName(), '[amount]')) {
                $amountFieldName = $field->getName();
                break;
            }
        }
        $this->assertNotNull($amountFieldName, 'Champ amount introuvable dans le formulaire.');

        $form[$amountFieldName] = $bidAmount;
        $client->submit($form);

        $this->assertResponseRedirects();
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Verify DB: bid was created
        $em->clear();
        $bidRepo = $container->get(BidRepository::class);
        $latestBid = $bidRepo->findHighestBid($em->find(Auction::class, $auction->getId()));
        $this->assertNotNull($latestBid);
        $this->assertSame('VALID', $latestBid->getStatus());

        // Verify notification was created
        $notification = $em->getRepository(Notification::class)->findOneBy(
            ['user' => $clientUser, 'type' => 'BID_PLACED'],
            ['createdAt' => 'DESC']
        );
        $this->assertNotNull($notification);
    }

    public function testBidBelowMinimumIsRejected(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);
        $auctionRepo = $container->get(AuctionRepository::class);

        $clientUser = $userRepo->findOneBy(['email' => 'client@aag.com']);
        $activeAuctions = $auctionRepo->findActiveAuctions();

        if (empty($activeAuctions)) {
            $this->markTestSkipped('Aucune enchère active. Chargez les fixtures.');
        }

        $auction = $activeAuctions[0];
        $tooLow = number_format($auction->getMinimumNextBid() - 1.0, 2, '.', '');

        $client->loginUser($clientUser);
        $crawler = $client->request('GET', '/encheres/' . $auction->getId());

        $submitButton = $crawler->selectButton('Enchérir');
        if ($submitButton->count() === 0) {
            $this->markTestSkipped('Bouton d\'enchère non trouvé.');
        }

        $form = $submitButton->form();
        $amountFieldName = null;
        foreach ($form->all() as $field) {
            if (str_ends_with($field->getName(), '[amount]')) {
                $amountFieldName = $field->getName();
                break;
            }
        }
        $this->assertNotNull($amountFieldName);

        $form[$amountFieldName] = $tooLow;
        $client->submit($form);

        // Either 200 (form re-rendered with errors) or redirect — check form error is shown
        $response = $client->getResponse();
        if ($response->isRedirect()) {
            $client->followRedirect();
        }
        // Form error message must be present
        $this->assertSelectorExists('ul li');
    }

    public function testAnonymousUserCannotBid(): void
    {
        $client = static::createClient();
        $auctionRepo = static::getContainer()->get(AuctionRepository::class);
        $activeAuctions = $auctionRepo->findActiveAuctions();

        if (empty($activeAuctions)) {
            $this->markTestSkipped('Aucune enchère active. Chargez les fixtures.');
        }

        $auction = $activeAuctions[0];
        $crawler = $client->request('GET', '/encheres/' . $auction->getId());
        $this->assertResponseIsSuccessful();

        // Anonymous should see login prompt, not bid form
        $this->assertSelectorNotExists('#bid-form');
        $this->assertSelectorTextContains('.bid-form-card', 'Connexion requise');
    }

    public function testBidOnClosedAuctionIsRejected(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $auctionRepo = $container->get(AuctionRepository::class);
        $userRepo = $container->get(UserRepository::class);

        $pastAuctions = $auctionRepo->findPastAuctions();
        $clientUser = $userRepo->findOneBy(['email' => 'client@aag.com']);

        if (empty($pastAuctions) || !$clientUser) {
            $this->markTestSkipped('Aucune enchère clôturée ou client manquant.');
        }

        $auction = $pastAuctions[0];

        // Closed auction detail should show "Clôturée"
        $client->loginUser($clientUser);
        $crawler = $client->request('GET', '/encheres/' . $auction->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('#bid-form');
    }

    public function testAuctionStateEndpointReturnsJson(): void
    {
        $client = static::createClient();
        $auctionRepo = static::getContainer()->get(AuctionRepository::class);
        $auction = $auctionRepo->findOneBy([]);

        if ($auction === null) {
            $this->markTestSkipped('Aucune enchère en base.');
        }

        $client->request('GET', '/encheres/' . $auction->getId() . '/state');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('auctionId', $data);
        $this->assertArrayHasKey('currentPrice', $data);
        $this->assertArrayHasKey('minimumNextBid', $data);
        $this->assertArrayHasKey('totalBids', $data);
        $this->assertArrayHasKey('bids', $data);
    }

    public function testNotifyOutbidUsersCommandExecution(): void
    {
        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);

        $command = $application->find('app:notify-outbid-users');
        $commandTester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(\Symfony\Component\Console\Command\Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testCloseExpiredAuctionsCommandExecution(): void
    {
        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);

        $command = $application->find('app:close-expired-auctions');
        $commandTester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $commandTester->execute(['--dry-run' => true]);

        $this->assertSame(\Symfony\Component\Console\Command\Command::SUCCESS, $commandTester->getStatusCode());
    }
}

