<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function encryptArray(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        return Crypt::encrypt(serialize($value));
    }

    private function decryptArray(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;
        try {
            return json_encode(unserialize(Crypt::decrypt($value)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function up(): void
    {
        Schema::table('provider_connections', function (Blueprint $table) {
            $table->text('auth_options')->nullable()->change();
            $table->text('headers')->nullable()->change();
            $table->text('query_params')->nullable()->change();
            $table->text('proxy')->nullable()->change();
        });
        Schema::table('provider_endpoints', function (Blueprint $table) {
            $table->text('headers')->nullable()->change();
            $table->text('query_params')->nullable()->change();
            $table->text('webhook_config')->nullable()->change();
        });

        foreach (DB::table('provider_connections')->select('id','auth_options','headers','query_params','proxy')->orderBy('id')->cursor() as $row) {
            DB::table('provider_connections')->where('id',$row->id)->update([
                'auth_options'=>$this->encryptArray($row->auth_options),
                'headers'=>$this->encryptArray($row->headers),
                'query_params'=>$this->encryptArray($row->query_params),
                'proxy'=>$this->encryptArray($row->proxy),
            ]);
        }
        foreach (DB::table('provider_endpoints')->select('id','headers','query_params','webhook_config')->orderBy('id')->cursor() as $row) {
            DB::table('provider_endpoints')->where('id',$row->id)->update([
                'headers'=>$this->encryptArray($row->headers),
                'query_params'=>$this->encryptArray($row->query_params),
                'webhook_config'=>$this->encryptArray($row->webhook_config),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('provider_connections')->select('id','auth_options','headers','query_params','proxy')->orderBy('id')->cursor() as $row) {
            DB::table('provider_connections')->where('id',$row->id)->update([
                'auth_options'=>$this->decryptArray($row->auth_options),
                'headers'=>$this->decryptArray($row->headers),
                'query_params'=>$this->decryptArray($row->query_params),
                'proxy'=>$this->decryptArray($row->proxy),
            ]);
        }
        foreach (DB::table('provider_endpoints')->select('id','headers','query_params','webhook_config')->orderBy('id')->cursor() as $row) {
            DB::table('provider_endpoints')->where('id',$row->id)->update([
                'headers'=>$this->decryptArray($row->headers),
                'query_params'=>$this->decryptArray($row->query_params),
                'webhook_config'=>$this->decryptArray($row->webhook_config),
            ]);
        }
        Schema::table('provider_connections', function (Blueprint $table) {
            $table->json('auth_options')->nullable()->change();
            $table->json('headers')->nullable()->change();
            $table->json('query_params')->nullable()->change();
            $table->json('proxy')->nullable()->change();
        });
        Schema::table('provider_endpoints', function (Blueprint $table) {
            $table->json('headers')->nullable()->change();
            $table->json('query_params')->nullable()->change();
            $table->json('webhook_config')->nullable()->change();
        });
    }
};
