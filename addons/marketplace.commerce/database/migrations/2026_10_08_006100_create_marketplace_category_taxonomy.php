<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('listing_type', 30)->default('product');
            $table->string('product_type', 30)->default('physical');
            $table->json('attribute_schema')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
            $table->index(['parent_id', 'active']);
            $table->index(['listing_type', 'active']);
        });

        Schema::table('marketplace_products', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('marketplace_categories')->nullOnDelete();
            $table->json('attributes')->nullable()->after('metadata');
            $table->string('listing_type', 30)->default('product')->after('product_type');
            $table->index(['category_id', 'status']);
            $table->index(['listing_type', 'status']);
        });

        $categories = [
            ['name'=>'Real Estate','slug'=>'real-estate','listing_type'=>'property','product_type'=>'physical','sort'=>10,'attrs'=>[
                'property_type'=>['type'=>'select','required'=>true,'options'=>['house','apartment','flat','duplex','bungalow','terrace','land','plot','shop','office','warehouse','commercial_property','event_center','hotel_guest_house','farm','estate']],
                'listing_intent'=>['type'=>'select','required'=>true,'options'=>['sale','rent','lease','shortlet']],
                'rent_frequency'=>['type'=>'select','required'=>false,'options'=>['daily','weekly','monthly','quarterly','yearly']],
                'bedrooms'=>['type'=>'integer','required'=>false,'min'=>0],
                'bathrooms'=>['type'=>'integer','required'=>false,'min'=>0],
                'toilets'=>['type'=>'integer','required'=>false,'min'=>0],
                'land_size'=>['type'=>'string','required'=>false],
                'furnishing'=>['type'=>'select','required'=>false,'options'=>['unfurnished','semi_furnished','furnished']],
                'parking_spaces'=>['type'=>'integer','required'=>false,'min'=>0],
                'serviced'=>['type'=>'boolean','required'=>false],
                'title_document'=>['type'=>'string','required'=>false],
                'location'=>['type'=>'string','required'=>true],
            ]],
            ['name'=>'Vehicles','slug'=>'vehicles','listing_type'=>'product','product_type'=>'physical','sort'=>20,'attrs'=>[
                'vehicle_type'=>['type'=>'select','required'=>true,'options'=>['car','suv','van','bus','truck','motorcycle','tricycle','boat','other']],
                'condition'=>['type'=>'select','required'=>false,'options'=>['new','used','refurbished']],
                'make'=>['type'=>'string','required'=>false],'model'=>['type'=>'string','required'=>false],
                'year'=>['type'=>'integer','required'=>false,'min'=>1900,'max'=>2100],
                'mileage'=>['type'=>'integer','required'=>false,'min'=>0],
                'transmission'=>['type'=>'select','required'=>false,'options'=>['automatic','manual','cvt']],
                'fuel_type'=>['type'=>'select','required'=>false,'options'=>['petrol','diesel','hybrid','electric','cng','other']],
                'location'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Phones & Tablets','slug'=>'phones-tablets','listing_type'=>'product','product_type'=>'physical','sort'=>30,'attrs'=>[
                'brand'=>['type'=>'string','required'=>false],'model'=>['type'=>'string','required'=>false],
                'storage'=>['type'=>'string','required'=>false],'ram'=>['type'=>'string','required'=>false],
                'network'=>['type'=>'string','required'=>false],'sim_type'=>['type'=>'string','required'=>false],
                'battery_health'=>['type'=>'integer','required'=>false,'min'=>0,'max'=>100],
            ]],
            ['name'=>'Electronics','slug'=>'electronics','listing_type'=>'product','product_type'=>'physical','sort'=>40,'attrs'=>[
                'brand'=>['type'=>'string','required'=>false],'model'=>['type'=>'string','required'=>false],
                'warranty'=>['type'=>'string','required'=>false],'condition'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Computers & Accessories','slug'=>'computers-accessories','listing_type'=>'product','product_type'=>'physical','sort'=>50,'attrs'=>[
                'brand'=>['type'=>'string','required'=>false],'model'=>['type'=>'string','required'=>false],
                'processor'=>['type'=>'string','required'=>false],'ram'=>['type'=>'string','required'=>false],
                'storage'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Home, Furniture & Appliances','slug'=>'home-furniture-appliances','listing_type'=>'product','product_type'=>'physical','sort'=>60,'attrs'=>[
                'brand'=>['type'=>'string','required'=>false],'material'=>['type'=>'string','required'=>false],
                'dimensions'=>['type'=>'string','required'=>false],'warranty'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Fashion','slug'=>'fashion','listing_type'=>'product','product_type'=>'physical','sort'=>70,'attrs'=>[
                'gender'=>['type'=>'select','required'=>false,'options'=>['men','women','unisex','boys','girls']],
                'size'=>['type'=>'string','required'=>false],'brand'=>['type'=>'string','required'=>false],
                'colour'=>['type'=>'string','required'=>false],'material'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Beauty & Personal Care','slug'=>'beauty-personal-care','listing_type'=>'product','product_type'=>'physical','sort'=>80,'attrs'=>[
                'brand'=>['type'=>'string','required'=>false],'volume_size'=>['type'=>'string','required'=>false],
                'expiry_date'=>['type'=>'date','required'=>false],
            ]],
            ['name'=>'Health & Wellness','slug'=>'health-wellness','listing_type'=>'product','product_type'=>'physical','sort'=>90,'attrs'=>[
                'brand'=>['type'=>'string','required'=>false],'form'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Baby, Kids & Toys','slug'=>'baby-kids-toys','listing_type'=>'product','product_type'=>'physical','sort'=>100,'attrs'=>[
                'age_range'=>['type'=>'string','required'=>false],'brand'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Sports & Fitness','slug'=>'sports-fitness','listing_type'=>'product','product_type'=>'physical','sort'=>110,'attrs'=>[
                'sport'=>['type'=>'string','required'=>false],'brand'=>['type'=>'string','required'=>false],
                'size'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Gaming','slug'=>'gaming','listing_type'=>'product','product_type'=>'physical','sort'=>120,'attrs'=>[
                'platform'=>['type'=>'string','required'=>false],'condition'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Books, Music & Collectibles','slug'=>'books-music-collectibles','listing_type'=>'product','product_type'=>'physical','sort'=>130,'attrs'=>[
                'author_artist'=>['type'=>'string','required'=>false],'edition'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Food, Agriculture & Farming','slug'=>'food-agriculture-farming','listing_type'=>'product','product_type'=>'physical','sort'=>140,'attrs'=>[
                'item_type'=>['type'=>'string','required'=>false],'quantity_unit'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Animals & Pets','slug'=>'animals-pets','listing_type'=>'product','product_type'=>'physical','sort'=>150,'attrs'=>[
                'animal_type'=>['type'=>'string','required'=>true],'breed'=>['type'=>'string','required'=>false],
                'age'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Commercial Equipment & Tools','slug'=>'commercial-equipment-tools','listing_type'=>'product','product_type'=>'physical','sort'=>160,'attrs'=>[
                'equipment_type'=>['type'=>'string','required'=>false],'brand'=>['type'=>'string','required'=>false],
                'capacity'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Building & Construction','slug'=>'building-construction','listing_type'=>'product','product_type'=>'physical','sort'=>170,'attrs'=>[
                'material_type'=>['type'=>'string','required'=>false],'brand'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Services','slug'=>'services','listing_type'=>'service','product_type'=>'service','sort'=>180,'attrs'=>[
                'service_area'=>['type'=>'string','required'=>false],'delivery_mode'=>['type'=>'select','required'=>false,'options'=>['onsite','remote','hybrid']],
            ]],
            ['name'=>'Digital Products','slug'=>'digital-products','listing_type'=>'digital','product_type'=>'digital','sort'=>190,'attrs'=>[
                'format'=>['type'=>'string','required'=>false],'version'=>['type'=>'string','required'=>false],
                'file_size'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Jobs & Professional Opportunities','slug'=>'jobs-professional-opportunities','listing_type'=>'service','product_type'=>'service','sort'=>200,'attrs'=>[
                'employment_type'=>['type'=>'select','required'=>false,'options'=>['full_time','part_time','contract','temporary','internship','freelance']],
                'location'=>['type'=>'string','required'=>false],
            ]],
            ['name'=>'Business & Industry','slug'=>'business-industry','listing_type'=>'product','product_type'=>'physical','sort'=>210,'attrs'=>[
                'business_type'=>['type'=>'string','required'=>false],'location'=>['type'=>'string','required'=>false],
            ]],
        ];

        foreach ($categories as $category) {
            DB::table('marketplace_categories')->insert([
                'name'=>$category['name'],'slug'=>$category['slug'],'listing_type'=>$category['listing_type'],
                'product_type'=>$category['product_type'],'attribute_schema'=>json_encode($category['attrs']),
                'active'=>true,'sort_order'=>$category['sort'],'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        $realEstate = DB::table('marketplace_categories')->where('slug','real-estate')->value('id');
        $realEstateChildren = [
            ['Houses','houses'],['Apartments & Flats','apartments-flats'],['Land & Plots','land-plots'],
            ['Shops','shops'],['Offices','offices'],['Warehouses','warehouses'],
            ['Commercial Property','commercial-property'],['Shortlet','shortlet'],
            ['Event Centres','event-centres'],['Hotels & Guest Houses','hotels-guest-houses'],
            ['Farms','farms'],['Estates','estates'],
        ];
        foreach ($realEstateChildren as $i=>$child) {
            DB::table('marketplace_categories')->insert([
                'parent_id'=>$realEstate,'name'=>$child[0],'slug'=>'real-estate-'.$child[1],
                'listing_type'=>'property','product_type'=>'physical','attribute_schema'=>json_encode([]),
                'active'=>true,'sort_order'=>10+$i,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id','status']);
            $table->dropIndex(['listing_type','status']);
            $table->dropColumn(['category_id','attributes','listing_type']);
        });
        Schema::dropIfExists('marketplace_categories');
    }
};
