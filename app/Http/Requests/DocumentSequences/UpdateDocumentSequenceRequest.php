<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentSequences;

use App\Models\DocumentSequence;

final class UpdateDocumentSequenceRequest extends DocumentSequenceRequest
{
    public function authorize(): bool
    {
        $documentSequence = $this->route(
            'documentSequence',
        );

        return $documentSequence instanceof DocumentSequence
            && $this->user()?->can(
                'update',
                $documentSequence,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $documentSequence = $this->route(
            'documentSequence',
        );

        return $this->sequenceRules(
            $documentSequence instanceof DocumentSequence
                ? (int) $documentSequence->getKey()
                : null,
        );
    }
}