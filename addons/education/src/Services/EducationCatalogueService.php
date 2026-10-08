<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationInstitution;
use Semizzy\Addons\Education\Models\EducationProduct;
final class EducationCatalogueService {
 public function institutions(?string $type=null, ?string $state=null) {
  return EducationInstitution::query()->where('active',true)->when($type,fn($q)=>$q->where('type',$type))->when($state,fn($q)=>$q->where('state',$state))->orderBy('name')->get();
 }
 public function products(?string $category=null, ?int $institutionId=null) {
  return EducationProduct::query()->where('active',true)->when($category,fn($q)=>$q->where('category',$category))->when($institutionId,fn($q)=>$q->where('institution_id',$institutionId))->with('institution')->orderBy('name')->get();
 }
}