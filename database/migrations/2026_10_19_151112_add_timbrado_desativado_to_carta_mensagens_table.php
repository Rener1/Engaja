<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca as cartas em que o gestor/admin desligou o papel timbrado na
     * edição, para que o reprocessamento automático não volte a timbrá-las.
     */
    public function up(): void
    {
        Schema::table('carta_mensagens', function (Blueprint $table) {
            $table->boolean('timbrado_desativado')->default(false)->after('timbrado_aplicado_em');
        });
    }

    public function down(): void
    {
        Schema::table('carta_mensagens', function (Blueprint $table) {
            $table->dropColumn('timbrado_desativado');
        });
    }
};
