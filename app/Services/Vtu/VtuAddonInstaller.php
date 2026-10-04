<?php
namespace App\Services\Vtu;
class VtuAddonInstaller{public function __construct(private VtuServiceRegistry $registry){} public function install():void{$this->registry->bootstrapCatalogue();}}
