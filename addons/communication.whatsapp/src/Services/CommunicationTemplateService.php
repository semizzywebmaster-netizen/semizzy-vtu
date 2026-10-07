<?php
namespace Addons\CommunicationWhatsapp\Services;

use App\Models\Communication\Template;
use RuntimeException;

class CommunicationTemplateService
{
 public function render(Template $template,array $variables=[]): array
 {
  if(!$template->enabled) throw new RuntimeException('Communication template is disabled.');
  $allowed=array_keys($template->variables ?: []);
  foreach($variables as $key=>$value){
   if($allowed && !in_array($key,$allowed,true)) throw new RuntimeException("Unsupported template variable: {$key}");
  }
  $replace=[];
  foreach($allowed as $key) $replace['{{'.$key.'}}']=(string)($variables[$key] ?? '');
  return [
   'subject'=>$template->subject ? strtr($template->subject,$replace) : null,
   'body'=>strtr($template->body,$replace)
  ];
 }
}
