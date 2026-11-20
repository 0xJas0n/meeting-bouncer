<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Persistence\Doctrine;

use App\Screening\Domain\Model\FocusBlock;
use App\Screening\Domain\Port\FocusBlockRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFocusBlockRepository implements FocusBlockRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(FocusBlock $block): void
    {
        $this->em->persist($block);
        $this->em->flush();
    }

    public function has(string $id): bool
    {
        return null !== $this->em->find(FocusBlock::class, $id);
    }

    public function removeAll(): void
    {
        $this->em->createQuery('DELETE FROM '.FocusBlock::class)->execute();
        $this->em->clear();
    }

    public function overlapping(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<FocusBlock> */
        return $this->em->createQueryBuilder()
            ->select('f')->from(FocusBlock::class, 'f')
            ->where('f.startsAt < :to AND f.endsAt > :from')
            ->orderBy('f.startsAt')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getResult();
    }
}
