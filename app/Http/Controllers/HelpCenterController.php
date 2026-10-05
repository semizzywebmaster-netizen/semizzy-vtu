<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpFeedback;
use App\Models\HelpQuestion;
use App\Services\Help\HelpAssistantService;
use App\Services\Help\HelpSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('q'));
        $context = $request->string('context')->toString() ?: null;
        $articles = $query
            ? app(HelpSearchService::class)->search($query, $context)
            : app(HelpSearchService::class)->related($context);

        return Inertia::render('HelpCenter', [
            'query' => $query,
            'context' => $context,
            'articles' => $articles->map(fn ($a) => $this->article($a)),
            'categories' => HelpArticle::query()->where('published', true)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->values(),
        ]);
    }

    public function show(Request $request, HelpArticle $article): Response
    {
        abort_unless($article->published, 404);
        $article->increment('views');
        return Inertia::render('HelpArticle', ['article' => $this->article($article->fresh()), 'context' => $request->string('context')->toString()]);
    }

    public function feedback(Request $request, HelpArticle $article)
    {
        $data = $request->validate(['helpful' => ['required','boolean'], 'comment' => ['nullable','string','max:1000'], 'context_key' => ['nullable','string','max:120']]);
        HelpFeedback::updateOrCreate(
            ['user_id'=>$request->user()->id,'article_id'=>$article->id],
            ['helpful'=>$data['helpful'],'comment'=>$data['comment'] ?? null,'context_key'=>$data['context_key'] ?? null]
        );
        return back()->with('success', 'Thanks for the feedback.');
    }

    public function ask(Request $request, HelpAssistantService $assistant)
    {
        $data = $request->validate(['question'=>['required','string','min:2','max:2000'],'context'=>['nullable','string','max:120']]);
        return response()->json($assistant->answer($data['question'], $data['context'] ?? null, $request->user()->id));
    }

    private function article(HelpArticle $article): array
    {
        return [
            'id'=>$article->id,'type'=>$article->type,'title'=>$article->title,'slug'=>$article->slug,
            'excerpt'=>$article->excerpt,'content'=>$article->content,'category'=>$article->category,
            'contextKey'=>$article->context_key,'tags'=>$article->tags,'views'=>$article->views,
        ];
    }
}