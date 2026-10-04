<?php
namespace App\Services\Vtu;
use Illuminate\Support\Facades\Artisan;
class VtuAddonInstaller{
 public function __construct(private VtuServiceRegistry $registry){}
 public function install():void{
  $exit=Artisan::call('migrate',['--path'=>'database/migrations','--force'=>true]);
  if($exit!==0)throw new \RuntimeException('VTU addon migration failed. Check the application logs.');
  $this->registry->bootstrapCatalogue();
 }
}