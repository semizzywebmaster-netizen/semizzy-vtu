<?php
return [
 'identifier'=>'ai.chatbot','name'=>'AI Chatbot & Platform Assistant','version'=>'1.0.0',
 'description'=>'AI customer assistant with provider management, approved knowledge and human-support escalation.',
 'category'=>'AI & Automation','compatibility'=>'>=2.0.0','dependencies'=>[],
 'autoload_namespace'=>'Semizzy\\Addons\\AIChatbot',
 'permissions'=>['ai_chatbot.use','ai_chatbot.providers.manage','ai_chatbot.settings.manage','ai_chatbot.knowledge.manage','ai_chatbot.conversations.view','ai_chatbot.support.manage','ai_chatbot.analytics.view'],
 'role_permissions'=>[
  'ADMIN'=>['ai_chatbot.use','ai_chatbot.providers.manage','ai_chatbot.settings.manage','ai_chatbot.knowledge.manage','ai_chatbot.conversations.view','ai_chatbot.support.manage','ai_chatbot.analytics.view'],
  'STAFF'=>['ai_chatbot.use','ai_chatbot.conversations.view','ai_chatbot.support.manage','ai_chatbot.analytics.view'],
  'SUPPORT'=>['ai_chatbot.use','ai_chatbot.conversations.view','ai_chatbot.support.manage'],
  'USER'=>['ai_chatbot.use']
 ],
 'admin_navigation'=>[['id'=>'admin-ai-chatbot','label'=>'AI Chatbot','url'=>'/admin/ai-chatbot','icon'=>'bot','permission'=>'ai_chatbot.settings.manage','section'=>'addons','order'=>120]],
 'settings'=>[
  ['key'=>'enabled','type'=>'boolean','default'=>false],
  ['key'=>'public_enabled','type'=>'boolean','default'=>true],
  ['key'=>'assistant_name','type'=>'string','default'=>'SEMIZZY ONE Assistant'],
  ['key'=>'welcome_message','type'=>'string','default'=>'Hello! How can I help you today?'],
  ['key'=>'allowed_paths','type'=>'array','default'=>['/','/services','/pricing','/help','/dashboard']],
  ['key'=>'excluded_paths','type'=>'array','default'=>['/login','/register','/wallet','/wallet/*','/payments','/payments/*','/admin','/admin/*']],
  ['key'=>'retention_days','type'=>'integer','default'=>90],
  ['key'=>'max_message_chars','type'=>'integer','default'=>4000],
  ['key'=>'max_context_messages','type'=>'integer','default'=>12]
 ],
 'migrations'=>['2026_10_09_100000_create_ai_chatbot_tables.php'],
 'web_route_files'=>['addons/ai.chatbot/routes/web.php'],'api_route_files'=>[],
 'routes'=>['/admin/ai-chatbot','/ai-chatbot/config','/ai-chatbot/conversations'],
 'api_routes'=>[],
 'capabilities'=>['provider_adapters','approved_knowledge_base','owned_conversation_history','visitor_sessions','support_escalation','rate_limits','page_targeting']
];
