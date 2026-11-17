<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prefixed, unlike Inlay's other tables: hosts commonly own "site_settings".
        Schema::create('inlay_site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->json('values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inlay_site_settings');
    }
};
