<?php

namespace App\Tests\Catalog;

use App\Entity\Artwork;
use App\Entity\User;
use App\Repository\ArtworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FavoriteWorkflowTest extends WebTestCase
{
    public function testAnonymousVisitorIsAskedToLoginAndCannotAccessFavorites(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $artwork = static::getContainer()->get(ArtworkRepository::class)->findOneBy([]);
        $this->assertNotNull($artwork);

        $crawler = $client->request('GET', '/catalogue');
        $this->assertResponseIsSuccessful();
        $button = $crawler->filter(sprintf('[data-favorite-toggle][data-favorite-url$="%d/toggle"]', $artwork->getId()));
        $this->assertSame('false', $button->attr('data-authenticated'));
        $this->assertSame('false', $button->attr('aria-pressed'));

        $client->request('GET', '/compte/favoris');
        $this->assertResponseRedirects('/login');

        $client->request('POST', sprintf('/compte/favoris/%d/toggle', $artwork->getId()), [
            '_token' => 'invalid',
        ]);
        $this->assertResponseRedirects('/login');
    }

    public function testUsersCanToggleTheirOwnFavoritesAndKeepThemAcrossRequests(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $artworks = static::getContainer()->get(ArtworkRepository::class)->findBy([], ['id' => 'ASC'], 2);
        $this->assertCount(2, $artworks, 'The test requires two catalog artworks.');

        $firstUser = $this->createUser('first');
        $secondUser = $this->createUser('second');
        $entityManager->persist($firstUser);
        $entityManager->persist($secondUser);
        $entityManager->flush();

        try {
            $client->loginUser($firstUser);

            foreach ($artworks as $artwork) {
                $token = $this->getFavoriteToken($client, $artwork);
                $client->request('POST', sprintf('/compte/favoris/%d/toggle', $artwork->getId()), [
                    '_token' => $token,
                ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
                $this->assertResponseIsSuccessful();
                $this->assertTrue($this->getJsonResponse($client)['favorite']);
            }

            $client->request('GET', '/compte/favoris');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('h1', 'MES FAVORIS');
            $this->assertCount(2, $client->getCrawler()->filter('#favorite-artworks .art-card'));

            $client->loginUser($secondUser);
            $client->request('GET', '/compte/favoris');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('[data-favorites-empty]', 'Vous n\'avez encore aucune œuvre favorite.');

            $client->loginUser($firstUser);
            $token = $this->getFavoriteToken($client, $artworks[0]);
            $client->request('POST', sprintf('/compte/favoris/%d/toggle', $artworks[0]->getId()), [
                '_token' => $token,
            ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            $this->assertResponseIsSuccessful();
            $this->assertSame(['favorite' => false, 'count' => 1], array_intersect_key(
                $this->getJsonResponse($client),
                ['favorite' => true, 'count' => true],
            ));

            $client->request('GET', '/compte/favoris');
            $this->assertCount(1, $client->getCrawler()->filter('#favorite-artworks .art-card'));

            $client->loginUser($secondUser);
            $secondToken = $this->getFavoriteToken($client, $artworks[0]);
            $client->request('POST', sprintf('/compte/favoris/%d/toggle', $artworks[0]->getId()), [
                '_token' => $secondToken,
            ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            $this->assertResponseIsSuccessful();
            $this->assertSame(['favorite' => true, 'count' => 1], array_intersect_key(
                $this->getJsonResponse($client),
                ['favorite' => true, 'count' => true],
            ));

            $client->loginUser($firstUser);
            $client->request('GET', '/compte/favoris');
            $this->assertCount(1, $client->getCrawler()->filter('#favorite-artworks .art-card'));
            $client->loginUser($secondUser);
            $client->request('GET', '/compte/favoris');
            $this->assertCount(1, $client->getCrawler()->filter('#favorite-artworks .art-card'));
        } finally {
            $entityManager->createQueryBuilder()
                ->delete(User::class, 'user')
                ->where('user.id IN (:userIds)')
                ->setParameter('userIds', [$firstUser->getId(), $secondUser->getId()])
                ->getQuery()
                ->execute();
        }
    }

    public function testFavoriteToggleRejectsAnInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createUser('csrf');
        $entityManager->persist($user);
        $entityManager->flush();
        $artwork = static::getContainer()->get(ArtworkRepository::class)->findOneBy([]);
        $this->assertNotNull($artwork);

        try {
            $client->loginUser($user);
            $client->request('POST', sprintf('/compte/favoris/%d/toggle', $artwork->getId()), [
                '_token' => 'invalid',
            ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            $this->assertResponseStatusCodeSame(403);
        } finally {
            $entityManager->createQueryBuilder()
                ->delete(User::class, 'user')
                ->where('user.id = :userId')
                ->setParameter('userId', $user->getId())
                ->getQuery()
                ->execute();
        }
    }

    private function createUser(string $suffix): User
    {
        return (new User())
            ->setEmail(sprintf('favorite-%s-%s@example.test', $suffix, bin2hex(random_bytes(6))))
            ->setPassword('test-password')
            ->setFirstName('Favorite')
            ->setLastName('Tester')
            ->setRole('ROLE_USER');
    }

    private function getFavoriteToken($client, Artwork $artwork): string
    {
        $crawler = $client->request('GET', sprintf('/catalogue/%d', $artwork->getId()));
        $this->assertResponseIsSuccessful();
        $button = $crawler->filter(sprintf('[data-favorite-toggle][data-favorite-url$="%d/toggle"]', $artwork->getId()));
        $this->assertStringContainsString('favorite-button--detail', $button->attr('class'));
        $this->assertStringContainsString(
            $button->attr('aria-pressed') === 'true' ? 'Retirer des favoris' : 'Ajouter aux favoris',
            $button->text(),
        );

        return (string) $button->attr('data-favorite-token');
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponse($client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        return $data;
    }
}
