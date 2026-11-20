<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

use App\Screening\Application\DoorCheck\DoorContextFactory;
use App\Screening\Application\ReadModel\VerdictView;
use App\Screening\Domain\DoorCheck\DoorChecker;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteNotFound;
use App\Screening\Domain\Model\InviteStatus;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Port\InviteRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetInviteVerdictHandler
{
    public function __construct(private InviteRepository $invites, private DoorContextFactory $contexts, private DoorChecker $checker, private TranslatorInterface $translator)
    {
    }

    public function __invoke(GetInviteVerdict $query): VerdictView
    {
        $id = new InviteId($query->inviteId);
        $invite = $this->invites->find($id) ?? throw InviteNotFound::withId($id);
        // stored snapshot once evaluated, live rules before that
        $check = $invite->doorCheck() ?? $this->checker->check($this->contexts->forInvite($invite));
        $v = $invite->verdict();
        $status = $invite->status();

        return new VerdictView(
            $id->value,
            $invite->title(),
            $invite->slot()->start,
            $invite->duration()->minutes,
            $invite->attendeeCount()->value,
            $check->cost()->cents,
            array_map(fn (Flag $f): string => $this->translator->trans('flag.'.$f->code, $f->params), $check->flags()),
            $status->value,
            $v?->type->value,
            $v?->purpose->value,
            $v?->seriousReason,
            $v?->jokeLine,
            $v?->suggestedDurationMin,
            $v?->replyDraft,
            $v?->asyncTemplate,
            $v?->persona->value,
            InviteStatus::EVALUATED === $status,
            null === $invite->worthIt() && (InviteStatus::OVERRIDDEN === $status || (InviteStatus::CONFIRMED === $status && VerdictType::LET_IN === $v?->type)),
            $invite->worthIt(),
            $invite->organizerEmail(),
        );
    }
}
