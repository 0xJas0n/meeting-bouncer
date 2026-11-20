<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Persistence\Doctrine;

use App\Reporting\Domain\SavingsEntry;
use App\Reporting\Domain\SavingsLedger;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSavingsLedger implements SavingsLedger
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function record(SavingsEntry $entry): void
    {
        // a verdict can only be decided once per invite, but keep it idempotent for replays
        if (null === $this->find($entry->inviteId())) {
            $this->save($entry);
        }
    }

    public function find(string $inviteId): ?SavingsEntry
    {
        return $this->em->find(SavingsEntry::class, $inviteId);
    }

    public function clear(): void
    {
        $this->em->createQuery('DELETE FROM '.SavingsEntry::class)->execute();
        $this->em->clear();
    }

    public function save(SavingsEntry $entry): void
    {
        $this->em->persist($entry);
        $this->em->flush();
    }

    public function decidedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<SavingsEntry> */
        return $this->em->createQueryBuilder()
            ->select('e')->from(SavingsEntry::class, 'e')
            ->where('e.decidedAt >= :from AND e.decidedAt < :to')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getResult();
    }
}
