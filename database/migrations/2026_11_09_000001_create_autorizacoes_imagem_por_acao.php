<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autorizacoes_imagem', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participante_id')->constrained('participantes')->restrictOnDelete();
            $table->foreignId('evento_id')->constrained('eventos')->restrictOnDelete();
            $table->boolean('autorizado');
            $table->timestampTz('respondido_em');
            $table->string('origem', 32);
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('presenca_origem_id')->nullable()->constrained('presencas')->nullOnDelete();
            $table->string('acao_nome');
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
            $table->unique(['participante_id', 'evento_id'], 'autorizacoes_imagem_participante_evento_unique');
            $table->index(['evento_id', 'autorizado']);
        });

        Schema::create('historicos_autorizacao_imagem', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autorizacao_imagem_id')->constrained('autorizacoes_imagem')->restrictOnDelete();
            $table->boolean('autorizado_anterior')->nullable();
            $table->boolean('autorizado');
            $table->timestampTz('respondido_em');
            $table->string('origem', 32);
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('presenca_origem_id')->nullable()->constrained('presencas')->nullOnDelete();
            $table->string('acao_nome');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historicos_autorizacao_imagem');
        Schema::dropIfExists('autorizacoes_imagem');
    }
};
