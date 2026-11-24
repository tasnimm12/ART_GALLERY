<?php

namespace App\Tests\Security;

use App\Entity\Artwork;
use App\Entity\Auction;
use App\Entity\Role;
use App\Entity\User;
use App\Security\Voter\AuctionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AuctionVoterTest extends TestCase
{
    private function createSecurityMock(array $grantedRoles = []): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(function ($role) use ($grantedRoles) {
            return in_array($role, $grantedRoles, true);
        });

        return $security;
    }

    private function createTokenMock(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createAuction(string $status = 'ACTIVE', bool $closed = false, bool $isOngoing = true): Auction
    {
        $auction = new Auction();
        $auction->setStatus($status);
        $auction->setStartingPrice('100.00');

        if ($isOngoing) {
            $auction->setStartDate(new \DateTimeImmutable('-1 hour'));
            $auction->setEndDate(new \DateTimeImmutable('+1 hour'));
        } else {
            $auction->setStartDate(new \DateTimeImmutable('-2 hours'));
            $auction->setEndDate(new \DateTimeImmutable('-1 hour'));
        }

        if ($closed) {
            $auction->setClosedAt(new \DateTimeImmutable('-30 minutes'));
            $auction->setStatus('CLOSED');
        }

        return $auction;
    }

    private function createUser(bool $verified = true, string $status = 'ACTIVE'): User
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hash');
        $user->setStatus($status);
        if ($verified) {
            $user->setEmailVerifiedAt(new \DateTimeImmutable());
        }

        return $user;
    }

    public function testViewIsAlwaysAllowed(): void
    {
        $voter = new AuctionVoter($this->createSecurityMock());
        $token = $this->createTokenMock(null);
        $auction = $this->createAuction();

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $auction, ['AUCTION_VIEW']));
    }

    public function testVerifiedClientCanBidOnActiveAuction(): void
    {
        $voter = new AuctionVoter($this->createSecurityMock(['ROLE_CLIENT', 'ROLE_USER']));
        $user = $this->createUser(verified: true, status: 'ACTIVE');
        $token = $this->createTokenMock($user);
        $auction = $this->createAuction(status: 'ACTIVE', closed: false, isOngoing: true);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $auction, ['AUCTION_BID']));
    }

    public function testUnverifiedClientCannotBid(): void
    {
        $voter = new AuctionVoter($this->createSecurityMock(['ROLE_CLIENT', 'ROLE_USER']));
        $user = $this->createUser(verified: false, status: 'ACTIVE');
        $token = $this->createTokenMock($user);
        $auction = $this->createAuction(status: 'ACTIVE', closed: false, isOngoing: true);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $auction, ['AUCTION_BID']));
    }

    public function testClientCannotBidOnClosedAuction(): void
    {
        $voter = new AuctionVoter($this->createSecurityMock(['ROLE_CLIENT', 'ROLE_USER']));
        $user = $this->createUser(verified: true, status: 'ACTIVE');
        $token = $this->createTokenMock($user);
        $auction = $this->createAuction(status: 'CLOSED', closed: true, isOngoing: false);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $auction, ['AUCTION_BID']));
    }

    public function testAdminCanEditOpenAuction(): void
    {
        $voter = new AuctionVoter($this->createSecurityMock(['ROLE_ADMIN', 'ROLE_CLIENT', 'ROLE_USER']));
        $user = $this->createUser();
        $token = $this->createTokenMock($user);
        $auction = $this->createAuction(status: 'ACTIVE', closed: false);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $auction, ['AUCTION_EDIT']));
    }

    public function testAdminCannotEditClosedAuction(): void
    {
        $voter = new AuctionVoter($this->createSecurityMock(['ROLE_ADMIN', 'ROLE_CLIENT', 'ROLE_USER']));
        $user = $this->createUser();
        $token = $this->createTokenMock($user);
        $auction = $this->createAuction(status: 'CLOSED', closed: true);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $auction, ['AUCTION_EDIT']));
    }
}
