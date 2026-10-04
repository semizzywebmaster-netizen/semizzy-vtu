<?php
namespace App\Services\Vtu;
use App\Models\Service;
use Illuminate\Validation\ValidationException;
class VtuPayloadValidator{public function validate(Service $service,array $payload):array{$rules=[];foreach((array)($service->metadata['required_fields']??[]) as $field)$rules[$field]=['required'];$v=validator($payload,$rules);if($v->fails())throw new ValidationException($v);return $v->validated();}}
