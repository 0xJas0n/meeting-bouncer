<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final readonly class Verdict
{
    public function __construct(
        public VerdictType $type,
        public MeetingPurpose $purpose,
        public string $seriousReason,
        public string $jokeLine,
        public ?int $suggestedDurationMin,
        public string $replyDraft,
        public ?string $asyncTemplate,
        public Persona $persona,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'purpose' => $this->purpose->value,
            'seriousReason' => $this->seriousReason,
            'jokeLine' => $this->jokeLine,
            'suggestedDurationMin' => $this->suggestedDurationMin,
            'replyDraft' => $this->replyDraft,
            'asyncTemplate' => $this->asyncTemplate,
            'persona' => $this->persona->value,
        ];
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            VerdictType::from((string) $d['type']),
            MeetingPurpose::from((string) $d['purpose']),
            (string) $d['seriousReason'],
            (string) $d['jokeLine'],
            isset($d['suggestedDurationMin']) ? (int) $d['suggestedDurationMin'] : null,
            (string) $d['replyDraft'],
            isset($d['asyncTemplate']) ? (string) $d['asyncTemplate'] : null,
            Persona::from((string) $d['persona']),
        );
    }
}
