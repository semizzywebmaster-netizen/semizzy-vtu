<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_messages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // greeting|quote|seasonal|promotional
            $table->string('title')->nullable();
            $table->text('message');
            $table->json('tiers')->nullable();
            $table->json('audiences')->nullable(); // merchant, agent, reseller, etc.
            $table->string('time_period', 20)->nullable()->index(); // morning|afternoon|evening|night
            $table->string('season_key', 80)->nullable()->index();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['type'=>'greeting','message'=>'Good morning, :name. Ready for a productive day?','time_period'=>'morning','priority'=>10],
            ['type'=>'greeting','message'=>'Good afternoon, :name. Keep things moving smoothly.','time_period'=>'afternoon','priority'=>10],
            ['type'=>'greeting','message'=>'Good evening, :name. Here is to a strong finish today.','time_period'=>'evening','priority'=>10],
            ['type'=>'greeting','message'=>'Good night, :name. Stay safe and rest well.','time_period'=>'night','priority'=>10],
            ['type'=>'greeting','message'=>'Welcome back, :name. Your account is ready when you are.','time_period'=>null,'priority'=>1],
            ['type'=>'quote','message'=>'Small, consistent actions create big results.','priority'=>1],
            ['type'=>'quote','message'=>'Build trust with every transaction.','priority'=>1],
            ['type'=>'quote','message'=>'Progress is easier when every step is intentional.','priority'=>1],
            ['type'=>'quote','message'=>'Serve customers well and let reliability speak for you.','priority'=>1],
            ['type'=>'greeting','message'=>'Good morning, :name. Merchant mode: build, serve and grow.','time_period'=>'morning','audiences'=>json_encode(['merchant']),'priority'=>30],
            ['type'=>'greeting','message'=>'Good afternoon, :name. Keep your business moving.','time_period'=>'afternoon','audiences'=>json_encode(['merchant']),'priority'=>30],
            ['type'=>'greeting','message'=>'Good evening, :name. Review today and prepare for tomorrow.','time_period'=>'evening','audiences'=>json_encode(['merchant']),'priority'=>30],
            ['type'=>'greeting','message'=>'Good night, :name. Your business can pick up where you left off tomorrow.','time_period'=>'night','audiences'=>json_encode(['merchant']),'priority'=>30],
            ['type'=>'greeting','message'=>'Good morning, :name. Agent operations are ready for the day.','time_period'=>'morning','audiences'=>json_encode(['agent']),'priority'=>25],
            ['type'=>'greeting','message'=>'Good afternoon, :name. Keep serving customers with confidence.','time_period'=>'afternoon','audiences'=>json_encode(['agent']),'priority'=>25],
            ['type'=>'greeting','message'=>'Good evening, :name. Nice work keeping things moving.','time_period'=>'evening','audiences'=>json_encode(['agent']),'priority'=>25],
            ['type'=>'greeting','message'=>'Good night, :name. Take a well-earned break.','time_period'=>'night','audiences'=>json_encode(['agent']),'priority'=>25],
        ];
        foreach ($rows as &$row) { $row['tiers'] = $row['tiers'] ?? null; $row['season_key'] = null; $row['active']=true; $row['starts_at']=null; $row['ends_at']=null; $row['created_at']=$now; $row['updated_at']=$now; }
        DB::table('dashboard_messages')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_messages');
    }
};