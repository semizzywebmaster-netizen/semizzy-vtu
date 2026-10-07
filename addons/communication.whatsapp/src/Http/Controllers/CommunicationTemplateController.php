<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\CommunicationTemplateService;
use App\Models\Communication\Template;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommunicationTemplateController
{
 public function index(): JsonResponse { return response()->json(Template::orderBy('name')->get()); }
 public function render(Request $request,Template $template,CommunicationTemplateService $service): JsonResponse
 {
  $data=$request->validate(['variables'=>'nullable|array']);
  return response()->json($service->render($template,$data['variables'] ?? []));
 }
 public function store(Request $request): JsonResponse
 {
  $data=$request->validate(['name'=>'required|string|max:191','channel'=>'required|string|max:32','event'=>'nullable|string|max:191','language'=>'nullable|string|max:16','subject'=>'nullable|string','body'=>'required|string','variables'=>'nullable|array','enabled'=>'nullable|boolean']);
  return response()->json(['template'=>Template::create($data)],201);
 }
}
