<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $groups = [
            'vehicles' => ['Cars','SUVs','Buses','Trucks','Motorcycles & Scooters','Tricycles','Boats','Vehicle Parts & Accessories'],
            'phones-tablets' => ['Mobile Phones','Tablets','Smart Watches','Phone Accessories','Chargers & Cables','Cases & Screen Protectors'],
            'electronics' => ['Televisions','Audio & Speakers','Cameras & Photography','Security & Surveillance','Generators & Power','Solar & Inverters','Air Conditioners','Fans','Small Electronics'],
            'computers-accessories' => ['Laptops','Desktops','Monitors','Printers & Scanners','Computer Accessories','Networking','Storage Devices','Software'],
            'home-furniture-appliances' => ['Furniture','Kitchen & Dining','Large Appliances','Small Appliances','Home Decor','Bedding','Lighting','Household Supplies'],
            'fashion' => ['Men’s Fashion','Women’s Fashion','Children’s Fashion','Shoes','Bags','Watches','Jewellery & Accessories','Traditional Wear'],
            'beauty-personal-care' => ['Skincare','Hair Care','Makeup','Fragrances','Personal Care','Salon & Spa Equipment'],
            'health-wellness' => ['Health Equipment','Fitness & Wellness','Mobility & Care Equipment'],
            'baby-kids-toys' => ['Baby Products','Kids Clothing','Toys & Games','Baby Furniture','School Kids'],
            'sports-fitness' => ['Sports Equipment','Gym & Fitness','Outdoor Recreation','Cycling','Football','Other Sports'],
            'gaming' => ['Consoles','Video Games','Gaming PCs','Gaming Accessories'],
            'books-music-collectibles' => ['Books','Musical Instruments','Music & Audio','Movies','Collectibles','Art & Crafts'],
            'food-agriculture-farming' => ['Food & Groceries','Farm Produce','Livestock Feed','Farm Equipment','Seeds & Plants','Agricultural Services'],
            'animals-pets' => ['Dogs','Cats','Birds','Fish','Farm Animals','Pet Supplies'],
            'commercial-equipment-tools' => ['Industrial Machinery','Restaurant Equipment','Office Equipment','Tools','Shop Equipment','Medical Equipment'],
            'building-construction' => ['Building Materials','Plumbing','Electrical','Doors & Windows','Tiles & Flooring','Roofing','Paints & Finishes','Construction Tools'],
            'services' => ['Repair & Maintenance','Cleaning','Beauty Services','Photography & Video','Events & Catering','Logistics & Delivery','Tutoring & Training','IT & Digital Services','Professional Services','Home Services'],
            'digital-products' => ['E-books & Documents','Templates & Design Assets','Software & Licences','Audio','Video','Digital Courses','Graphics & Media'],
            'business-industry' => ['Business for Sale','Office & Commercial Supplies','Retail Equipment','Wholesale & Distribution','Franchise & Business Opportunities'],
        ];

        foreach ($groups as $parentSlug => $children) {
            $parent = DB::table('marketplace_categories')->where('slug', $parentSlug)->first();
            if (!$parent) {
                continue;
            }
            foreach ($children as $index => $name) {
                $slug = $parentSlug.'-'.\Illuminate\Support\Str::slug($name);
                DB::table('marketplace_categories')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'parent_id' => $parent->id,
                        'name' => $name,
                        'listing_type' => $parent->listing_type,
                        'product_type' => $parent->product_type,
                        'attribute_schema' => json_encode([]),
                        'active' => true,
                        'sort_order' => 100 + $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $parents = DB::table('marketplace_categories')->whereIn('slug', [
            'vehicles','phones-tablets','electronics','computers-accessories','home-furniture-appliances','fashion',
            'beauty-personal-care','health-wellness','baby-kids-toys','sports-fitness','gaming','books-music-collectibles',
            'food-agriculture-farming','animals-pets','commercial-equipment-tools','building-construction','services',
            'digital-products','business-industry',
        ])->pluck('id');

        DB::table('marketplace_categories')->whereIn('parent_id', $parents)->delete();
    }
};
