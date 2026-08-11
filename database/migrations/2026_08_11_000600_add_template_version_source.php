<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->foreignId('replaces_template_id')
                ->nullable()
                ->after('business_id')
                ->constrained('whatsapp_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replaces_template_id');
        });
    }
};
