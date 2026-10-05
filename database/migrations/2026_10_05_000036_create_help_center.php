<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->default('article');
            $table->string('title', 180);
            $table->string('slug', 220)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category', 80)->nullable()->index();
            $table->string('context_key', 120)->nullable()->index();
            $table->json('tags')->nullable();
            $table->boolean('published')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
        });

        Schema::create('help_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->string('normalized_hash', 64)->index();
            $table->string('context_key', 120)->nullable()->index();
            $table->boolean('answered')->default(false)->index();
            $table->foreignId('resolved_article_id')->nullable()->constrained('help_articles')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('help_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->boolean('helpful');
            $table->text('comment')->nullable();
            $table->string('context_key', 120)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_feedback');
        Schema::dropIfExists('help_questions');
        Schema::dropIfExists('help_articles');
    }
};