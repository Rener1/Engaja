<?php

namespace App\Models;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participante extends Model
{
    use HasFactory, SoftDeletes;

    public const SEXOS = ['Masculino', 'Feminino', 'Não preenchido'];

    public const TAG_REDE_ENSINO = 'Rede de Ensino';

    public const TAG_MOVIMENTO_SOCIAL = 'Movimento Social';

    public const TAGS = [
        self::TAG_REDE_ENSINO,
        self::TAG_MOVIMENTO_SOCIAL,
    ];

    protected $table = 'participantes';

    protected $fillable = [
        'user_id',
        'municipio_id',
        'cpf',
        'telefone',
        'escola_unidade',
        'tipo_organizacao',
        'tag',
        'data_entrada',
        'data_nascimento',
        'sexo',
        'autorizacao_imagem',
    ];

    protected $appends = ['cpf_valido'];

    protected $casts = ['data_nascimento' => 'date:Y-m-d'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    public function inscricoes()
    {
        return $this->hasMany(Inscricao::class, 'participante_id');
    }

    public function cartasComoEducando()
    {
        return $this->hasMany(Carta::class, 'educando_participante_id');
    }

    public function cartaMensagensComoRemetente()
    {
        return $this->hasMany(CartaMensagem::class, 'remetente_participante_id');
    }

    public function cartaMensagensComoDestinatario()
    {
        return $this->hasMany(CartaMensagem::class, 'destinatario_participante_id');
    }

    public function eventos()
    {
        return $this->belongsToMany(Evento::class, 'inscricaos')
            ->withPivot(['atividade_id'])
            ->withTimestamps();
    }

    public function getCpfValidoAttribute()
    {
        return $this->validaCpf($this->cpf);
    }

    public function getNomeComLocalidadeAttribute(): string
    {
        $nome = $this->user?->name ?? 'Participante';
        $estado = $this->municipio?->estado?->nome;
        $municipio = $this->municipio?->nome;

        return collect([$nome, $estado, $municipio])
            ->filter()
            ->implode(' - ');
    }

    public function getNomeAttribute(): string
    {
        return $this->user?->name ?? 'Participante';
    }

    public function getMunicipioEstadoAttribute(): string
    {
        if (! $this->municipio) {
            return 'Não informado';
        }

        $municipio = $this->municipio->nome;
        $estado = $this->municipio->estado?->nome ?? $this->municipio->estado?->sigla ?? '';

        return collect([$municipio, $estado])
            ->filter()
            ->implode(' - ');
    }

    private function validaCpf($cpf)
    {
        // Aqui você coloca a regra de validação de CPF
        // (ou usa um package como "laravel-legends/pt-br-validator")
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}
