<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The knowledge base is filled through MCP and HTTP only: no proxy, no
     * conversation ingestion and no search journal are left, so their storage
     * goes away with them.
     *
     * Deliberately irreversible: the dropped rows were a by-product of a
     * pipeline that no longer exists, and there is nothing to rebuild them
     * from.
     */
    public function up(): void
    {
        if (Schema::hasTable('knowledge_entries')) {
            $columns = array_values(array_filter(
                ['source_id', 'conversation_id'],
                static fn (string $column): bool => Schema::hasColumn('knowledge_entries', $column),
            ));

            if ($columns !== []) {
                Schema::table('knowledge_entries', function (Blueprint $table) use ($columns): void {
                    foreach ($columns as $column) {
                        $table->dropForeign([$column]);
                    }

                    $table->dropColumn($columns);
                });
            }
        }

        foreach ([
            'messages',
            'conversations',
            'knowledge_sources',
            'sync_runs',
            'search_logs',
            'agent_conversation_messages',
            'agent_conversations',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: the data belonged to the removed ingestion layer.
    }
};
