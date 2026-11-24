<?php

namespace App\Tests\Security;

use App\Entity\Order;
use App\Entity\User;
use App\Security\Voter\OrderVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class OrderVoterTest extends TestCase
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

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hash');

        return $user;
    }

    private function createOrder(User $owner, string $status = 'PENDING'): Order
    {
        $order = new Order();
        $order->setUser($owner);
        $order->setOrderNumber('ORD-' . uniqid());
        $order->setTotalAmount('250.00');
        $order->setSource('DIRECT');
        $order->setStatus($status);

        return $order;
    }

    public function testOwnerCanViewTheirOrder(): void
    {
        $voter = new OrderVoter($this->createSecurityMock(['ROLE_CLIENT']));
        $owner = $this->createUser('owner@test.com');
        $order = $this->createOrder($owner);
        $token = $this->createTokenMock($owner);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $order, ['ORDER_VIEW']));
    }

    public function testOtherUserCannotViewOrder(): void
    {
        $voter = new OrderVoter($this->createSecurityMock(['ROLE_CLIENT']));
        $owner = $this->createUser('owner@test.com');
        $otherUser = $this->createUser('other@test.com');
        $order = $this->createOrder($owner);
        $token = $this->createTokenMock($otherUser);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $order, ['ORDER_VIEW']));
    }

    public function testAdminCanViewAnyOrder(): void
    {
        $voter = new OrderVoter($this->createSecurityMock(['ROLE_ADMIN']));
        $owner = $this->createUser('owner@test.com');
        $admin = $this->createUser('admin@test.com');
        $order = $this->createOrder($owner);
        $token = $this->createTokenMock($admin);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $order, ['ORDER_VIEW']));
    }

    public function testOwnerCanPayPendingOrder(): void
    {
        $voter = new OrderVoter($this->createSecurityMock(['ROLE_CLIENT']));
        $owner = $this->createUser('owner@test.com');
        $order = $this->createOrder($owner, status: 'PENDING');
        $token = $this->createTokenMock($owner);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $order, ['ORDER_PAY']));
    }

    public function testOwnerCannotPayCompletedOrder(): void
    {
        $voter = new OrderVoter($this->createSecurityMock(['ROLE_CLIENT']));
        $owner = $this->createUser('owner@test.com');
        $order = $this->createOrder($owner, status: 'COMPLETED');
        $token = $this->createTokenMock($owner);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $order, ['ORDER_PAY']));
    }

    public function testAdminCanEditOrderButClientCannot(): void
    {
        $adminVoter = new OrderVoter($this->createSecurityMock(['ROLE_ADMIN']));
        $clientVoter = new OrderVoter($this->createSecurityMock(['ROLE_CLIENT']));

        $owner = $this->createUser('owner@test.com');
        $admin = $this->createUser('admin@test.com');
        $order = $this->createOrder($owner);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $adminVoter->vote($this->createTokenMock($admin), $order, ['ORDER_EDIT']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $clientVoter->vote($this->createTokenMock($owner), $order, ['ORDER_EDIT']));
    }
}
