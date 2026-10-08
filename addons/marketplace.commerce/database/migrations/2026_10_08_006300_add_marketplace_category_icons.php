<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_categories', function (Blueprint $table): void {
            $table->string('icon', 120)->nullable()->after('slug');
            $table->string('icon_type', 20)->default('lucide')->after('icon');
            $table->string('icon_color', 30)->nullable()->after('icon_type');
            $table->string('description', 500)->nullable()->after('name');
        });

        $icons = [
            'real-estate'=>'building-2','vehicles'=>'car-front','phones-tablets'=>'smartphone','electronics'=>'tv',
            'computers-accessories'=>'laptop','home-furniture-appliances'=>'armchair','fashion'=>'shirt',
            'beauty-personal-care'=>'sparkles','health-wellness'=>'heart-pulse','baby-kids-toys'=>'baby',
            'sports-fitness'=>'dumbbell','gaming'=>'gamepad-2','books-music-collectibles'=>'book-open',
            'food-agriculture-farming'=>'wheat','animals-pets'=>'paw-print','commercial-equipment-tools'=>'factory',
            'building-construction'=>'construction','services'=>'briefcase-business','digital-products'=>'file-down',
            'jobs-professional-opportunities'=>'user-round-check','business-industry'=>'store',
        ];

        foreach ($icons as $slug => $icon) {
            DB::table('marketplace_categories')->where('slug',$slug)->update([
                'icon'=>$icon,'icon_type'=>'lucide','updated_at'=>now()
            ]);
        }

        $rows = DB::table('marketplace_categories')->select('id','slug','parent_id')->get();
        foreach ($rows as $row) {
            if (!$row->icon) {
                $parentIcon = $row->parent_id
                    ? DB::table('marketplace_categories')->where('id',$row->parent_id)->value('icon')
                    : null;
                DB::table('marketplace_categories')->where('id',$row->id)->update([
                    'icon'=>$parentIcon ?: 'tag',
                    'icon_type'=>'lucide',
                    'updated_at'=>now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_categories', function (Blueprint $table): void {
            $table->dropColumn(['icon','icon_type','icon_color','description']);
        });
    }
};
