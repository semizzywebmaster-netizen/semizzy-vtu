<?php

namespace App\Services\Help;

use App\Models\HelpQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HelpAssistantService
{
    public function answer(string $question, ?string $contextKey = null, ?int $userId = null): array
    {
        $question = trim($question);
        $search = app(HelpSearchService::class)->search($question, $contextKey, 5);

        $payload = [
            'question' => $question,
            'context' => $contextKey,
            'sources' => $search->map(fn ($a) => ['id'=>$a->id,'title'=>$a->title,'content'=>Str::limit(strip_tags($a->content), 3500)])->values()->all(),
        ];

        $answer = null;
        if (config('semizzy.help.ai.enabled') && config('semizzy.help.ai.endpoint')) {
            try {
                $response = Http::timeout(12)->withToken((string) config('semizzy.help.ai.api_key'))
                    ->post((string) config('semizzy.help.ai.endpoint'), [
                        'model' => config('semizzy.help.ai.model'),
                        'messages' => [
                            ['role'=>'system','content'=>'You are the SEMIZZY ONE Help Assistant. Answer only from the supplied help-center sources. If they do not contain the answer, say you do not know and direct the user to support. Never request passwords, OTPs, PINs, API secrets or full payment credentials.'],
                            ['role'=>'user','content'=>json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)],
                        ],
                    ]);
                if ($response->successful()) {
                    $answer = data_get($response->json(), 'choices.0.message.content')
                        ?? data_get($response->json(), 'output_text');
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $questionRecord = HelpQuestion::create([
            'user_id' => $userId,
            'question' => $question,
            'normalized_hash' => hash('sha256', Str::lower(preg_replace('/\\s+/', ' ', $question))),
            'context_key' => $contextKey,
            'answered' => filled($answer) || $search->isNotEmpty(),
            'resolved_article_id' => $search->first()?->id,
        ]);

        if (! $answer) {
            $answer = $search->isNotEmpty()
                ? 'I found these Help Center resources that may answer your question. If they do not solve it, you can open a support ticket.'
                : 'I could not find a reliable answer in the Help Center yet. Please open a support ticket so the team can assist and the question can be tracked.';
        }

        return [
            'answer' => $answer,
            'sources' => $search->map(fn ($a) => ['id'=>$a->id,'title'=>$a->title,'slug'=>$a->slug])->values(),
            'questionId' => $questionRecord->id,
            'answered' => $questionRecord->answered,
        ];
    }
}