<?php

namespace App\Security\Voter;

use App\Entity\Auction;
use App\Enum\AuctionStatus;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AuctionVoter extends Voter
{
    public const VIEW = 'AUCTION_VIEW';
    public const BID = 'AUCTION_BID';
    public const EDIT = 'AUCTION_EDIT';
    public const CANCEL = 'AUCTION_CANCEL';

    public function __construct(private Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [
            self::VIEW, 'VIEW',
            self::BID, 'BID',
            self::EDIT, 'EDIT',
            self::CANCEL, 'CANCEL'
        ], true)) {
            return false;
        }

        return $subject instanceof Auction;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Auction $auction */
        $auction = $subject;

        // Normalise attribute
        $attribute = match ($attribute) {
            'VIEW' => self::VIEW,
            'BID' => self::BID,
            'EDIT' => self::EDIT,
            'CANCEL' => self::CANCEL,
            default => $attribute,
        };

        // Anyone can view auctions
        if ($attribute === self::VIEW) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::BID => $this->canBid($auction, $user),
            self::EDIT => $this->canEdit($auction, $user),
            self::CANCEL => $this->canCancel($auction, $user),
            default => false,
        };
    }

    private function canBid(Auction $auction, User $user): bool
    {
        // Must have client role
        if (!$this->security->isGranted('ROLE_CLIENT')) {
            return false;
        }

        // Account must be active and verified (KYC / Email verification)
        if ($user->getStatus() !== 'ACTIVE' || $user->getEmailVerifiedAt() === null) {
            return false;
        }

        // Auction must not be closed
        if ($auction->getStatus() === AuctionStatus::ENDED->value || $auction->getStatus() === AuctionStatus::CANCELLED->value || $auction->getClosedAt() !== null) {
            return false;
        }

        // Auction must be currently open based on dates
        $now = new \DateTimeImmutable();
        if ($now < $auction->getStartDate() || $now > $auction->getEndDate()) {
            return false;
        }

        return true;
    }

    private function canEdit(Auction $auction, User $user): bool
    {
        // Only admin can edit
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return false;
        }

        // An admin cannot modify an auction that is already closed
        if ($auction->getStatus() === AuctionStatus::ENDED->value || $auction->getClosedAt() !== null) {
            return false;
        }

        return true;
    }

    private function canCancel(Auction $auction, User $user): bool
    {
        // Only admin can cancel
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return false;
        }

        // Cannot cancel if already closed
        if ($auction->getStatus() === AuctionStatus::ENDED->value || $auction->getClosedAt() !== null) {
            return false;
        }

        return true;
    }
}
