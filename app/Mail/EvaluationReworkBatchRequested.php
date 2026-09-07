<?php

namespace App\Mail;

use App\Models\ReworkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EvaluationReworkBatchRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $reworks)
    {
        $this->reworks = $reworks->values();
        if ($this->reworks->isEmpty() || $this->reworks->contains(fn ($rework) => ! $rework instanceof ReworkRequest)) {
            throw new InvalidArgumentException('A grouped rework notification needs evaluation rework records.');
        }
        if ($this->reworks->pluck('evaluator_id')->unique()->count() !== 1
            || $this->reworks->pluck('procurement_id')->unique()->count() !== 1) {
            throw new InvalidArgumentException('A grouped rework notification must belong to one evaluator and procurement.');
        }
    }

    public function build(): self
    {
        $reference = trim((string) $this->reworks->first()->procurement?->reference_no) ?: 'Evaluation';

        return $this
            ->subject('Evaluation Rework Required: '.$reference.' ('.$this->reworks->count().' '.str('evaluation')->plural($this->reworks->count()).')')
            ->view('emails.evaluations.rework-batch-requested');
    }
}
