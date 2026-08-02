<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentSequences;

final class StoreDocumentSequenceRequest extends DocumentSequenceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'document_numbering.create',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->sequenceRules();
    }
}