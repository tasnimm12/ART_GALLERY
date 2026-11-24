<?php

namespace App\Tests\Entity;

use App\Entity\Artist;
use App\Entity\Artwork;
use App\Entity\Auction;
use App\Entity\OrderItem;
use PHPUnit\Framework\TestCase;

class ArtworkHistorySnapshotTest extends TestCase
{
    public function testOrderItemKeepsPurchaseDescriptionWhenArtworkIsDetached(): void
    {
        $artist = (new Artist())->setName('Mina Duval');
        $artwork = (new Artwork())->setTitle('Lumière abstraite')->setArtist($artist);
        $item = (new OrderItem())->setArtwork($artwork);

        $item->setArtwork(null);

        self::assertNull($item->getArtwork());
        self::assertSame('Lumière abstraite', $item->getArtworkTitle());
        self::assertSame('Mina Duval', $item->getArtistName());
    }

    public function testAuctionKeepsArtworkDescriptionAndCannotBeBidOnAfterDetachment(): void
    {
        $artist = (new Artist())->setName('Mina Duval');
        $artwork = (new Artwork())->setTitle('Lumière abstraite')->setArtist($artist);
        $auction = (new Auction())->setArtwork($artwork);

        $auction->setArtwork(null);

        self::assertNull($auction->getArtwork());
        self::assertSame('Lumière abstraite', $auction->getArtworkTitle());
        self::assertSame('Mina Duval', $auction->getArtistName());
        self::assertFalse($auction->isActive());
        self::assertFalse($auction->isUpcoming());
    }
}
