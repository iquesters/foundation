<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\Integration\Models\Integration;
use Iquesters\SmartMessenger\Jobs\MessageJobs\ProcessChatbotResponseJob;
use Iquesters\SmartMessenger\Models\Message;

class ChatbotDispatchController extends Controller
{
    use Loggable;

    public function dispatchProcessResponse(Request $request): JsonResponse
    {
        $this->logMethodStart();

        $validated = $request->validate([
            'message_id' => ['required', 'string'],
            'chatbot_response' => ['required', 'array'],
            'integration_id' => ['nullable', 'string'],
        ]);

        try {
            $message = Message::query()
                ->where('message_id', $validated['message_id'])
                ->first();

            if (!$message) {
                $this->logWarning('Dispatch aborted: message not found' . $this->formatContext([
                    'message_id' => $validated['message_id'],
                ]));

                return response()->json([
                    'success' => false,
                    'message' => 'Message not found',
                ], 404);
            }

            $freshMessage = $message->fresh();

            if (!$freshMessage) {
                $this->logError('Dispatch aborted: failed to refresh message model' . $this->formatContext([
                    'message_id' => $message->id,
                ]));

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to prepare message for dispatch',
                ], 500);
            }

            $resolvedIntegrationId = $this->resolveIntegrationId($validated['integration_id'] ?? null);

            ProcessChatbotResponseJob::dispatch(
                $freshMessage,
                $validated['chatbot_response'],
                $resolvedIntegrationId
            );

            $this->logInfo('ProcessChatbotResponseJob dispatched via foundation API' . $this->formatContext([
                'message_db_id' => $freshMessage->id,
                'message_id' => $freshMessage->message_id,
                'queue' => 'ProcessChatbotResponseJob',
                'integration_input' => $validated['integration_id'] ?? null,
                'resolved_integration_id' => $resolvedIntegrationId,
                'chatbot_messages_count' => count($validated['chatbot_response']['messages'] ?? []),
                'chatbot_actions_count' => count($validated['chatbot_response']['actions'] ?? []),
            ]));

            return response()->json([
                'success' => true,
                'message' => 'ProcessChatbotResponseJob dispatched successfully',
                'data' => [
                    'message_id' => $freshMessage->message_id,
                    'message_db_id' => $freshMessage->id,
                    'job_class' => ProcessChatbotResponseJob::class,
                    'queue' => 'ProcessChatbotResponseJob',
                    'integration_input' => $validated['integration_id'] ?? null,
                    'resolved_integration_id' => $resolvedIntegrationId,
                ],
            ], 200);
        } catch (\Throwable $e) {
            $this->logError('Dispatch failed' . $this->formatContext([
                'message_id' => $validated['message_id'] ?? null,
                'integration_input' => $validated['integration_id'] ?? null,
                'error' => $e->getMessage(),
            ]));

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispatch ProcessChatbotResponseJob',
            ], 500);
        } finally {
            $this->logMethodEnd();
        }
    }

    private function formatContext(array $context): string
    {
        return ' | context=' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function resolveIntegrationId(?string $integrationInput): ?int
    {
        if ($integrationInput === null || trim($integrationInput) === '') {
            return null;
        }

        $integrationInput = trim($integrationInput);

        if (ctype_digit($integrationInput)) {
            $integration = Integration::query()->find((int) $integrationInput);
        } else {
            $integration = Integration::query()
                ->where('uid', $integrationInput)
                ->first();
        }

        if (!$integration) {
            $this->logWarning('Integration input could not be resolved' . $this->formatContext([
                'integration_input' => $integrationInput,
            ]));

            return null;
        }

        return (int) $integration->id;
    }
}
