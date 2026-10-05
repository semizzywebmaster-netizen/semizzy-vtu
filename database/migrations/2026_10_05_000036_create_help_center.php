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

        \DB::table('help_articles')->insert([
            ['type'=>'faq','title'=>'How do I get started?','slug'=>'getting-started','excerpt'=>'Learn the basic steps for using your SEMIZZY ONE account.','content'=>'Sign in, complete your profile and verification steps, then use the available services shown on your dashboard. If a service is unavailable, check the Help Center or contact Support.','category'=>'Getting Started','context_key'=>'dashboard','tags'=>json_encode(['start','account','dashboard']), 'published'=>true,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
            ['type'=>'faq','title'=>'How do I contact customer support?','slug'=>'contact-customer-support','excerpt'=>'Open a support ticket and keep the conversation in one secure thread.','content'=>'Open Help & Support Center, choose Open support ticket, describe the issue without sharing passwords, OTPs, transaction PINs or API secrets, and submit. Support replies remain attached to the ticket.','category'=>'Support','context_key'=>'support','tags'=>json_encode(['support','ticket','customer']), 'published'=>true,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
            ['type'=>'guide','title'=>'Account security basics','slug'=>'account-security-basics','excerpt'=>'Protect your account and never share sensitive authentication information.','content'=>'Use a strong password, keep your verified contact channels current, review recognised devices and login activity, and never share passwords, OTPs, transaction PINs or private API credentials. Report suspicious activity through Support.','category'=>'Security','context_key'=>'profile','tags'=>json_encode(['security','password','otp','pin']), 'published'=>true,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
            ['type'=>'tutorial','title'=>'Understanding your account tier','slug'=>'understanding-account-tiers','excerpt'=>'Your tier controls eligibility and account limits configured by the platform.','content'=>'SEMIZZY ONE supports Tier 1, Tier 2, Tier 3 and Tier 4 Merchant. Higher tiers may require additional verification. Tier 3 and Tier 4 are eligible for the future API User addon; API keys are not part of Core until that addon is installed.','category'=>'Account','context_key'=>'profile','tags'=>json_encode(['tier','merchant','api']), 'published'=>true,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
        ]);

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