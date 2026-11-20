<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Screening\Application\Command\ImportInvites;
use App\Screening\Application\Import\CalendarSources;
use App\Screening\Application\Import\RawAttendee;
use App\Screening\Application\Import\RawInvite;
use App\Screening\Application\Port\CalendarSource;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\Language;
use App\Screening\Domain\Port\InviteRepository;
use App\Screening\Infrastructure\Calendar\JsonFixtureCalendarSource;
use App\Shared\Application\Bus\CommandBus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** The first real calendar import removes the demo week; later re-imports keep real data. */
final class ImportReplacesDemoDataTest extends KernelTestCase
{
    public function testRealImportWipesDemoWeekOnce(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $google = new class implements CalendarSource {
            public string $id = 'real-1';

            public function name(): string
            {
                return 'google';
            }

            public function invites(): iterable
            {
                yield new RawInvite($this->id, 'Real meeting', '', new \DateTimeImmutable('+1 day 10:00'), 30, [new RawAttendee('A', 'a@example.com', isSelf: true), new RawAttendee('B', 'b@example.com')], null, Language::EN);
            }

            public function focusBlocks(): iterable
            {
                return [];
            }
        };
        $c->set(CalendarSources::class, new CalendarSources([$c->get(JsonFixtureCalendarSource::class), $google]));
        $bus = $c->get(CommandBus::class);
        $invites = $c->get(InviteRepository::class);

        $bus->dispatch(new ImportInvites());
        self::assertNotNull($invites->find(new InviteId('inv-quick-sync')));

        $bus->dispatch(new ImportInvites('google'));
        self::assertNull($invites->find(new InviteId('inv-quick-sync')), 'demo week is gone');
        self::assertNotNull($invites->find(new InviteId('real-1')));

        $google->id = 'real-2';
        $bus->dispatch(new ImportInvites('google'));
        self::assertNotNull($invites->find(new InviteId('real-1')), 'no demo data left, so nothing is wiped');
        self::assertNotNull($invites->find(new InviteId('real-2')));
    }
}
