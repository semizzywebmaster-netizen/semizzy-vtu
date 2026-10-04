<?php
namespace App\Services\Vtu;
use Illuminate\Support\Facades\Artisan;
class VtuAddonInstaller{
 public function __construct(private VtuServiceRegistry $registry){}
 public function install():void{
  Artisan::call('migrate',['--path'=>'database/migrations/2026_10_05_000026_create_vtu_addon_tables.php','--force'=>true]);
  $exit=Artisan::output();
  if(str_contains(strtolower($exit),'exception')||str_contains(strtolower($exit),'error'))throw new \RuntimeException('VTU addon migration failed. Check the application logs.');
  $this->registry->bootstrapCatalogue();
 }
}