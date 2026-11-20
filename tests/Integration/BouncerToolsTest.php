<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Mcp\Exception\ToolCallException;
use App\Screening\Application\Command\ImportInvites;
use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\MeetingPurpose;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\Verdict;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Port\VerdictGenerator;
use App\Screening\UI\Mcp\BouncerTools;
use App\Shared\Application\Bus\CommandBus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BouncerToolsTest extends KernelTestCase
{
    private const RATE_FILE = __DIR__.'/../../var/hourly_rate_test';

    private BouncerTools $tools;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $c->set(VerdictGenerator::class, new class implements VerdictGenerator {
            public function generate(Invite $invite, DoorCheck $check, Persona $persona): Verdict
            {
                $long = $invite->duration()->minutes >= 60 && !$check->signals()['hasAgenda'];

                return new Verdict($long ? VerdictType::GO_ASYNC : VerdictType::LET_IN, MeetingPurpose::UNCLEAR, 'Reason.', 'Joke.', null, 'Reply.', $long ? 'Template.' : null, $persona);
            }
        });
        $c->get(CommandBus::class)->dispatch(new ImportInvites());
        $this->tools = $c->get(BouncerTools::class);
        @unlink(self::RATE_FILE);
    }

    protected function tearDown(): void
    {
        @unlink(self::RATE_FILE);
        parent::tearDown();
    }

    public function testSetHourlyRateChangesCostsLive(): void
    {
        $this->tools->setHourlyRate(72.5);

        self::assertEquals(['attendees' => 11, 'hours' => 1.0, 'hourlyRateEur' => 72.5, 'totalEur' => 797.5], $this->tools->meetingCost('inv-quick-sync'));
        self::assertEquals(797.5, collect($this->tools->listUpcomingInvites(), 'inv-quick-sync')['costEur']);

        $this->expectException(ToolCallException::class);
        $this->tools->setHourlyRate(0);
    }

    public function testCheckWeekReturnsVerdictsAndTotals(): void
    {
        $result = $this->tools->checkWeek('WIENER_GRANT');

        self::assertSame(8, $result['totals']['invites']);
        self::assertEquals(660, collect($result['verdicts'], 'inv-quick-sync')['costEur']);
        self::assertSame('GO_ASYNC', collect($result['verdicts'], 'inv-quick-sync')['verdict']);
        self::assertSame('Go async', collect($result['verdicts'], 'inv-quick-sync')['verdictLabel']);
        self::assertGreaterThan(0, $result['totals']['potentialEurSaved']);
    }

    /** MCP output is pseudonymized only, exactly like the LLM payload. */
    public function testNothingPersonalLeavesThroughMcp(): void
    {
        $out = json_encode([
            $this->tools->listUpcomingInvites(7),
            $this->tools->checkWeek(),
            $this->tools->meetingCost('inv-roadmap'),
            $this->tools->suggestSlot('inv-roadmap', 45),
            $this->tools->weeklyReport(),
        ], \JSON_UNESCAPED_UNICODE);

        foreach (['@', 'Mustermann', 'Erika', 'Tobias', 'example.com', '+43', '1234567'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, (string) $out, sprintf('MCP output leaks "%s"', $forbidden));
        }
    }

    public function testMeetingCostBreakdown(): void
    {
        self::assertEquals(['attendees' => 7, 'hours' => 1.5, 'hourlyRateEur' => 60.0, 'totalEur' => 630.0], $this->tools->meetingCost('inv-roadmap'));
    }

    public function testSuggestSlotAvoidsFocusBlockAndReturnsThree(): void
    {
        $slots = $this->tools->suggestSlot('inv-roadmap', 45);

        self::assertCount(3, $slots);
    }

    public function testPrecheckDraftFixesTheOffender(): void
    {
        $r = $this->tools->precheckDraft('Quick sync', 'sync', 11, 60);

        self::assertFalse($r['ready']);
        self::assertEquals(660, $r['costEur']);
        self::assertNotEmpty($r['suggestions']);
        self::assertStringContainsString('costs 240,00 € instead of 660,00 €', implode(' ', $r['suggestions']));
    }

    public function testPrecheckRedactsPersonalDataInDraft(): void
    {
        $r = $this->tools->precheckDraft('Sync', 'mail erika@example.com', 3, 30);

        self::assertStringNotContainsString('@', json_encode($r, \JSON_THROW_ON_ERROR));
    }

    public function testConfirmAndRateCloseTheLoopIntoTheReport(): void
    {
        $this->tools->checkMeeting('inv-quick-sync');
        $confirmed = $this->tools->confirmVerdict('inv-quick-sync');

        self::assertSame('GO_ASYNC', $confirmed['verdict']);
        self::assertGreaterThan(0, $this->tools->weeklyReport()['eurSaved']);

        $this->tools->checkMeeting('inv-roadmap');
        $this->tools->overrideVerdict('inv-roadmap');
        $this->tools->rateMeeting('inv-roadmap', true);
        self::assertSame(1, $this->tools->weeklyReport()['ratedWorthIt']);
        self::assertStringNotContainsString('@', $this->tools->invite('inv-quick-sync'));
    }

    public function testUnknownInviteGivesReadableToolError(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Invite "nope" not found.');
        $this->tools->meetingCost('nope');
    }

    public function testUnknownPersonaGivesReadableToolError(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Unknown persona "CLOWN"');
        $this->tools->checkMeeting('inv-quick-sync', 'CLOWN');
    }

    public function testEmptyCountsAreAnObject(): void
    {
        self::assertSame('{}', json_encode($this->tools->weeklyReport()['countsPerOutcome']));
    }
}

/**
 * @param list<array<string, mixed>> $verdicts
 *
 * @return array<string, mixed>
 */
function collect(array $verdicts, string $id): array
{
    foreach ($verdicts as $v) {
        if ($id === $v['id']) {
            return $v;
        }
    }
    throw new \OutOfBoundsException($id);
}
