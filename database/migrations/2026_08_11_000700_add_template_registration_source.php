<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('registration_source')->default('manual')->after('meta_id');
        });

        $demoBusinessIds = DB::table('whatsapp_accounts')
            ->where('provider', 'fake')
            ->pluck('business_id');

        DB::table('whatsapp_templates')
            ->whereIn('business_id', $demoBusinessIds)
            ->update(['registration_source' => 'demo']);

        DB::table('whatsapp_templates')
            ->whereNotNull('meta_id')
            ->whereNotIn('business_id', $demoBusinessIds)
            ->update(['registration_source' => 'meta_sync']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropColumn('registration_source');
        });
    }
};
