<?php

namespace App\Exceptions\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-renders a domain exception as HTTP 422 using the same JSON shape Laravel
 * produces for validation failures, so API clients can handle a thrown business
 * rule exactly as they handle a failed FormRequest.
 *
 * The using exception names the offending field via validationField(). By
 * default the top-level "message" reuses the full exception message; an
 * exception can override validationMessage() to give a shorter human summary.
 */
trait RendersAsValidationError
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->validationMessage(),
            'errors' => [
                $this->validationField() => [$this->getMessage()],
            ],
        ], 422);
    }

    /**
     * The input field the failure should be reported against.
     */
    abstract protected function validationField(): string;

    /**
     * The top-level summary message. Defaults to the full exception message.
     */
    protected function validationMessage(): string
    {
        return $this->getMessage();
    }
}
