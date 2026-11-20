<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Screening\Application\Command\EvaluateInvite;
use App\Screening\Application\Command\ImportInvites;
use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteStatus;
use App\Screening\Domain\Model\MeetingPurpose;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\Verdict;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Port\InviteRepository;
use App\Screening\Domain\Port\VerdictGenerator;
use App\Tests\Unit\InviteBuilder;
use App\Shared\Application\Bus\CommandBus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Full stack minus the LLM: import -> evaluate -> Doctrine round trip. */
final class EvaluateInviteTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        self::getContainer()->set(VerdictGenerator::class, new class implements VerdictGenerator {
            public function generate(Invite $invite, DoorCheck $check, Persona $persona): Verdict
            {
                return new Verdict(VerdictType::GO_ASYNC, MeetingPurpose::STATUS_UPDATE, 'Reason.', 'Joke.', null, 'Reply.', 'Template.', $persona);
            }
        });
    }

    public function testEvaluationIsPersisted(): void
    {
        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new ImportInvites());
        $bus->dispatch(new ImportInvites()); // idempotent
        $bus->dispatch(new EvaluateInvite('inv-quick-sync', Persona::WIENER_GRANT));

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $invite = self::getContainer()->get(InviteRepository::class)->find(new InviteId('inv-quick-sync'));

        self::assertNotNull($invite);
        self::assertSame(InviteStatus::EVALUATED, $invite->status());
        self::assertSame(VerdictType::GO_ASYNC, $invite->verdict()?->type);
        self::assertSame(Persona::WIENER_GRANT, $invite->verdict()->persona);
        self::assertSame(66000, $invite->doorCheck()?->cost()->cents);
        self::assertTrue($invite->doorCheck()->hasFlag('optional_in_large_meeting'));
    }

    public function testOverridesCountAsPositiveFeedback(): void
    {
        $repo = self::getContainer()->get(InviteRepository::class);
        $at = new \DateTimeImmutable(InviteBuilder::NOW);
        $verdict = new Verdict(VerdictType::BOUNCE, MeetingPurpose::ANNOUNCEMENT, 'r', 'j', null, 'reply', null, Persona::DOORMAN);
        foreach (['a' => 'override', 'b' => 'override+bad', 'c' => 'pending'] as $id => $what) {
            $invite = InviteBuilder::invite(id: 'inv-'.$id);
            if ('pending' !== $what) {
                $invite->evaluate(DoorCheck::fromArray([]), $verdict, $at);
                $invite->override($at);
            }
            if ('override+bad' === $what) {
                $invite->rate(false, $at);
            }
            $repo->save($invite);
        }

        $feedback = $repo->feedbackForTitle('Test meeting', new InviteId('inv-x'));

        self::assertEqualsCanonicalizing([true, false], $feedback, 'unrated override = true, rating wins, pending ignored');
    }

    public function testUnknownInviteIsReported(): void
    {
        $this->expectException(\DomainException::class);

        self::getContainer()->get(CommandBus::class)->dispatch(new EvaluateInvite('nope'));
    }
}
