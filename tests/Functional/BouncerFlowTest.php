<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Screening\Application\Command\EvaluateInvite;
use App\Screening\Application\Command\ImportInvites;
use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\MeetingPurpose;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\Verdict;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Port\VerdictGenerator;
use App\Shared\Application\Bus\CommandBus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Dashboard -> verdict card -> confirm -> savings banner, with a stubbed LLM. */
final class BouncerFlowTest extends WebTestCase
{
    public function testPrecheckFormIsAPlainPostSoTurboCannotDropTheResult(): void
    {
        $client = $this->stubbedClientWithFixtures();
        $client->request('GET', '/precheck');
        self::assertSelectorExists('form[data-turbo="false"]');
        $client->submitForm('Check draft', ['title' => 'Sync', 'description' => 'sync', 'attendeeCount' => 12, 'durationMin' => 60]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#result');
        self::assertSelectorTextContains('#result', '720,00 €');
    }

    public function testPrecheckWithBlankNumbersFallsBackInsteadOfFailing(): void
    {
        $client = $this->stubbedClientWithFixtures();
        $crawler = $client->request('GET', '/precheck');
        $form = $crawler->selectButton('Check draft')->form(['title' => 'Sync', 'attendeeCount' => '', 'durationMin' => '']);
        $client->submit($form);
        self::assertResponseIsSuccessful();
    }

    public function testUnknownInviteShowsFriendlyNotFound(): void
    {
        $client = $this->stubbedClientWithFixtures();
        $client->request('GET', '/invites/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEvaluateConfirmAndSeeSavings(): void
    {
        $client = $this->stubbedClientWithFixtures();

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('660,00 €', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Person-hours 0 h Money 0,00 €', preg_replace('/\s+/', ' ', strip_tags((string) $client->getResponse()->getContent())) ?? '');

        $client->click($crawler->selectLink('Quick sync')->link());
        self::assertSelectorTextContains('article', 'Not checked yet');
        $client->submitForm('Ask the bouncer');
        $client->followRedirect();
        self::assertSelectorTextContains('.joke', 'Not tonight.');
        self::assertSelectorExists('textarea');

        self::assertSelectorTextContains('.rec .on', 'Go async');

        $client->submitForm('Approve');
        $client->followRedirect();
        self::assertSelectorTextContains('.decision-actions', 'Approved.');

        $client->request('GET', '/');
        $text = preg_replace('/\s+/', ' ', strip_tags((string) $client->getResponse()->getContent())) ?? '';
        self::assertStringContainsString('Person-hours 11 h Money 660,00 €', $text);
        self::assertSelectorTextContains('.filed', 'Quick sync');
        self::assertSelectorTextContains('.filed', 'Approved');
        self::assertSelectorTextNotContains('.tray:not(.filed)', 'Quick sync');

        // Reset wipes decisions and savings, then reseeds.
        $client->submitForm('Reset demo week');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'demo week loaded');
        self::assertSelectorNotExists('.filed');
        $text = preg_replace('/\s+/', ' ', strip_tags((string) $client->getResponse()->getContent())) ?? '';
        self::assertStringContainsString('Person-hours 0 h Money 0,00 €', $text);
    }

    public function testDemoImportFillsAnEmptyInTray(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $client->request('GET', '/');
        self::assertSelectorTextContains('.tray-empty', 'Nothing to review yet');
        self::assertSelectorTextContains('.howto', 'How it works');

        $client->submitForm('Load demo week');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'demo week loaded');
        self::assertSelectorTextContains('.tray', 'Quick sync');
        self::assertSelectorTextContains('.tray-head button', 'Review');
    }

    public function testSwitchingReviewerOffersAnOptInReReview(): void
    {
        $client = $this->stubbedClientWithFixtures();
        static::getContainer()->get(CommandBus::class)->dispatch(new EvaluateInvite('inv-quick-sync', Persona::DOORMAN));

        $client->request('GET', '/');
        self::assertSelectorExists('.tray .strip-rec .tick');
        self::assertSelectorNotExists('.prompt');

        $client->request('POST', '/persona', ['persona' => 'NEW_YORKER'], [], ['HTTP_REFERER' => '/']);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.prompt', 'New Yorker is on the desk now');
        self::assertSelectorTextContains('.prompt', '1 open request was reviewed by someone else');

        // The normal button only reviews pending invites: Quick sync keeps its Doorman review.
        $client->submit($crawler->filter('.tray-head form')->form());
        $client->request('GET', '/invites/inv-quick-sync');
        self::assertSelectorTextContains('figcaption', 'Doorman');

        // The prompt was one-shot; the quiet hint stays until the user opts in.
        $crawler = $client->request('GET', '/');
        self::assertSelectorNotExists('.prompt');
        $client->submit($crawler->filter('.stale form')->form());
        $client->followRedirect();
        self::assertSelectorNotExists('.stale');
        $client->request('GET', '/invites/inv-quick-sync');
        self::assertSelectorTextContains('figcaption', 'New Yorker');
    }

    public function testDemoImportNeedsCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/demo/import', ['_token' => 'bad']);

        self::assertResponseStatusCodeSame(403);
    }

    private function stubbedClientWithFixtures(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot(); // keep the stubbed VerdictGenerator across requests
        $c = static::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $c->set(VerdictGenerator::class, new class implements VerdictGenerator {
            public function generate(Invite $invite, DoorCheck $check, Persona $persona): Verdict
            {
                return new Verdict(VerdictType::GO_ASYNC, MeetingPurpose::STATUS_UPDATE, 'No agenda.', 'Not tonight.', null, 'Polite reply.', '- Status:', $persona);
            }
        });
        $c->get(CommandBus::class)->dispatch(new ImportInvites());

        return $client;
    }

    public function testBadCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/invites/inv-quick-sync/confirm', ['_token' => 'bad']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPrecheckSuggestsFixesForTheOffenderDraft(): void
    {
        $client = $this->stubbedClientWithFixtures();
        $client->request('GET', '/precheck');
        $client->submitForm('Check draft', ['title' => 'Quick sync', 'description' => 'sync', 'attendeeCount' => 11, 'durationMin' => 60]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Needs work before sending');
        self::assertSelectorTextContains('article', '660,00 €');
        self::assertSelectorTextContains('article', 'Add an agenda');
        self::assertSelectorTextContains('article', 'No agenda.');
    }

    public function testGoogleCallbackRejectsForeignState(): void
    {
        $client = static::createClient();
        $client->request('GET', '/calendar/google/callback', ['state' => 'forged', 'code' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testGoogleConnectExplainsMissingConfiguration(): void
    {
        $client = static::createClient();
        $client->request('GET', '/calendar/google/connect');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash', 'GOOGLE_CLIENT_ID');
    }

    public function testGoogleImportNeedsCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/calendar/google/import', ['_token' => 'bad']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testLanguageSwitchTranslatesUiAndOffersOnlyThatLanguagesPersonas(): void
    {
        $client = $this->stubbedClientWithFixtures();

        $client->request('GET', '/');
        self::assertSelectorCount(5, '.nameplate');
        self::assertSelectorTextContains('.nameplates', 'Doorman');
        self::assertSelectorTextNotContains('.nameplates', 'Berlin');

        $client->request('POST', '/locale', ['locale' => 'de'], [], ['HTTP_REFERER' => '/']);
        $client->followRedirect();
        self::assertSelectorExists('html[lang="de"]');
        self::assertSelectorTextContains('.tray-head h2', 'Eingangskorb');
        self::assertSelectorCount(5, '.nameplate');
        self::assertSelectorTextContains('.nameplates', 'Berliner Türsteher');
        self::assertSelectorTextNotContains('.nameplates', 'Portier');
        self::assertSelectorTextContains('.chips', 'Agenda');

        $client->request('POST', '/locale', ['locale' => 'en'], [], ['HTTP_REFERER' => '/']);
        $client->followRedirect();
        self::assertSelectorTextContains('.tray-head h2', 'In-tray');
    }

    public function testPersonaOfAnotherLanguageIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/persona', ['persona' => 'BERLIN']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnknownLanguageIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/locale', ['locale' => 'fr']);

        self::assertResponseStatusCodeSame(400);
    }
}
