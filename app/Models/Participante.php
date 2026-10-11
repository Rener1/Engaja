<?php

namespace App\Models;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

class Participante extends Model
{
    use HasFactory, SoftDeletes;

    public const SEXOS = ['Masculino', 'Feminino', 'Não preenchido'];
  
    private const NAO_INFORMADO = 'Não informado';

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

    /**
     * Idade em anos completos, a partir de data_nascimento (ainda a ser criada
     * pela equipe, em participantes ou em users). Sem a coluna, retorna null.
     */
    public function getIdadeAttribute(): ?int
    {
        return $this->calcularIdade($this->user);
    }

    /**
     * Conjunto fechado de dados do remetente exibido no modal da carta.
     * Qualquer outro atributo (CPF, telefone, e-mail, demográficos) fica de fora.
     *
     * @return array{nome: string, cidade: string, estado: string, idade: string, sexo: string}
     */
    public function dadosRemetente(?User $usuario = null): array
    {
        $usuario ??= $this->user;
        $estado = $this->municipio?->estado;

        $idade = $this->calcularIdade($usuario);
        $idadeTexto = $idade !== null
            ? $idade.' '.($idade === 1 ? 'ano' : 'anos')
            : self::NAO_INFORMADO;

        $sexo = $usuario?->identidade_genero;
        if ($sexo === 'Outro' && filled($usuario?->identidade_genero_outro)) {
            $sexo = $usuario->identidade_genero_outro;
        }

        return [
            'nome' => $usuario?->name ?: 'Participante',
            'cidade' => $this->municipio?->nome ?: self::NAO_INFORMADO,
            'estado' => $this->rotuloEstado($estado?->nome, $estado?->sigla),
            'idade' => $idadeTexto,
            'sexo' => $sexo ?: self::NAO_INFORMADO,
        ];
    }

    private function rotuloEstado(?string $nome, ?string $sigla): string
    {
        if (filled($nome) && filled($sigla)) {
            return "{$nome} ({$sigla})";
        }

        return $nome ?: $sigla ?: self::NAO_INFORMADO;
    }

    private function calcularIdade(?User $usuario): ?int
    {
        $nascimento = $this->atributoSeExistir($this, 'data_nascimento')
            ?? $this->atributoSeExistir($usuario, 'data_nascimento');

        if (blank($nascimento)) {
            return null;
        }

        try {
            $data = Carbon::parse($nascimento);
        } catch (Throwable) {
            return null;
        }

        return $data->isFuture() ? null : $data->age;
    }

    /**
     * Lê o atributo apenas se a coluna existir no registro carregado, para
     * funcionar antes da migration e mesmo com strict mode do Eloquent.
     */
    private function atributoSeExistir(?Model $modelo, string $campo): mixed
    {
        if (! $modelo || ! array_key_exists($campo, $modelo->getAttributes())) {
            return null;
        }

        return $modelo->getAttribute($campo);
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
