<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Persistence\Doctrine;

use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteStatus;
use App\Screening\Domain\Port\InviteRepository;
use App\Shared\Application\Bus\EventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineInviteRepository implements InviteRepository
{
    public function __construct(private EntityManagerInterface $em, private EventBus $events)
    {
    }

    public function save(Invite $invite): void
    {
        $this->em->persist($invite);
        $this->em->flush();
        foreach ($invite->pullDomainEvents() as $event) {
            $this->events->publish($event);
        }
    }

    public function find(InviteId $id): ?Invite
    {
        return $this->em->find(Invite::class, $id->value);
    }

    public function removeAll(): void
    {
        $this->em->createQuery('DELETE FROM '.Invite::class)->execute();
        $this->em->clear(); // bulk DQL bypasses the identity map: drop stale managed copies
    }

    public function feedbackForTitle(string $title, InviteId $except): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('i.worthIt')->from(Invite::class, 'i')
            ->where('i.title = :title AND i.id != :id AND (i.worthIt IS NOT NULL OR i.status = :overridden)')
            ->setParameter('title', $title)->setParameter('id', $except->value)
            ->setParameter('overridden', InviteStatus::OVERRIDDEN->value)
            ->getQuery()->getScalarResult();

        // unrated rows are overrides: the user went anyway
        return array_map(static fn (mixed $worth): bool => null === $worth || (bool) $worth, array_column($rows, 'worthIt'));
    }

    public function startingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Invite> */
        return $this->em->createQueryBuilder()
            ->select('i')->from(Invite::class, 'i')
            ->where('i.startsAt >= :from AND i.startsAt < :to')
            ->orderBy('i.startsAt')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getResult();
    }
}
