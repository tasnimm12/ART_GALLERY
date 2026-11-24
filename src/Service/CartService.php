<?php

namespace App\Service;

use App\Entity\Artwork;
use App\Repository\ArtworkRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    private const SESSION_KEY = 'aag_cart';

    public function __construct(
        private RequestStack $requestStack,
        private ArtworkRepository $artworkRepository
    ) {
    }

    private function getSession(): SessionInterface
    {
        $session = $this->requestStack->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }

    /**
     * @return list<int>
     */
    public function getRawCart(): array
    {
        return $this->getSession()->get(self::SESSION_KEY, []);
    }

    public function add(int $artworkId): bool
    {
        $artwork = $this->artworkRepository->find($artworkId);
        if (!$artwork || $artwork->getStatus() !== 'AVAILABLE') {
            return false;
        }

        $cart = $this->getRawCart();
        if (!in_array($artworkId, $cart, true)) {
            $cart[] = $artworkId;
            $this->getSession()->set(self::SESSION_KEY, $cart);
        }

        return true;
    }

    public function remove(int $artworkId): void
    {
        $cart = $this->getRawCart();
        // normalize comparison to int to avoid strict type mismatch between stored values and parameter
        $cart = array_values(array_filter($cart, fn($id) => (int)$id !== $artworkId));
        $this->getSession()->set(self::SESSION_KEY, $cart);
    }

    public function clear(): void
    {
        $this->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * @return array<array{artwork: Artwork, price: float}>
     */
    public function getItems(): array
    {
        $cart = $this->getRawCart();
        $items = [];

        foreach ($cart as $artworkId) {
            $artwork = $this->artworkRepository->find($artworkId);
            if ($artwork && $artwork->getStatus() === 'AVAILABLE') {
                $items[] = [
                    'artwork' => $artwork,
                    'price' => (float) $artwork->getPrice(),
                ];
            }
        }

        return $items;
    }

    public function getTotal(): float
    {
        $total = 0.0;
        foreach ($this->getItems() as $item) {
            $total += $item['price'];
        }

        return $total;
    }

    public function getCount(): int
    {
        return count($this->getItems());
    }
}
