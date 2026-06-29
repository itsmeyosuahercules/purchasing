<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('key', 'urgent_contact')->value('value');

        if ($legacy && ! DB::table('settings')->where('key', 'whatsapp_contact')->exists()) {
            Setting::set('whatsapp_contact', $legacy);
        }

        DB::table('settings')->where('key', 'urgent_contact')->delete();
        \Illuminate\Support\Facades\Cache::forget('setting.urgent_contact');
    }

    public function down(): void
    {
        $whatsapp = DB::table('settings')->where('key', 'whatsapp_contact')->value('value');

        if ($whatsapp) {
            Setting::set('urgent_contact', $whatsapp);
        }

        DB::table('settings')->where('key', 'wechat_contact')->delete();
        DB::table('settings')->where('key', 'whatsapp_contact')->delete();
        \Illuminate\Support\Facades\Cache::forget('setting.wechat_contact');
        \Illuminate\Support\Facades\Cache::forget('setting.whatsapp_contact');
    }
};
