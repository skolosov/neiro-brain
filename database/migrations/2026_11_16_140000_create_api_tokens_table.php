<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Личные токены доступа к MCP и API.
 *
 * Sanctum здесь не нужен: у нас одна таблица, один формат и две способности.
 * В базе лежит только SHA-256 хеш — плейн-токен показывается один раз при
 * выпуске и больше нигде не хранится.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
