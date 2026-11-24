<?php

namespace App\EventSubscriber;

use App\Entity\Artist;
use App\Entity\Artwork;
use App\Entity\Auction;
use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class AuditLogSubscriber
{
    private const AUDITED_ENTITIES = [
        User::class,
        Artwork::class,
        Artist::class,
        Auction::class,
        Order::class,
        Payment::class,
    ];

    private bool $isLogging = false;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->logAction($args, 'ENTITY_CREATED');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->logAction($args, 'ENTITY_UPDATED');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->logAction($args, 'ENTITY_DELETED');
    }

    private function logAction(PostPersistEventArgs|PostUpdateEventArgs|PostRemoveEventArgs $args, string $action): void
    {
        $entity = $args->getObject();

        // Avoid infinite recursion & ignore non-audited entities
        if ($this->isLogging || $entity instanceof AuditLog) {
            return;
        }

        $entityClass = get_class($entity);
        if (!in_array($entityClass, self::AUDITED_ENTITIES, true)) {
            return;
        }

        $this->isLogging = true;

        try {
            $em = $args->getObjectManager();
            $request = $this->requestStack->getCurrentRequest();
            $currentUser = $this->security->getUser();

            $log = new AuditLog();
            if ($currentUser instanceof User) {
                $log->setUser($currentUser);
            }

            $shortName = (new \ReflectionClass($entity))->getShortName();
            $log->setAction($action);
            $log->setEntityType($shortName);

            if (method_exists($entity, 'getId') && $entity->getId() !== null) {
                $log->setEntityId($entity->getId());
            }

            $details = [];
            if ($action === 'ENTITY_UPDATED' && $args instanceof PostUpdateEventArgs) {
                $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
                // Filter out password and large binary fields for security
                unset($changeSet['password'], $changeSet['updatedAt']);
                $details['changes'] = array_map(function ($change) {
                    return [
                        'old' => is_scalar($change[0]) ? $change[0] : (is_null($change[0]) ? null : (string) gettype($change[0])),
                        'new' => is_scalar($change[1]) ? $change[1] : (is_null($change[1]) ? null : (string) gettype($change[1])),
                    ];
                }, $changeSet);
            }

            $log->setDetails(!empty($details) ? $details : ['class' => $entityClass]);

            if ($request) {
                $log->setIpAddress($request->getClientIp());
                $log->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 500));
            }

            // Direct DB insertion via connection to prevent re-entering unit of work flush cycles
            $conn = $em->getConnection();
            $conn->insert('audit_log', [
                'user_id' => $log->getUser()?->getId(),
                'action' => $log->getAction(),
                'entity_type' => $log->getEntityType(),
                'entity_id' => $log->getEntityId(),
                'details' => json_encode($log->getDetails(), JSON_THROW_ON_ERROR),
                'ip_address' => $log->getIpAddress(),
                'user_agent' => $log->getUserAgent(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Audit logging must not break main transaction
        } finally {
            $this->isLogging = false;
        }
    }
}
