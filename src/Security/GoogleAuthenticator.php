<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);
        $email = $googleUser->getEmail();

        if (!$email) {
            throw new AuthenticationException('Aucune adresse e-mail n’a été renvoyée par Google.');
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName(trim($googleUser->getFirstName() ?: explode('@', $email)[0]));
            $user->setLastName(trim($googleUser->getLastName() ?: 'Google'));
            $user->setStatus('ACTIVE');
            $user->setEmailVerifiedAt(new \DateTimeImmutable());
            $user->setPassword($this->userPasswordHasher->hashPassword($user, bin2hex(random_bytes(32))));

            $this->entityManager->persist($user);
        } else {
            if (!$user->getFirstName() && $googleUser->getFirstName()) {
                $user->setFirstName($googleUser->getFirstName());
            }
            if (!$user->getLastName() && $googleUser->getLastName()) {
                $user->setLastName($googleUser->getLastName());
            }
            if ($user->getStatus() !== 'ACTIVE') {
                $user->setStatus('ACTIVE');
            }
            if ($user->getEmailVerifiedAt() === null) {
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
            }
        }

        $this->entityManager->flush();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn () => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->router->generate('app_account'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'La connexion Google a échoué. Veuillez réessayer.');

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
