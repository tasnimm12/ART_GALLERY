<?php

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrderVoter extends Voter
{
    public const VIEW = 'ORDER_VIEW';
    public const PAY = 'ORDER_PAY';
    public const CANCEL = 'ORDER_CANCEL';
    public const EDIT = 'ORDER_EDIT';

    public function __construct(private Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [
            self::VIEW, 'VIEW',
            self::PAY, 'PAY',
            self::CANCEL, 'CANCEL',
            self::EDIT, 'EDIT'
        ], true)) {
            return false;
        }

        return $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Order $order */
        $order = $subject;

        $attribute = match ($attribute) {
            'VIEW' => self::VIEW,
            'PAY' => self::PAY,
            'CANCEL' => self::CANCEL,
            'EDIT' => self::EDIT,
            default => $attribute,
        };

        return match ($attribute) {
            self::VIEW => $this->canView($order, $user),
            self::PAY => $this->canPay($order, $user),
            self::CANCEL => $this->canCancel($order, $user),
            self::EDIT => $this->canEdit($order, $user),
            default => false,
        };
    }

    private function canView(Order $order, User $user): bool
    {
        // Admin can view any order
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Client can only view their own order
        return $order->getUser() === $user;
    }

    private function canPay(Order $order, User $user): bool
    {
        // Only the owner of the order can pay
        if ($order->getUser() !== $user) {
            return false;
        }

        // Can only pay for pending orders
        return $order->getStatus() === 'PENDING';
    }

    private function canCancel(Order $order, User $user): bool
    {
        // Order must be in PENDING status to be cancelled
        if ($order->getStatus() !== 'PENDING') {
            return false;
        }

        // Either the owner or an admin can cancel
        return $order->getUser() === $user || $this->security->isGranted('ROLE_ADMIN');
    }

    private function canEdit(Order $order, User $user): bool
    {
        // Only admin can edit orders
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
