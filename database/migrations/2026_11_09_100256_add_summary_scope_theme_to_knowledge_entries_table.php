<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card as a theme, not an atom:
 *
 *  - summary   — 1–2 sentences (200–300 chars) saying what the card states.
 *                Read first, everywhere: it is the table of contents the model
 *                uses to decide whether the full text is worth reading.
 *  - theme      — the single main label that places the card inside a project.
 *  - scope      — whose knowledge it is: the user (profile), a project, or the
 *                machine setup (environment). Cross-project knowledge must not
 *                be buried under one project.
 *  - superseded_by — newer version of the same card. Superseded cards stay in
 *                the table as history but are never handed out by search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->text('summary')->nullable()->after('content');
            $table->string('theme', 64)->nullable()->after('summary');
            $table->string('scope', 16)->nullable()->after('theme');
            $table->foreignId('superseded_by')->nullable()->after('scope')
                ->constrained('knowledge_entries')->nullOnDelete();

            $table->index(['scope', 'project']);
            $table->index('theme');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropIndex(['scope', 'project']);
            $table->dropIndex(['theme']);
            $table->dropColumn(['summary', 'theme', 'scope']);
        });
    }
};
