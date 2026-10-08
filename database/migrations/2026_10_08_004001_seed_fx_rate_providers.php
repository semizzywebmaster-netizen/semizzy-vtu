<?php

use App\Models\FxRateProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $providers = [
            ['Open Exchange Rates','openexchangerates','https://openexchangerates.org/api/latest.json',['auth'=>'query','query'=>['app_id'=>'{api_key}','base'=>'{from}','symbols'=>'{to}']]],
            ['CurrencyLayer','currencylayer','https://api.currencylayer.com/live',['auth'=>'query','query'=>['access_key'=>'{api_key}','source'=>'{from}','currencies'=>'{to}']]],
            ['Fixer','fixer','https://data.fixer.io/api/latest',['auth'=>'query','query'=>['access_key'=>'{api_key}','base'=>'{from}','symbols'=>'{to}']]],
            ['XE Currency Data','xe-currency-data','https://xecdapi.xe.com/v1/convert_from.json',['auth'=>'basic','query'=>['from'=>'{from}','to'=>'{to}','amount'=>'1']]],
            ['ExchangeRate.host','exchangerate-host','https://api.exchangerate.host/live',['auth'=>'query','query'=>['access_key'=>'{api_key}','source'=>'{from}','currencies'=>'{to}']]],
        ];

        foreach ($providers as $i => [$name,$code,$base,$settings]) {
            FxRateProvider::query()->updateOrCreate(
                ['code'=>$code],
                [
                    'name'=>$name,
                    'driver'=>'generic_json',
                    'base_url'=>$base,
                    'settings'=>$settings + ['timeout'=>10],
                    'priority'=>($i + 1) * 10,
                    'weight'=>1,
                    'enabled'=>false,
                ]
            );
        }
    }

    public function down(): void
    {
        FxRateProvider::query()->whereIn('code', ['openexchangerates','currencylayer','fixer','xe-currency-data','exchangerate-host'])->delete();
    }
};