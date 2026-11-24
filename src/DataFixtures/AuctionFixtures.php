<?php

namespace App\DataFixtures;

use App\Entity\Auction;
use App\Entity\Artwork;
use App\Entity\Bid;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AuctionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $artworkRepo = $manager->getRepository(Artwork::class);
        $userRepo    = $manager->getRepository(User::class);

        $artworks = $artworkRepo->findAll();
        $client   = $userRepo->findOneBy(['email' => 'client@aag.com']);
        $admin    = $userRepo->findOneBy(['email' => 'admin@aag.com']);

        if (empty($artworks) || !$client) {
            return;
        }

        $now = new \DateTimeImmutable();

        // ─── 1. Active auction (started 1h ago, ends in 3h) ─────────────────
        if (isset($artworks[0])) {
            $aw = $artworks[0];
            $aw->setStatus('AUCTION');

            $auction1 = new Auction();
            $auction1->setArtwork($aw)
                ->setStartDate($now->modify('-1 hour'))
                ->setEndDate($now->modify('+3 hours'))
                ->setStartingPrice('1500.00')
                ->setReservePrice('3000.00')
                ->setMinIncrement('50.00')
                ->setCurrentPrice('1700.00')
                ->setStatus('ACTIVE')
                ->setWinner($client);

            $manager->persist($auction1);

            // Add some bids
            $bidsData = [
                ['user' => $client, 'amount' => '1500.00', 'ago' => '-50 minutes', 'status' => 'OUTBID'],
                ['user' => $admin,  'amount' => '1600.00', 'ago' => '-35 minutes', 'status' => 'OUTBID'],
                ['user' => $client, 'amount' => '1700.00', 'ago' => '-10 minutes', 'status' => 'VALID'],
            ];

            foreach ($bidsData as $bd) {
                $bid = new Bid();
                $bid->setAuction($auction1)
                    ->setUser($bd['user'])
                    ->setAmount($bd['amount'])
                    ->setStatus($bd['status']);
                // Force createdAt via reflection for fixture realism
                $ref = new \ReflectionProperty(Bid::class, 'createdAt');
                $ref->setAccessible(true);
                $ref->setValue($bid, $now->modify($bd['ago']));
                $manager->persist($bid);
            }
        }

        // ─── 2. Second active auction (ends in 2 minutes — anti-snipe demo) ──
        if (isset($artworks[1])) {
            $aw2 = $artworks[1];
            $aw2->setStatus('AUCTION');

            $auction2 = new Auction();
            $auction2->setArtwork($aw2)
                ->setStartDate($now->modify('-2 hours'))
                ->setEndDate($now->modify('+5 minutes'))
                ->setStartingPrice('800.00')
                ->setMinIncrement('25.00')
                ->setCurrentPrice('950.00')
                ->setStatus('ACTIVE')
                ->setWinner($admin);

            $manager->persist($auction2);

            $bid2 = new Bid();
            $bid2->setAuction($auction2)
                ->setUser($admin)
                ->setAmount('950.00')
                ->setStatus('VALID');
            $manager->persist($bid2);
        }

        // ─── 3. Upcoming auction (starts tomorrow) ────────────────────────────
        if (isset($artworks[2])) {
            $aw3 = $artworks[2];

            $auction3 = new Auction();
            $auction3->setArtwork($aw3)
                ->setStartDate($now->modify('+1 day'))
                ->setEndDate($now->modify('+4 days'))
                ->setStartingPrice('2500.00')
                ->setReservePrice('4500.00')
                ->setMinIncrement('100.00')
                ->setStatus('SCHEDULED');

            $manager->persist($auction3);
        }

        // ─── 4. Closed auction (ended yesterday) ──────────────────────────────
        if (isset($artworks[3])) {
            $aw4 = $artworks[3];
            $aw4->setStatus('SOLD');

            $auction4 = new Auction();
            $auction4->setArtwork($aw4)
                ->setStartDate($now->modify('-5 days'))
                ->setEndDate($now->modify('-1 day'))
                ->setStartingPrice('3000.00')
                ->setMinIncrement('100.00')
                ->setCurrentPrice('4200.00')
                ->setStatus('ENDED')
                ->setClosedAt($now->modify('-1 day'))
                ->setWinner($client);

            $manager->persist($auction4);

            $bid4 = new Bid();
            $bid4->setAuction($auction4)
                ->setUser($client)
                ->setAmount('4200.00')
                ->setStatus('VALID');
            $manager->persist($bid4);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CatalogFixtures::class,
        ];
    }
}
