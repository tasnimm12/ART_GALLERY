<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use App\EventSubscriber\OrderStatusSubscriber;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class OrderStatusSubscriberTest extends TestCase
{
    public function testNestedPostFlushDoesNotSendDuplicateReceipt(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $mailer = $this->createMock(MailerInterface::class);
        $twig = $this->createMock(Environment::class);
        $logger = $this->createMock(LoggerInterface::class);

        $user = (new User())->setEmail('buyer@example.test');
        $order = (new Order())
            ->setUser($user)
            ->setOrderNumber('ORD-TEST-1')
            ->setTotalAmount('100.00')
            ->setSource('test');
        (new \ReflectionProperty(Order::class, 'id'))->setValue($order, 42);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects(self::once())
            ->method('find')
            ->with(42)
            ->willReturn($order);

        $paymentRepository = $this->createMock(EntityRepository::class);
        $paymentRepository->expects(self::once())
            ->method('findBy')
            ->with(['order' => $order])
            ->willReturn([]);

        $entityManager->method('getRepository')->willReturnMap([
            [Order::class, $orderRepository],
            [Payment::class, $paymentRepository],
        ]);
        $registry->expects(self::once())
            ->method('getManager')
            ->willReturn($entityManager);
        $entityManager->expects(self::exactly(2))
            ->method('persist');
        $twig->expects(self::once())
            ->method('render')
            ->willReturn('<html>Receipt</html>');
        $mailer->expects(self::once())
            ->method('send');
        $logger->expects(self::exactly(2))
            ->method('info');

        $subscriber = new OrderStatusSubscriber($registry, $mailer, $twig, $logger);
        $changeSet = ['status' => ['PENDING', 'PAID']];
        $subscriber->preUpdate(new PreUpdateEventArgs($order, $entityManager, $changeSet));

        $postFlush = new PostFlushEventArgs($entityManager);
        $entityManager->expects(self::once())
            ->method('flush')
            ->willReturnCallback(static function () use ($subscriber, $postFlush): void {
                $subscriber->postFlush($postFlush);
            });

        $subscriber->postFlush($postFlush);
    }
}
