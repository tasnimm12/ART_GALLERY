<?php

namespace App\Tests\Order;

use App\Entity\Artwork;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\ArtworkRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CartAndOrderTest extends WebTestCase
{
    public function testAddToCartAndDisplayCart(): void
    {
        $client = static::createClient();
        $artworkRepo = static::getContainer()->get(ArtworkRepository::class);
        $artwork = $artworkRepo->findOneBy(['status' => 'AVAILABLE']);
        $this->assertNotNull($artwork);

        // Add to cart
        $client->request('GET', '/panier/ajouter/' . $artwork->getId());
        $this->assertResponseRedirects('/panier');
        $crawler = $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cart-item-title', $artwork->getTitle());
    }

    public function testRemoveFromCart(): void
    {
        $client = static::createClient();
        $artworkRepo = static::getContainer()->get(ArtworkRepository::class);
        $artwork = $artworkRepo->findOneBy(['status' => 'AVAILABLE']);
        $this->assertNotNull($artwork);

        // Add then remove
        $client->request('GET', '/panier/ajouter/' . $artwork->getId());
        $client->followRedirect();

        $client->request('GET', '/panier/supprimer/' . $artwork->getId());
        $this->assertResponseRedirects('/panier');
        $crawler = $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.empty-cart-box h2', 'Votre panier est vide');
    }

    public function testAnonymousCheckoutRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/commande/recapitulatif');

        $this->assertResponseRedirects('/login');
    }

    public function testFullCheckoutAndPaymentWorkflow(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $userRepo = $container->get(UserRepository::class);
        $artworkRepo = $container->get(ArtworkRepository::class);

        $clientUser = $userRepo->findOneBy(['email' => 'client@aag.com']);
        $this->assertNotNull($clientUser);

        $artwork = $artworkRepo->findOneBy(['status' => 'AVAILABLE']);
        $this->assertNotNull($artwork);
        $artworkId = $artwork->getId();

        // 1. Login user
        $client->loginUser($clientUser);

        // 2. Add artwork to cart
        $client->request('GET', '/panier/ajouter/' . $artworkId);
        $client->followRedirect();

        // 3. Go to checkout
        $crawler = $client->request('GET', '/commande/recapitulatif');
        $this->assertResponseIsSuccessful();

        // 4. Submit checkout and payment form
        $form = $crawler->selectButton('🔒 Payer')->form([
            'checkout_form[address]' => '42 Avenue Montaigne',
            'checkout_form[postalCode]' => '75008',
            'checkout_form[city]' => 'Paris',
            'checkout_form[country]' => 'France',
            'checkout_form[phone]' => '+33 6 11 22 33 44',
            'checkout_form[paymentMethod]' => 'CARD',
            'checkout_form[notes]' => 'Livraison en main propre',
        ]);

        $client->submit($form);

        // 5. Check redirection to confirmation
        $this->assertResponseRedirects();
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.confirmation-title', 'Merci pour votre acquisition !');

        // 6. Verify Database states
        $em->clear();
        $updatedArtwork = $em->getRepository(Artwork::class)->find($artworkId);
        $this->assertSame('SOLD', $updatedArtwork->getStatus());

        $orders = $em->getRepository(Order::class)->findBy(['user' => $clientUser]);
        $this->assertNotEmpty($orders);
        $latestOrder = $orders[count($orders) - 1];
        $this->assertSame('PAID', $latestOrder->getStatus());

        $payment = $em->getRepository(Payment::class)->findOneBy(['order' => $latestOrder]);
        $this->assertNotNull($payment);
        $this->assertSame('PAID', $payment->getStatus());

        $notification = $em->getRepository(Notification::class)->findOneBy(['user' => $clientUser], ['createdAt' => 'DESC']);
        $this->assertNotNull($notification);
        $this->assertSame('ORDER_CONFIRMED', $notification->getType());

        // 7. Verify order history page
        $client->request('GET', '/mes-commandes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.orders-card', $latestOrder->getOrderNumber());
    }
}
