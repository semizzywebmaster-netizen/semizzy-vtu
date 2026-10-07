<?php
namespace Semizzy\Addons\TravelTickets\Services;
class TravelSearchService{
 public function __construct(private TravelProviderGateway $gateway){}
 public function search(string $type,array $payload):array{
  $capability=match($type){'flight'=>'flight_search','bus'=>'bus_search','hotel'=>'hotel_search',default=>throw new \InvalidArgumentException('Unsupported travel type.')};
  $operation=$type.'_search';$result=$this->gateway->request($capability,$operation,$payload);
  return ['provider'=>$result['provider']->identifier,'data'=>$result['response']];
 }
}