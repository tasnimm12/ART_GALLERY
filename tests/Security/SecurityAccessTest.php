<?php

namespace App\Tests\Security;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityAccessTest extends WebTestCase
{
    public function testPublicRoutesAreAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
    }

    public function testProtectedRoutesRedirectAnonymousUser(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/login');

        $client->request('GET', '/compte');
        $this->assertResponseRedirects('/login');
    }

    public function testClientUserCanAccessAccountButNotAdmin(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['email' => 'client@aag.com']);

        $client->loginUser($testUser);

        $client->request('GET', '/compte');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminUserCanAccessAdminAndAccount(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $adminUser = $userRepository->findOneBy(['email' => 'admin@aag.com']);

        $client->loginUser($adminUser);

        $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/compte');
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanRegisterAndIsRedirected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $uniqueEmail = 'newuser_' . uniqid() . '@aag.com';
        $form = $crawler->selectButton('S\'inscrire')->form([
            'registration_form[firstName]' => 'Alice',
            'registration_form[lastName]' => 'Martin',
            'registration_form[email]' => $uniqueEmail,
            'registration_form[phone]' => '+33612345678',
            'registration_form[plainPassword][first]' => 'Password123!',
            'registration_form[plainPassword][second]' => 'Password123!',
            'registration_form[agreeTerms]' => true,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/compte');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanLoginThroughForm(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'client@aag.com',
            'password' => 'Client123!',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/compte');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }
}
