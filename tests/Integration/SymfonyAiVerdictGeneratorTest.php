<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Screening\Application\Import\InviteFactory;
use App\Screening\Application\Import\RawAttendee;
use App\Screening\Application\Import\RawInvite;
use App\Screening\Domain\DoorCheck\AgendaDetector;
use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\DoorCheck\DoorChecker;
use App\Screening\Domain\DoorCheck\Rule\AgendaRule;
use App\Screening\Domain\DoorCheck\Rule\CostRule;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\Language;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Privacy\Pseudonymizer;
use App\Screening\Domain\Service\CostCalculator;
use App\Screening\Infrastructure\Ai\PromptBuilder;
use App\Screening\Infrastructure\Ai\SymfonyAiVerdictGenerator;
use App\Tests\Unit\InviteBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Agent;
use App\Screening\Infrastructure\Ai\VerdictPayload;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;

final class SymfonyAiVerdictGeneratorTest extends TestCase
{
    private static function payload(string $type = 'GO_ASYNC'): ObjectResult
    {
        return new ObjectResult(new VerdictPayload($type, 'status_update', 'No agenda and 11 people.', 'Not tonight.', 30, 'Thanks, could we handle this async?', "- Status:\n- Blockers:"));
    }

    private function generator(ObjectResult|string|\Closure $response): SymfonyAiVerdictGenerator
    {
        return new SymfonyAiVerdictGenerator(new Agent(MockPlatformFactory::createPlatform($response instanceof ObjectResult ? static fn () => $response : $response), 'claude-sonnet-5-5'), new PromptBuilder(), new NullLogger());
    }

    private function check(Invite $invite): DoorCheck
    {
        return (new DoorChecker([new AgendaRule(new AgendaDetector()), new CostRule(new CostCalculator())]))->check(InviteBuilder::context($invite));
    }

    public function testMapsStructuredResponseToDomainVerdict(): void
    {
        $invite = InviteBuilder::invite('sync', 11, 60, true);
        $verdict = $this->generator(self::payload())->generate($invite, $this->check($invite), Persona::BERLIN);

        self::assertSame(VerdictType::GO_ASYNC, $verdict->type);
        self::assertNull($verdict->suggestedDurationMin, 'only SHORTEN carries a duration');
        self::assertStringContainsString('Blockers', (string) $verdict->asyncTemplate);
        self::assertSame(Persona::BERLIN, $verdict->persona);
    }

    public function testPayloadIsExtractedFromThinkingPlusObjectMultiPart(): void
    {
        $invite = InviteBuilder::invite();
        $multi = new MultiPartResult([new ThinkingResult('hmm'), self::payload()]);
        $verdict = $this->generator(static fn () => $multi)->generate($invite, $this->check($invite), Persona::DOORMAN);

        self::assertSame(VerdictType::GO_ASYNC, $verdict->type);
    }

    public function testGarbageResponseFailsLoudly(): void
    {
        $invite = InviteBuilder::invite();
        $this->expectException(\UnexpectedValueException::class);

        $this->generator('I am sorry, I cannot do that.')->generate($invite, $this->check($invite), Persona::DOORMAN);
    }

    public function testUnknownVerdictTypeFailsLoudly(): void
    {
        $invite = InviteBuilder::invite();
        $this->expectException(\UnexpectedValueException::class);

        $this->generator(self::payload('MAYBE'))->generate($invite, $this->check($invite), Persona::DOORMAN);
    }

    /** The most important test: nothing personal reaches the LLM payload. */
    public function testNoPersonalDataReachesThePlatform(): void
    {
        $raw = new RawInvite(
            'inv-privacy',
            'Sync with Erika Mustermann',
            'Please mail erika.mustermann@example.com or Max Mustermann, call +43 660 1234567. Join https://meet.example.com/j/1?pwd=secret',
            new \DateTimeImmutable('2026-11-04 10:00:00'),
            60,
            [
                new RawAttendee('Demo User', 'demo.user@example.com', true, true),
                new RawAttendee('Erika Mustermann', 'erika.mustermann@example.com'),
                new RawAttendee('Max Mustermann', 'max.mustermann@example.com'),
            ],
            null,
            Language::EN,
        );
        $invite = (new InviteFactory(new Pseudonymizer()))->create($raw);

        $payloads = [];
        $generator = $this->generator(function ($model, $payload, $options) use (&$payloads): ObjectResult {
            $payloads[] = json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

            return self::payload();
        });
        $generator->generate($invite, $this->check($invite), Persona::WIENER_GRANT);

        self::assertCount(1, $payloads);
        foreach (['@', 'Mustermann', 'Erika', 'Max M', 'erika', 'demo.user', 'Demo User', '660 1234567', '+43', 'example.com', 'pwd=', 'meet.example', 'j/1'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, (string) $payloads[0], sprintf('LLM payload leaks "%s"', $forbidden));
        }
        self::assertStringContainsString('Person A', (string) $payloads[0]);
    }
}
