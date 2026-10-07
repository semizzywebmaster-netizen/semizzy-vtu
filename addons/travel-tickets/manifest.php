<?php
return [
 'identifier'=>'travel.tickets','name'=>'Travel & Tickets Booking','version'=>'1.0.0',
 'description'=>'Modular travel booking for flights, bus tickets and accommodation using Core provider, payment, wallet, notification and audit infrastructure.',
 'compatibility'=>'>=2.0.0','dependencies'=>[],
 'permissions'=>['travel.view','travel.book','travel.bookings.manage','travel.services.manage','travel.providers.manage','travel.refunds.manage','travel.settings.manage'],
 'role_permissions'=>[
  'ADMIN'=>['travel.view','travel.book','travel.bookings.manage','travel.services.manage','travel.providers.manage','travel.refunds.manage','travel.settings.manage'],
  'STAFF'=>['travel.view','travel.book','travel.bookings.manage','travel.services.manage'],
  'SUPPORT'=>['travel.view','travel.bookings.manage','travel.refunds.manage'],'USER'=>['travel.view','travel.book']],
 'navigation'=>[['id'=>'travel-tickets','label'=>'Travel & Tickets','url'=>'/travel-tickets','icon'=>'plane','permission'=>'travel.view','section'=>'services','order'=>170]],
 'admin_navigation'=>[['id'=>'admin-travel-tickets','label'=>'Travel & Tickets','url'=>'/admin/travel-tickets','icon'=>'plane','permission'=>'travel.view','section'=>'addons','order'=>170]],
 'settings'=>[['key'=>'enabled','type'=>'boolean','default'=>true],['key'=>'booking_mode','type'=>'string','default'=>'provider_or_manual'],['key'=>'require_transaction_pin','type'=>'boolean','default'=>true],['key'=>'default_currency','type'=>'string','default'=>'NGN'],['key'=>'booking_hold_minutes','type'=>'integer','default'=>15]],
 'migrations'=>['2026_10_07_030000_create_travel_tickets_tables.php'],
 'web_route_files'=>['addons/travel-tickets/routes/web.php','addons/travel-tickets/routes/admin.php'],'api_route_files'=>['addons/travel-tickets/routes/api.php'],
 'routes'=>['/travel-tickets','/admin/travel-tickets'],'api_routes'=>['/api/v1/travel-tickets'],
 'provider_integrations'=>['Core ProviderManager','Core Payment/Wallet'],
 'provider_capabilities'=>['flight_search','flight_availability','flight_book','flight_cancel','bus_search','bus_availability','bus_book','bus_cancel','hotel_search','hotel_availability','hotel_book','hotel_cancel'],
 'capabilities'=>['flight_booking','bus_booking','hotel_booking','booking_history','cancellation','refunds','manual_fulfillment','provider_fulfillment','requery','receipts'],
];