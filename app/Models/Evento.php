<?php

namespace App\Models;

use App\Models\Cartas\Carta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use HasFactory, SoftDeletes;

    public const ACOES_GERAIS = [
        '1' => 'Ação Geral 1 – EJA conectada aos direitos humanos, à cultura digital, às questões ambientais, valorizando os saberes e a participação dos educandos, contribuindo para a redução da evasão nos cursos da EJA e com a elevação da escolaridade.',
        '2' => 'Ação Geral 2 – Ampliação do conhecimento sobre metodologias ativas e inovadoras, educadores formados com potencial para serem alfabetizadores, contribuições para a atualização da Matriz de Saberes e Diretrizes da EJA.',
        '3' => 'Ação Geral 3 – Ampliação do acervo de materiais de formação em formato impresso, digital e materiais audiovisuais (vídeos e podcasts) e acesso à memória, documentação e ampliação da formação por meio do CREJA.',
    ];

    public const SUBACOES = [
        '1' => [
            '1.1 - Mapeamento inicial - Leitura do Mundo',
            '1.2 - Formação aos educandos da EJA',
            '1.3 - Escuta Territorial - Feira Pedagógica Artístico-Cultural',
        ],
        '2' => [
            '2.1 - Assessoria e formação às equipes de EJA nas redes municipais parceiras',
            '2.2 - Realização de curso EaD "Como Alfabetizar com Paulo Freire"',
            '2.3 - Realização de lives formativas educacionais ampliadas',
            '2.4 - Participação em eventos ampliados, como COP 30 e Encontro de EJA',
        ],
        '3' => [
            '3.1 - Produção de materiais de formação em vídeos, cadernos, ebooks e podcasts/videocasts',
            '3.2 - Criação e inserção de dados no Centro de Referência da EJA – CREJA',
        ],
    ];

    public const CHECKLIST_PLANEJAMENTO_ITEMS = [
        'recorri_objetivos_gerais' => 'Ao planejar cada ação, recorri aos objetivos gerais do projeto, em diálogo com os dados da Leitura do Mundo e com ações que já foram realizadas? Tenho clareza do que já foi feito e do que precisa ser feito na ação que estou planejando?',
        'conexao_outras_acoes' => 'Ao planejar, estabeleci conexão com as outras ações do projeto? Por exemplo, Oficinas de Leitura e Escrita não estão desconectadas do Cartas para Esperançar, do Semear Palavras, da Escuta Territorial e do livreto dos educandos da EJA a ser publicado em 2027. Ao planejar, você se perguntou se estabeleceu as devidas conexões com outras ações do projeto e esta que vc está realizando?',
        'listas_presenca' => 'Preparei listas de presença impressas de acordo com os dados a serem inseridos no sistema ENGAJA?',
        'formularios_avaliacao' => 'Preparei formulários de avaliação de cada ação de formação, incluindo questões que possam medir os impactos que se pretende alcançar no projeto? As avaliações preparadas oferecem informações relevantes para medir o impacto das ações? Você tem clareza do que precisa ser avaliado e está contemplando no instrumental preparado?',
        'lista_materiais' => 'Organizei a lista de materiais necessários para as ações de formação que pretendo realizar e apresentei à coordenação geral/gerência com antecedência?',
        'demanda_infraestrutura' => 'Organizei a demanda de infraestrutura local com antecedência?',
        'inscricao_publico' => 'A inscrição do público esperado na formação foi feita?',
        'informacao_dia_horario' => 'A informação sobre o dia e horário da formação com cada grupo chegou com antecedência aos públicos participantes?',
        'materiais_institucionais' => 'Os materiais institucionais do projeto para entregar para os participantes estão organizados?',
        'equipe_pedagogica' => 'Equipe Pedagógica e Educadores articuladores sociais estão com clareza de quem fará o que durante os encontros presenciais de assessoria e formação.',
        'registros_audiovisual' => 'Planejei os momentos de registros audiovisual de cada ação de formação?',
        'nomear_arquivos' => 'Sei como nomear os arquivos e o local onde compartilhar os registros processuais?',
        'contatos_estrategicos' => 'Estou de posse de todos os contatos estratégicos relacionados os envolvidos na ação que vou realizar? Em caso de necessidade, sei a quem recorrer e o contato da pessoa?',
    ];

    protected $fillable = [
        'user_id',
        'eixo_id',
        'acao_geral',
        'subacao',
        'nome',
        'tipo',
        'is_cartas',
        'data_horario',
        'duracao',
        'data_inicio',
        'data_fim',
        'modalidade',
        'link',
        'objetivos_gerais',
        'objetivos_especificos',
        'local',
        'imagem',
        'recursos_materiais_necessarios',
        'providencias_sme_parceria',
        'observacoes_complementares',
        'ods_selecionados',
        'checklist_planejamento',
    ];

    protected $casts = [
        'ods_selecionados' => 'array',
        'checklist_planejamento' => 'array',
        'is_cartas' => 'boolean',
    ];

    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function autorizacoesImagem(): HasMany
    {
        return $this->hasMany(AutorizacaoImagem::class);
    }

    public function atividades(): HasMany
    {
        return $this->hasMany(Atividade::class);
    }

    public function cartas(): HasMany
    {
        return $this->hasMany(Carta::class);
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }

    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'inscricaos')
            ->withPivot(['atividade_id'])
            ->withTimestamps();
    }

    public function situacoesDesafiadoras(): BelongsToMany
    {
        return $this->belongsToMany(
            SituacaoDesafiadora::class,
            'evento_situacao_desafiadora',
            'evento_id',
            'situacao_desafiadora_id'
        )->withTimestamps();
    }

    public function matrizes(): BelongsToMany
    {
        return $this->belongsToMany(
            MatrizAprendizagem::class,
            'evento_matriz_aprendizagem',
            'evento_id',
            'matriz_aprendizagem_id'
        )->withTimestamps();
    }

    public function sequenciasDidaticas(): HasMany
    {
        return $this->hasMany(SequenciaDidatica::class, 'evento_id');
    }

    public function presencas()
    {
        return $this->hasManyThrough(
            Presenca::class,
            Atividade::class,
            'evento_id',
            'atividade_id',
            'id',
            'id'
        );
    }
}
