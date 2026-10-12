<?php

namespace App\Http\Controllers;

use App\Imports\ParticipantesPreviewImport;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\Presenca;
use App\Models\User;
use App\Services\DemograficoNormalizerService;
use App\Services\InscricaoImportValidator;
use App\Services\ParticipanteImportIdentityResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InscricaoController extends Controller
{
    public function index()
    { /* ... */
    }

    public function import(Evento $evento)
    {
        $atividades = $evento->atividades()
            ->orderBy('dia')
            ->orderBy('hora_inicio')
            ->get();

        return view('inscricoes.import', compact('evento', 'atividades'));
    }

    public function moodleImport(Evento $evento)
    {
        return view('inscricoes.moodle_import', compact('evento'));
    }

    public function moodleMomentTemplateDownload(Evento $evento)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('momentos');

        // Cabecalho no mesmo formato visual da planilha-modelo.
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:C1');

        $sheet->setCellValue('A1', 'MOMENTOS');
        $sheet->setCellValue('B1', 'Carga Horaria');
        $sheet->setCellValue('B2', 'Horas');
        $sheet->setCellValue('C2', 'Minutos');

        $sheet->getStyle('A1:C2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:C2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:C2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('963D79');

        $sheet->getColumnDimension('A')->setWidth(95);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(13);

        // Mantem varias linhas em branco com grade para preenchimento manual.
        $lastRow = 26;
        $sheet->getStyle("A1:C{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('C7C7C7');

        for ($row = 3; $row <= $lastRow; $row++) {
            $sheet->setCellValue("A{$row}", '');
            $sheet->setCellValue("B{$row}", '');
            $sheet->setCellValue("C{$row}", '');
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        $fileName = 'modelo_momentos_moodle.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function moodleUpload(Request $request, Evento $evento)
    {
        $request->validate([
            'participants_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'workloads_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        try {
            $parsed = $this->parseMoodleFiles(
                $request->file('participants_file'),
                $request->file('workloads_file')
            );

            $sessionKey = "moodle_import_evento_{$evento->id}";
            session([$sessionKey => $parsed]);

            return redirect()->route('inscricoes.moodle.preview', [
                'evento' => $evento,
                'session_key' => $sessionKey,
            ]);
        } catch (\Throwable $e) {
            $message = 'Falha ao processar as planilhas: '.$e->getMessage();
            $errorBag = str_contains(Str::lower($e->getMessage()), 'carga')
                ? ['workloads_file' => $message]
                : ['participants_file' => $message];

            return back()->withErrors([
                ...$errorBag,
                'moodle_files' => $message,
            ])->withInput();
        }
    }

    public function moodlePreview(Request $request, Evento $evento)
    {
        $sessionKey = (string) $request->query('session_key');
        $payload = session($sessionKey, []);

        if (! is_array($payload) || empty($payload['participants'] ?? [])) {
            return redirect()->route('inscricoes.moodle.import', $evento)
                ->withErrors(['participants_file' => 'Sessão expirada. Envie as duas planilhas novamente.']);
        }

        $participants = collect($payload['participants']);

        $perPage = (int) $request->query('per_page', 30);
        if ($perPage < 1) {
            $perPage = 30;
        }

        $page = (int) max(1, $request->query('page', 1));
        $total = $participants->count();
        $slice = $participants->slice(($page - 1) * $perPage, $perPage)->values();

        $rowsPaginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => route('inscricoes.moodle.preview', $evento),
                'query' => [
                    'session_key' => $sessionKey,
                    'per_page' => $perPage,
                ],
            ]
        );

        return view('inscricoes.moodle_preview', [
            'evento' => $evento,
            'sessionKey' => $sessionKey,
            'rows' => $rowsPaginator,
            'momentos' => collect($payload['momentos'] ?? []),
            'resumo' => $payload['summary'] ?? [],
            'newUsers' => collect($payload['new_users'] ?? []),
            'errorsList' => collect($payload['errors'] ?? []),
            'conflicts' => collect($payload['conflicts'] ?? []),
        ]);
    }

    public function moodleConfirm(Request $request, Evento $evento)
    {
        $validated = $request->validate([
            'session_key' => 'required|string',
        ]);

        $sessionKey = $validated['session_key'];
        $payload = session($sessionKey, []);

        if (! is_array($payload) || empty($payload['participants'] ?? [])) {
            return back()->withErrors(['session_key' => 'Sessão expirada. Refaça a pré-visualização.']);
        }

        $errorsList = collect($payload['errors'] ?? []);
        if ($errorsList->isNotEmpty()) {
            return redirect()->route('inscricoes.moodle.preview', [
                'evento' => $evento,
                'session_key' => $sessionKey,
            ])->withErrors(['import' => 'Existem inconsistências. Corrija as planilhas antes de confirmar.']);
        }

        $momentos = collect($payload['momentos'] ?? []);
        $participants = collect($payload['participants'] ?? []);

        $momentosSemCarga = $momentos
            ->filter(fn ($momento) => ! is_int($momento['carga_horaria'] ?? null))
            ->pluck('nome')
            ->map(fn ($nome) => trim((string) $nome))
            ->filter()
            ->unique()
            ->values();

        if ($momentosSemCarga->isNotEmpty()) {
            return redirect()->route('inscricoes.moodle.preview', [
                'evento' => $evento,
                'session_key' => $sessionKey,
            ])->withErrors([
                'import' => 'Não é permitido criar momento sem carga horária. Corrija a planilha de cargas para: '.$momentosSemCarga->implode(', '),
            ]);
        }

        $stats = DB::transaction(function () use ($evento, $momentos, $participants) {
            $atividadesByName = $evento->atividades()->get()->keyBy(
                fn ($a) => $this->normalizeMoodleLabel((string) $a->descricao)
            );

            $momentoMap = [];
            $momentoCriado = 0;
            $momentoAtualizado = 0;

            foreach ($momentos as $momento) {
                $nome = trim((string) ($momento['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }

                $carga = $momento['carga_horaria'];
                if (! is_int($carga)) {
                    throw new \RuntimeException("Momento '{$nome}' sem carga horária válida.");
                }

                $key = $this->normalizeMoodleLabel($nome);
                $atividade = $atividadesByName->get($key);

                if (! $atividade) {
                    $diaBase = $evento->data_inicio ?: now()->toDateString();
                    $horaInicio = '08:00';
                    $horaFim = '09:00';

                    if (is_int($carga) && $carga > 0 && $carga <= 720) {
                        $horaFim = Carbon::createFromFormat('H:i', $horaInicio)
                            ->addMinutes($carga)
                            ->format('H:i');
                    }

                    $cargaMinutos = is_int($carga) ? $carga : null;

                    $atividade = $evento->atividades()->create([
                        'descricao' => $nome,
                        'dia' => $diaBase,
                        'hora_inicio' => $horaInicio,
                        'hora_fim' => $horaFim,
                        'carga_horaria' => $cargaMinutos,
                        'presenca_ativa' => true,
                    ]);
                    $momentoCriado++;
                    $atividadesByName->put($key, $atividade);
                } else {
                    $cargaMinutosEsperada = is_int($carga) ? $carga : null;
                    if (is_int($carga) && $cargaMinutosEsperada !== null && $atividade->carga_horaria !== $cargaMinutosEsperada) {
                        $atividade->update(['carga_horaria' => $cargaMinutosEsperada]);
                        $momentoAtualizado++;
                    }
                }

                $momentoMap[$nome] = $atividade;
            }

            $emails = $participants
                ->pluck('email')
                ->map(fn ($email) => strtolower(trim((string) $email)))
                ->filter()
                ->unique()
                ->values();

            $nomes = $participants
                ->pluck('nome')
                ->map(fn ($nome) => $this->normalizePersonName((string) $nome))
                ->filter()
                ->unique()
                ->values();

            $cpfs = $participants
                ->pluck('cpf')
                ->map(fn ($cpf) => $this->normalizeCpf((string) $cpf))
                ->filter()
                ->unique()
                ->values();

            $usersByEmail = $this->fetchUsersByEmailInsensitive($emails);
            $usersByName = $this->fetchUsersByNameInsensitive($nomes);
            $participantesByCpf = $this->fetchParticipantesByCpfInsensitive($cpfs);

            $candidateUserIds = $usersByEmail->pluck('id')
                ->merge($usersByName->pluck('id'))
                ->merge($participantesByCpf->pluck('user_id'))
                ->filter()
                ->unique()
                ->values();

            // Coletar usuários não encontrados no Engaja (não cria mais)
            $usuariosNaoEncontrados = [];
            foreach ($participants as $row) {
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                $nome = trim((string) ($row['nome'] ?? ''));
                $nomeNormalizado = $this->normalizePersonName($nome);
                $cpf = $this->normalizeCpf((string) ($row['cpf'] ?? ''));
                $linha = $row['line'] ?? null;

                $userEncontrado = null;
                if ($email !== '' && $usersByEmail->has($email)) {
                    $userEncontrado = $usersByEmail->get($email);
                }

                if (! $userEncontrado && $cpf !== '' && $participantesByCpf->has($cpf)) {
                    $participantePorCpf = $participantesByCpf->get($cpf);
                    if ($participantePorCpf && $participantePorCpf->user) {
                        $userEncontrado = $participantePorCpf->user;
                    }
                }

                if (! $userEncontrado && $nomeNormalizado !== '' && $usersByName->has($nomeNormalizado)) {
                    $userEncontrado = $usersByName->get($nomeNormalizado);
                }

                if (! $userEncontrado) {
                    $usuariosNaoEncontrados[] = [
                        'linha' => $linha,
                        'nome' => $nome !== '' ? $nome : '(sem nome)',
                        'email' => $email !== '' ? $email : '(sem email)',
                    ];
                }
            }

            $participantesByUser = Participante::whereIn('user_id', $candidateUserIds)
                ->get()
                ->keyBy('user_id');

            $inscricoesCriadas = 0;
            $presencasAtualizadas = 0;

            foreach ($participants as $row) {
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                $nome = trim((string) ($row['nome'] ?? ''));
                $nomeNormalizado = $this->normalizePersonName($nome);
                $cpf = $this->normalizeCpf((string) ($row['cpf'] ?? ''));

                $user = null;
                if ($email !== '' && $usersByEmail->has($email)) {
                    $user = $usersByEmail->get($email);
                }

                if (! $user && $cpf !== '' && $participantesByCpf->has($cpf)) {
                    $participantePorCpf = $participantesByCpf->get($cpf);
                    if ($participantePorCpf && $participantePorCpf->user) {
                        $user = $participantePorCpf->user;
                    }
                }

                if (! $user && $nomeNormalizado !== '' && $usersByName->has($nomeNormalizado)) {
                    $user = $usersByName->get($nomeNormalizado);
                }

                if (! $user) {
                    continue;
                }

                $participante = $participantesByUser->get($user->id);
                if (! $participante) {
                    $participante = Participante::create(['user_id' => $user->id]);
                    $participantesByUser->put($user->id, $participante);
                }

                foreach (($row['status_por_momento'] ?? []) as $nomeMomento => $statusConclusao) {
                    if (! isset($momentoMap[$nomeMomento])) {
                        continue;
                    }

                    $atividade = $momentoMap[$nomeMomento];

                    $inscricao = Inscricao::withTrashed()
                        ->where('evento_id', $evento->id)
                        ->where('atividade_id', $atividade->id)
                        ->where('participante_id', $participante->id)
                        ->first();

                    if (! $inscricao) {
                        $inscricao = Inscricao::create([
                            'evento_id' => $evento->id,
                            'atividade_id' => $atividade->id,
                            'participante_id' => $participante->id,
                            'ouvinte' => false,
                        ]);
                        $inscricoesCriadas++;
                    } else {
                        $inscricao->fill([
                            'evento_id' => $evento->id,
                            'atividade_id' => $atividade->id,
                            'participante_id' => $participante->id,
                            'ouvinte' => false,
                        ]);
                        $inscricao->deleted_at = null;
                        $inscricao->save();
                    }

                    Presenca::updateOrCreate(
                        [
                            'inscricao_id' => $inscricao->id,
                            'atividade_id' => $atividade->id,
                        ],
                        [
                            'status' => $statusConclusao ? 'presente' : 'ausente',
                            'justificativa' => null,
                        ]
                    );
                    $presencasAtualizadas++;
                }
            }

            return [
                'momentos_criados' => $momentoCriado,
                'momentos_atualizados' => $momentoAtualizado,
                'inscricoes_criadas' => $inscricoesCriadas,
                'presencas_atualizadas' => $presencasAtualizadas,
                'usuarios_nao_encontrados' => $usuariosNaoEncontrados,
            ];
        });

        session()->forget($sessionKey);

        $usuariosNaoEncontrados = $stats['usuarios_nao_encontrados'] ?? [];

        $successMessage = sprintf(
            'Importação Moodle concluída. Momentos criados: %d. Cargas atualizadas: %d. Inscrições criadas: %d. Status atualizados: %d.',
            $stats['momentos_criados'],
            $stats['momentos_atualizados'],
            $stats['inscricoes_criadas'],
            $stats['presencas_atualizadas']
        );

        if (count($usuariosNaoEncontrados) > 0) {
            $successMessage .= ' Porém '.count($usuariosNaoEncontrados).' pessoa(s) não foram inseridas pois não possuem cadastro no Engaja.';
        }

        return redirect()->route('eventos.show', $evento)
            ->with('success', $successMessage)
            ->with('usuarios_nao_encontrados', $usuariosNaoEncontrados);
    }

    private function parseMoodleFiles(UploadedFile $participantsFile, UploadedFile $workloadsFile): array
    {
        $participantsSheet = Excel::toArray([], $participantsFile)[0] ?? [];
        $workloadsSheet = Excel::toArray([], $workloadsFile)[0] ?? [];

        if (empty($participantsSheet)) {
            throw new \RuntimeException('A planilha de participantes está vazia.');
        }

        if (empty($workloadsSheet)) {
            throw new \RuntimeException('A planilha de carga horária está vazia.');
        }

        [$participants, $momentosParticipantes, $errorsParticipants, $conflicts] = $this->parseMoodleParticipantsSheet($participantsSheet);
        [$workloadsMap, $momentosCargas, $errorsWorkloads] = $this->parseMoodleWorkloadSheet($workloadsSheet);

        $errors = array_merge($errorsParticipants, $errorsWorkloads);

        $participantsByEmail = collect($participants)
            ->filter(fn ($row) => filter_var($row['email'] ?? null, FILTER_VALIDATE_EMAIL))
            ->groupBy(fn ($row) => strtolower(trim((string) ($row['email'] ?? ''))))
            ->map(fn ($group) => $group->first())
            ->values();

        $participantsEmails = $participantsByEmail
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $usersByEmail = $this->fetchUsersByEmailInsensitive($participantsEmails);

        $newUsers = $participantsByEmail
            ->filter(function ($row) use ($usersByEmail) {
                $email = strtolower(trim((string) ($row['email'] ?? '')));

                return $email !== '' && ! $usersByEmail->has($email);
            })
            ->map(fn ($row) => [
                'nome' => trim((string) ($row['nome'] ?? '')),
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
            ])
            ->values()
            ->all();

        $missingInWorkloads = array_values(array_diff($momentosParticipantes, $momentosCargas));
        foreach ($missingInWorkloads as $momento) {
            $errors[] = "Momento '{$momento}' está na planilha de participantes, mas não está na planilha de cargas.";
        }

        $missingInParticipants = array_values(array_diff($momentosCargas, $momentosParticipantes));
        foreach ($missingInParticipants as $momento) {
            $errors[] = "Momento '{$momento}' está na planilha de cargas, mas não aparece na planilha de participantes.";
        }

        $momentosUnicos = collect(array_merge($momentosParticipantes, $momentosCargas))
            ->filter(fn ($nome) => trim((string) $nome) !== '')
            ->unique()
            ->values();

        $momentos = $momentosUnicos->map(function ($nome) use ($workloadsMap, $momentosParticipantes, $momentosCargas) {
            return [
                'nome' => $nome,
                'carga_horaria' => $workloadsMap[$nome] ?? null,
                'em_participantes' => in_array($nome, $momentosParticipantes, true),
                'em_cargas' => in_array($nome, $momentosCargas, true),
            ];
        })->values()->all();

        return [
            'participants' => $participants,
            'momentos' => $momentos,
            'new_users' => $newUsers,
            'errors' => array_values(array_unique($errors)),
            'conflicts' => $conflicts,
            'summary' => [
                'participants_rows' => count($participants),
                'participants_unique_email' => collect($participants)->pluck('email')->filter()->unique()->count(),
                'new_users_count' => count($newUsers),
                'momentos_total' => count($momentos),
                'momentos_com_carga' => count(array_filter($momentos, fn ($m) => is_int($m['carga_horaria']))),
                'errors_total' => count(array_unique($errors)),
            ],
        ];
    }

    private function parseMoodleParticipantsSheet(array $sheet): array
    {
        $headersRaw = array_map(fn ($v) => trim((string) $v), $sheet[0] ?? []);
        $headersNorm = array_map(fn ($v) => $this->normalizeMoodleHeader($v), $headersRaw);

        $acceptedNome = ['nome', 'name', 'nome_completo', 'primeiro_nome', 'first_name'];
        $acceptedEmail = [
            'email',
            'mail',
            'e_mail',
            'endereco_de_e_mail',
            'endereco_de_email',
        ];
        $acceptedCpf = ['cpf', 'documento', 'cpf_do_participante'];

        $idxNome = $this->findMoodleHeaderIndex($headersNorm, $acceptedNome);
        $idxEmail = $this->findMoodleHeaderIndex($headersNorm, [
            ...$acceptedEmail,
        ]);
        $idxCpf = $this->findMoodleHeaderIndex($headersNorm, $acceptedCpf);

        if ($idxNome === null && $idxEmail !== null) {
            if ($idxEmail > 0) {
                $idxNome = 0;
            } elseif (count($headersNorm) > 1) {
                $idxNome = 1;
            }
        }

        if ($idxEmail === null) {
            throw new \RuntimeException(
                'A planilha de participantes precisa conter ao menos a coluna de email. '
                .'Aceitos para email: ['.implode(', ', $acceptedEmail).']. '
                .($idxNome === null ? 'Coluna de nome não encontrada (aceitos: '.implode(', ', $acceptedNome).'). ' : '')
                .'Colunas encontradas: '.$this->formatMoodleHeadersForError($headersRaw)
            );
        }

        $naoMomentos = [
            'nome', 'name', 'email', 'mail', 'e_mail', 'cpf', 'telefone', 'municipio', 'tag',
            'tipo_organizacao', 'tipo_de_organizacao', 'organizacao', 'escola_unidade',
            'nome_completo', 'primeiro_nome', 'first_name', 'endereco_de_e_mail', 'endereco_de_email',
            'concluido', 'concluida', 'nao_concluido', 'nao_concluida', 'status',
        ];

        $momentColumns = [];
        foreach ($headersNorm as $i => $hNorm) {
            if ($i === $idxNome || $i === $idxEmail) {
                continue;
            }
            $raw = trim((string) ($headersRaw[$i] ?? ''));
            if ($raw === '' || in_array($hNorm, $naoMomentos, true)) {
                continue;
            }
            $momentColumns[$i] = $raw;
        }

        $errors = [];
        $conflicts = [];
        $participants = [];
        $emailsSeen = [];

        for ($i = 1; $i < count($sheet); $i++) {
            $line = $i + 1;
            $row = $sheet[$i] ?? [];

            $nome = $idxNome !== null ? trim((string) ($row[$idxNome] ?? '')) : '';
            $email = strtolower(trim((string) ($row[$idxEmail] ?? '')));
            $cpf = $idxCpf !== null ? $this->normalizeCpf((string) ($row[$idxCpf] ?? '')) : '';

            // Se não tem nome e não tem email, pular linha vazia
            if ($nome === '' && $email === '') {
                continue;
            }

            // Se coluna de nome existe mas está vazia, ou se email está vazio, reportar erro
            if ($email === '') {
                $errors[] = "Linha {$line}: email é obrigatório.";
            }

            // Se não tem coluna de nome, derivar do email
            if ($nome === '' && $email !== '') {
                $nome = Str::before($email, '@');
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Linha {$line}: email inválido ({$email}).";
            }

            if ($email !== '') {
                $nameNorm = $this->normalizeMoodleLabel($nome);
                if (isset($emailsSeen[$email]) && $emailsSeen[$email] !== $nameNorm) {
                    $errors[] = "Linha {$line}: email {$email} aparece com nomes diferentes na mesma planilha.";
                }
                $emailsSeen[$email] = $nameNorm;
            }

            $statusByMoment = [];
            foreach ($momentColumns as $idx => $momentName) {
                $status = $this->parseMoodleStatus($row[$idx] ?? null);
                if ($status === null) {
                    continue;
                }
                $statusByMoment[$momentName] = $status;
            }

            $participants[] = [
                'line' => $line,
                'nome' => $nome,
                'email' => $email,
                'cpf' => $cpf,
                'status_por_momento' => $statusByMoment,
            ];
        }

        $usersByEmail = $this->fetchUsersByEmailInsensitive(
            collect($participants)->pluck('email')->filter()->unique()->values()
        );

        foreach ($participants as $row) {
            $email = $row['email'];
            if ($email === '' || ! $usersByEmail->has($email)) {
                continue;
            }

            $user = $usersByEmail->get($email);
            if ($this->normalizeMoodleLabel($row['nome']) !== $this->normalizeMoodleLabel((string) $user->name)) {
                $errors[] = "Linha {$row['line']}: nome/email não bate com cadastro existente. Planilha '{$row['nome']}' x Sistema '{$user->name}' ({$email}).";
                $conflicts[] = [
                    'line' => $row['line'],
                    'email' => $email,
                    'sheet_name' => $row['nome'],
                    'system_name' => $user->name,
                ];
            }
        }

        return [
            $participants,
            array_values($momentColumns),
            $errors,
            $conflicts,
        ];
    }

    private function parseMoodleWorkloadSheet(array $sheet): array
    {
        $headersRaw = array_map(fn ($v) => trim((string) $v), $sheet[0] ?? []);
        $headersNorm = array_map(fn ($v) => $this->normalizeMoodleHeader($v), $headersRaw);
        $secondaryHeadersRaw = array_map(fn ($v) => trim((string) $v), $sheet[1] ?? []);
        $secondaryHeadersNorm = array_map(fn ($v) => $this->normalizeMoodleHeader($v), $secondaryHeadersRaw);

        $acceptedMomento = ['momento', 'momentos', 'atividade', 'nome_momento', 'nome_do_momento', 'nome', 'descricao'];
        $acceptedCarga = ['carga_horaria', 'carga_horaria_do_momento', 'carga', 'duracao', 'duracao_horas'];
        $acceptedHoras = ['horas', 'hora'];
        $acceptedMinutos = ['minutos', 'minuto', 'mins', 'min'];

        $idxMomento = $this->findMoodleHeaderIndex($headersNorm, $acceptedMomento);
        $idxCarga = $this->findMoodleHeaderIndex($headersNorm, $acceptedCarga);
        $idxHoras = $this->findMoodleHeaderIndex($headersNorm, $acceptedHoras);
        $idxMinutos = $this->findMoodleHeaderIndex($headersNorm, $acceptedMinutos);
        $startRow = 1;

        $hasSubHeaderHorasMinutos =
            $this->findMoodleHeaderIndex($secondaryHeadersNorm, $acceptedHoras) !== null
            || $this->findMoodleHeaderIndex($secondaryHeadersNorm, $acceptedMinutos) !== null;

        if (($idxHoras === null && $idxMinutos === null) && $hasSubHeaderHorasMinutos) {
            $idxHoras = $this->findMoodleHeaderIndex($secondaryHeadersNorm, $acceptedHoras);
            $idxMinutos = $this->findMoodleHeaderIndex($secondaryHeadersNorm, $acceptedMinutos);
            $startRow = 2;

            if ($idxMomento === null) {
                $idxMomento = $this->findMoodleHeaderIndex($secondaryHeadersNorm, $acceptedMomento);
            }
        }

        if ($idxMomento === null || ($idxCarga === null && $idxHoras === null && $idxMinutos === null)) {
            throw new \RuntimeException(
                'A planilha de cargas precisa conter colunas de momento e carga horária (carga_horaria) ou horas/minutos. '
                .'Aceitos para momento: ['.implode(', ', $acceptedMomento).']. '
                .'Aceitos para carga: ['.implode(', ', array_merge($acceptedCarga, $acceptedHoras, $acceptedMinutos)).']. '
                .'Colunas encontradas: '.$this->formatMoodleHeadersForError($headersRaw)
            );
        }

        $workloadsMap = [];
        $errors = [];
        $moments = [];

        for ($i = $startRow; $i < count($sheet); $i++) {
            $line = $i + 1;
            $row = $sheet[$i] ?? [];

            $momento = trim((string) ($row[$idxMomento] ?? ''));
            $cargaRaw = $idxCarga !== null ? trim((string) ($row[$idxCarga] ?? '')) : '';
            $horasRaw = $idxHoras !== null ? trim((string) ($row[$idxHoras] ?? '')) : '';
            $minutosRaw = $idxMinutos !== null ? trim((string) ($row[$idxMinutos] ?? '')) : '';

            if ($momento === '' && $cargaRaw === '' && $horasRaw === '' && $minutosRaw === '') {
                continue;
            }

            if ($momento === '') {
                $errors[] = "Linha {$line}: momento vazio na planilha de carga horária.";

                continue;
            }

            $temPartes = $horasRaw !== '' || $minutosRaw !== '';
            $temCargaLegada = $cargaRaw !== '';

            if ($temPartes || ($idxHoras !== null || $idxMinutos !== null)) {
                // Se a planilha tem colunas de horas/minutos e a linha veio vazia,
                // assume 0 para o campo vazio; se ambos vazios, total = 0.
                if (! $temPartes && $temCargaLegada) {
                    if (! is_numeric($cargaRaw) || (int) $cargaRaw < 0) {
                        $errors[] = "Linha {$line}: carga horária inválida para o momento '{$momento}'.";

                        continue;
                    }

                    $totalMinutos = ((int) $cargaRaw) * 60;
                    $workloadsMap[$momento] = $totalMinutos;
                    $moments[] = $momento;

                    continue;
                }

                $horas = 0;
                $minutos = 0;

                if ($horasRaw !== '') {
                    if (! is_numeric($horasRaw) || (int) $horasRaw < 0) {
                        $errors[] = "Linha {$line}: horas inválidas para o momento '{$momento}'.";

                        continue;
                    }
                    $horas = (int) $horasRaw;
                }

                if ($minutosRaw !== '') {
                    if (! is_numeric($minutosRaw) || (int) $minutosRaw < 0) {
                        $errors[] = "Linha {$line}: minutos inválidos para o momento '{$momento}'.";

                        continue;
                    }
                    $minutos = (int) $minutosRaw;
                    if ($minutos > 59) {
                        $errors[] = "Linha {$line}: minutos devem estar entre 0 e 59 para o momento '{$momento}'.";

                        continue;
                    }
                }

                $totalMinutos = ($horas * 60) + $minutos;
            } else {
                if ($cargaRaw === '' || ! is_numeric($cargaRaw)) {
                    $errors[] = "Linha {$line}: carga horária inválida para o momento '{$momento}'.";

                    continue;
                }

                $carga = (int) $cargaRaw;
                if ($carga < 0) {
                    $errors[] = "Linha {$line}: carga horária negativa para o momento '{$momento}'.";

                    continue;
                }

                $totalMinutos = $carga * 60;
            }

            $workloadsMap[$momento] = $totalMinutos;
            $moments[] = $momento;
        }

        return [$workloadsMap, $moments, $errors];
    }

    private function normalizeMoodleHeader(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function normalizeMoodleLabel(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function findMoodleHeaderIndex(array $headersNorm, array $candidates): ?int
    {
        foreach ($headersNorm as $index => $header) {
            if (in_array($header, $candidates, true)) {
                return $index;
            }
        }

        return null;
    }

    private function parseMoodleStatus(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(Str::ascii(trim((string) $value)));
        if ($normalized === '') {
            return null;
        }

        $trueValues = ['sim', 's', '1', 'true', 'ok', 'x', 'concluiu', 'concluido', 'presente'];
        $falseValues = [
            'nao', 'n', '0', 'false', 'ausente', 'pendente',
            'nao concluiu', 'nao_concluiu',
            'nao concluido', 'nao_concluido',
            'nao-concluido', 'nao concluida', 'nao_concluida',
            'nao finalizou', 'nao_finalizou',
        ];

        if (in_array($normalized, $trueValues, true)) {
            return true;
        }

        if (in_array($normalized, $falseValues, true)) {
            return false;
        }

        return null;
    }

    private function formatMoodleUserName(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name)) ?? '';
        if ($normalized === '') {
            return 'Participante';
        }

        return Str::title(Str::lower($normalized));
    }

    private function buildMoodleDefaultPassword(string $name): string
    {
        $firstName = Str::of($name)
            ->trim()
            ->explode(' ')
            ->first();

        $firstName = Str::lower(Str::ascii((string) $firstName));
        $firstName = preg_replace('/[^a-z0-9]/', '', $firstName) ?? '';

        if ($firstName === '') {
            $firstName = 'usuario';
        }

        return $firstName.'1234';
    }

    private function fetchUsersByEmailInsensitive(Collection $emails): Collection
    {
        $normalizedEmails = $emails
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedEmails->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn(DB::raw('LOWER(email)'), $normalizedEmails->all())
            ->get()
            ->keyBy(fn ($user) => strtolower(trim((string) $user->email)));
    }

    private function fetchUsersByNameInsensitive(Collection $names): Collection
    {
        $normalizedNames = $names
            ->map(fn ($name) => $this->normalizePersonName((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedNames->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $normalizedNames->all())
            ->get();

        // Em caso de nomes duplicados no sistema, não faz vinculação automática por nome.
        $grouped = $users->groupBy(fn ($user) => $this->normalizePersonName((string) $user->name));

        return $grouped
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => $group->first());
    }

    private function fetchParticipantesByCpfInsensitive(Collection $cpfs): Collection
    {
        $normalizedCpfs = $cpfs
            ->map(fn ($cpf) => $this->normalizeCpf((string) $cpf))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedCpfs->isEmpty()) {
            return collect();
        }

        $participantes = Participante::query()
            ->with('user')
            ->whereIn(
                DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', ''), ' ', '')"),
                $normalizedCpfs->all()
            )
            ->get();

        return $participantes->mapWithKeys(function ($participante) {
            $cpf = $this->normalizeCpf((string) ($participante->cpf ?? ''));

            return $cpf !== '' ? [$cpf => $participante] : [];
        });
    }

    private function normalizePersonName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)) ?: '');
    }

    private function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?: '';
    }

    private function formatMoodleHeadersForError(array $headersRaw): string
    {
        $headers = collect($headersRaw)
            ->map(fn ($h) => trim((string) $h))
            ->filter(fn ($h) => $h !== '')
            ->values()
            ->all();

        if (empty($headers)) {
            return '[sem cabeçalhos na primeira linha]';
        }

        return '['.implode(', ', $headers).']';
    }

    /**
     * Lê o arquivo e guarda TODAS as linhas na sessão. Redireciona para a prévia paginada.
     */
    public function cadastro(Request $request, Evento $evento, InscricaoImportValidator $validator, ParticipanteImportIdentityResolver $resolver)
    {
        if ($request->input('atividade_id') === '' || $request->input('atividade_id') === null) {
            $request->merge(['atividade_id' => null]);
        }

        $validated = $request->validate([
            'your_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'origem' => ['nullable', 'string', 'max:255'],
            'atividade_id' => [
                'nullable',
                'integer',
                Rule::exists('atividades', 'id')->where('evento_id', $evento->id),
            ],
        ]);

        $modoTodosMomentos = $validated['atividade_id'] === null;
        $origemImportacao = trim((string) ($validated['origem'] ?? ''));

        $atividadesEvento = $evento->atividades()
            ->orderBy('dia')
            ->orderBy('hora_inicio')
            ->get();

        if ($modoTodosMomentos && $atividadesEvento->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['atividade_id' => 'Este evento não possui momentos cadastrados.']);
        }

        if (! $modoTodosMomentos) {
            $atividade = $atividadesEvento->firstWhere('id', (int) $validated['atividade_id']);
            if (! $atividade) {
                return back()
                    ->withErrors(['atividade_id' => 'Momento inválido para este evento.'])
                    ->withInput();
            }
        }

        try {
            $uploadedFile = $request->file('your_file');
            $extension = $uploadedFile?->getClientOriginalExtension() ?: 'xlsx';
            $tmpRelativePath = $uploadedFile->storeAs(
                'tmp/imports',
                'inscricoes_'.Str::uuid().'.'.$extension
            );
            $tmpAbsolutePath = Storage::path($tmpRelativePath);

            $bestRows = collect();
            $bestScore = -1;
            $reader = IOFactory::createReaderForFile($tmpAbsolutePath);
            $reader->setReadDataOnly(config('excel.imports.read_only', true));
            $reader->setReadEmptyCells(! config('excel.imports.ignore_empty', false));
            $workbook = $reader->load($tmpAbsolutePath);
            $worksheetInfo = collect(iterator_to_array($workbook->getWorksheetIterator()))
                ->filter(fn (Worksheet $sheet) => $sheet->getSheetState() === Worksheet::SHEETSTATE_VISIBLE
                    && mb_strtolower(trim($sheet->getTitle())) !== '_valid')
                ->map(fn (Worksheet $sheet) => [
                    'worksheetName' => $sheet->getTitle(),
                    'totalRows' => $sheet->getHighestRow(),
                ])->values()->all();
            $workbook->disconnectWorksheets();
            unset($workbook);
            $prototype = new ParticipantesPreviewImport(1, $worksheetInfo);
            $maxHeaderRow = max(1, min(30, (int) collect($worksheetInfo)->max('totalRows') - 1));

            for ($headerRow = 1; $headerRow <= $maxHeaderRow; $headerRow++) {
                $attempt = $prototype->withHeadingRow($headerRow);
                Excel::import($attempt, $tmpAbsolutePath);

                $candidateRows = collect($attempt->rows)->values();
                $score = $candidateRows->filter(function ($row) {
                    $nome = trim((string) ($row['nome'] ?? ''));
                    $email = trim((string) ($row['email'] ?? ''));
                    $cpf = trim((string) ($row['cpf'] ?? ''));
                    $telefone = trim((string) ($row['telefone'] ?? ''));

                    return $nome !== '' || $email !== '' || $cpf !== '' || $telefone !== '';
                })->count();

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRows = $candidateRows;
                }
            }

            $rows = $bestRows
                ->filter(fn ($row) => collect($row)->except(['linha_original', 'aba_original'])
                    ->contains(fn ($value, $key) => ! str_ends_with($key, '_ok')
                        && is_scalar($value) && trim((string) $value) !== ''))
                ->values()
                ->all();

            if (empty($rows)) {
                $rows = $this->parseParticipantesSpreadsheetFallback($tmpAbsolutePath)->all();
            }

            if (empty($rows)) {
                Storage::delete($tmpRelativePath);

                return back()
                    ->withErrors(['your_file' => 'Não foi possível ler linhas da planilha. Verifique se os dados estão em células (não imagem), com cabeçalhos como nome, email, cpf, telefone e municipio.'])
                    ->withInput();
            }

            Storage::delete($tmpRelativePath);

            $rows = $validator->validate($rows);
            $validator->validateProfiles($rows, $resolver);
            $sessionKey = "import_preview_evento_{$evento->id}_".Str::uuid();

            session([$sessionKey => [
                'evento_id' => $evento->id,
                'user_id' => $request->user()->id,
                'modo_todos_momentos' => $modoTodosMomentos,
                'atividade_id' => $modoTodosMomentos ? null : $validated['atividade_id'],
                'origem' => $origemImportacao !== '' ? $origemImportacao : null,
                'rows' => $rows,
            ]]);

            return redirect()->route('inscricoes.preview', array_filter([
                'evento' => $evento,
                'session_key' => $sessionKey,
                'atividade_id' => $modoTodosMomentos ? null : $validated['atividade_id'],
            ]));
        } catch (ValidationException $e) {
            if (! empty($tmpRelativePath ?? null)) {
                Storage::delete($tmpRelativePath);
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            if (! empty($tmpRelativePath ?? null)) {
                Storage::delete($tmpRelativePath);
            }

            return back()
                ->withErrors(['your_file' => 'Falha ao processar o arquivo: '.$e->getMessage()])
                ->withInput();
        }
    }

    private function parseParticipantesSpreadsheetFallback(string $absolutePath): Collection
    {
        $aliases = [
            'nome' => ['nome', 'name'],
            'email' => ['email', 'e mail', 'e_mail', 'mail'],
            'cpf' => ['cpf', 'documento'],
            'telefone' => ['telefone', 'celular', 'fone', 'telefone celular', 'telefone_celular'],
            'municipio' => ['municipio', 'município', 'cidade'],
            'estado' => ['estado', 'uf', 'estado_sigla', 'sigla estado'],
            'tipo_organizacao' => ['tipo de organizacao', 'tipo_da_organizacao', 'tipo organizacao', 'tipoorganizacao'],
            'organizacao' => ['organizacao', 'organização', 'escola_unidade', 'escola unidade', 'organizacao_nome'],
            'tag' => ['tag'],
            'data_nascimento' => ['data_nascimento', 'data de nascimento', 'nascimento'],
            'sexo' => ['sexo'],
            'data_entrada' => ['data_entrada', 'data entrada', 'data de entrada'],
            ...DemograficoNormalizerService::headerAliases(),
        ];

        $demograficoNormalizer = new DemograficoNormalizerService;

        $aliasLookup = [];
        foreach ($aliases as $field => $terms) {
            foreach ($terms as $term) {
                $aliasLookup[$this->normalizeSpreadsheetHeader($term)] = $field;
            }
        }

        $municipiosLookup = Municipio::query()
            ->with('estado:id,nome,sigla')
            ->select('id', 'nome', 'estado_id')
            ->get()
            ->groupBy(fn ($m) => $this->normalizeSpreadsheetHeader((string) $m->nome))
            ->map(fn ($municipios) => $municipios->map(fn ($municipio) => [
                'id' => (int) $municipio->id,
                'estado_nome' => (string) $municipio->estado?->nome,
                'estado_sigla' => (string) $municipio->estado?->sigla,
            ])->values()->all())
            ->all();

        $spreadsheet = IOFactory::load($absolutePath);
        $bestRows = collect();
        $bestScore = -1;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE
                || mb_strtolower(trim($sheet->getTitle())) === '_valid') {
                continue;
            }

            $highestRow = (int) $sheet->getHighestDataRow();
            if ($highestRow < 1) {
                continue;
            }

            $highestColumnIndex = Coordinate::columnIndexFromString((string) $sheet->getHighestDataColumn());
            $maxHeaderRow = min(30, $highestRow);

            for ($headerRow = 1; $headerRow <= $maxHeaderRow; $headerRow++) {
                $fieldToColumn = [];

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $rawHeader = trim((string) $sheet->getCell([$col, $headerRow])->getCalculatedValue());
                    if ($rawHeader === '') {
                        continue;
                    }

                    $field = $aliasLookup[$this->normalizeSpreadsheetHeader($rawHeader)] ?? null;
                    if ($field && ! isset($fieldToColumn[$field])) {
                        $fieldToColumn[$field] = $col;
                    }
                }

                if (! isset($fieldToColumn['nome']) && ! isset($fieldToColumn['email'])) {
                    continue;
                }

                $rows = collect();

                for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
                    $nome = $this->sheetCellValue($sheet, $fieldToColumn['nome'] ?? null, $rowNumber);
                    $email = $this->sheetCellValue($sheet, $fieldToColumn['email'] ?? null, $rowNumber);
                    $cpfRaw = $this->sheetCellValue($sheet, $fieldToColumn['cpf'] ?? null, $rowNumber);
                    $telefoneRaw = $this->sheetCellValue($sheet, $fieldToColumn['telefone'] ?? null, $rowNumber);
                    $municipioNome = $this->sheetCellValue($sheet, $fieldToColumn['municipio'] ?? null, $rowNumber);
                    $estado = $this->sheetCellValue($sheet, $fieldToColumn['estado'] ?? null, $rowNumber);
                    if (preg_match('/^(.+?)\s*(?:-|\/)\s*([A-Za-z]{2})$/u', $municipioNome, $matches)) {
                        $municipioNome = trim($matches[1]);
                        if ($estado === '') {
                            $estado = mb_strtoupper($matches[2]);
                        }
                    }
                    $tipoOrganizacao = $this->sheetCellValue($sheet, $fieldToColumn['tipo_organizacao'] ?? null, $rowNumber);
                    $organizacao = $this->sheetCellValue($sheet, $fieldToColumn['organizacao'] ?? null, $rowNumber);
                    $tag = $this->sheetCellValue($sheet, $fieldToColumn['tag'] ?? null, $rowNumber);
                    $dataEntrada = $this->sheetCellValue($sheet, $fieldToColumn['data_entrada'] ?? null, $rowNumber);
                    $dataNascimento = $this->sheetCellValue($sheet, $fieldToColumn['data_nascimento'] ?? null, $rowNumber);
                    $sexo = $this->sheetCellValue($sheet, $fieldToColumn['sexo'] ?? null, $rowNumber);
                    $hasDemographicData = collect(array_keys(DemograficoNormalizerService::headerAliases()))
                        ->contains(fn ($field) => $this->sheetCellValue($sheet, $fieldToColumn[$field] ?? null, $rowNumber) !== '');

                    if (
                        $nome === '' &&
                        $email === '' &&
                        $cpfRaw === '' &&
                        $telefoneRaw === '' &&
                        $municipioNome === '' &&
                        $estado === '' &&
                        $tipoOrganizacao === '' &&
                        $organizacao === '' &&
                        $tag === '' &&
                        $dataEntrada === '' &&
                        $dataNascimento === '' &&
                        $sexo === '' &&
                        ! $hasDemographicData
                    ) {
                        continue;
                    }

                    $municipioId = null;
                    if ($municipioNome !== '') {
                        $candidatos = collect($municipiosLookup[$this->normalizeSpreadsheetHeader($municipioNome)] ?? []);
                        if ($estado !== '') {
                            $estadoNormalizado = $this->normalizeSpreadsheetHeader($estado);
                            $candidatos = $candidatos->filter(fn (array $municipio) => $this->normalizeSpreadsheetHeader($municipio['estado_nome']) === $estadoNormalizado
                                || $this->normalizeSpreadsheetHeader($municipio['estado_sigla']) === $estadoNormalizado
                            );
                        }
                        if ($candidatos->count() === 1) {
                            $municipioId = $candidatos->first()['id'];
                        }
                    }

                    if ($tipoOrganizacao === '' && $organizacao !== '') {
                        $tipoOrganizacao = $organizacao;
                        $organizacao = '';
                    }

                    $demograficosRaw = [];
                    foreach (DemograficoNormalizerService::headerAliases() as $campo => $campoAliases) {
                        $demograficosRaw[$campo] = $this->sheetCellValue($sheet, $fieldToColumn[$campo] ?? null, $rowNumber);
                        if ($demograficosRaw[$campo] === '') {
                            $demograficosRaw[$campo] = null;
                        }
                    }
                    $demograficosNormalizados = $demograficoNormalizer->normalizeRow($demograficosRaw);

                    $rows->push(array_merge([
                        'linha_original' => $rowNumber,
                        'aba_original' => $sheet->getTitle(),
                        'nome' => $nome,
                        'email' => $email,
                        'cpf' => preg_replace('/\D+/', '', $cpfRaw) ?: null,
                        'telefone' => preg_replace('/\D+/', '', $telefoneRaw) ?: null,
                        'municipio' => $municipioNome,
                        'municipio_id' => $municipioId,
                        'estado' => $estado,
                        'tipo_organizacao' => $tipoOrganizacao,
                        'tipo_organizacao_ok' => true,
                        'escola_unidade' => $organizacao,
                        'tag' => $tag !== '' ? $tag : null,
                        'tag_ok' => true,
                        'data_nascimento' => $dataNascimento,
                        'sexo' => $sexo !== '' ? $sexo : null,
                        'data_entrada' => $dataEntrada,
                    ], $demograficosNormalizados));
                }

                $score = $rows->filter(fn ($row) => trim((string) ($row['nome'] ?? '')) !== '' || trim((string) ($row['email'] ?? '')) !== '')->count();
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRows = $rows;
                }
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $bestRows->values();
    }

    private function normalizeSpreadsheetHeader(string $value): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        return preg_replace('/\s+/', ' ', $normalized) ?: '';
    }

    private function sheetCellValue(Worksheet $sheet, ?int $column, int $row): string
    {
        if (! $column) {
            return '';
        }

        return trim((string) $sheet->getCell([$column, $row])->getCalculatedValue());
    }

    /**
     * Mostra a prévia paginada (sem gravar no banco).
     */
    public function preview(Request $request, Evento $evento, ParticipanteImportIdentityResolver $resolver)
    {
        $request->validate(['session_key' => 'required|string|max:150']);
        $sessionKey = $request->query('session_key');
        $sessionPayload = $this->inscricaoPreviewData($request, $evento, $sessionKey);

        if (! is_array($sessionPayload) || empty($sessionPayload['rows'] ?? [])) {
            return redirect()->route('inscricoes.import', $evento)
                ->withErrors(['your_file' => 'Sessão de importação vazia/expirada. Envie o arquivo novamente.']);
        }

        $modoTodosMomentos = ! empty($sessionPayload['modo_todos_momentos']);

        if ($modoTodosMomentos) {
            $atividadesEscopo = $evento->atividades()
                ->orderBy('dia')
                ->orderBy('hora_inicio')
                ->get();

            if ($atividadesEscopo->isEmpty()) {
                return redirect()->route('inscricoes.import', $evento)
                    ->withErrors(['atividade_id' => 'Este evento não possui momentos cadastrados.']);
            }

            $atividade = null;
        } else {
            $atividadeId = $request->query('atividade_id') ?? ($sessionPayload['atividade_id'] ?? null);

            if (! $atividadeId) {
                return redirect()->route('inscricoes.import', $evento)
                    ->withErrors(['atividade_id' => 'Momento da importação não encontrado. Inicie o processo novamente.']);
            }

            $atividade = $evento->atividades()
                ->whereKey($atividadeId)
                ->first();

            if (! $atividade) {
                return redirect()->route('inscricoes.import', $evento)
                    ->withErrors(['atividade_id' => 'Momento informado não pertence a este evento.']);
            }

            $atividadesEscopo = collect([$atividade]);
        }

        $allRows = collect($sessionPayload['rows']);
        $origemImportacao = trim((string) ($sessionPayload['origem'] ?? ''));

        $resumoImportacao = $resolver->summarize($allRows->all());

        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $page = (int) max(1, $request->query('page', 1));
        $total = $allRows->count();

        $slice = $allRows->slice(($page - 1) * $perPage, $perPage)->values();

        $queryPreview = array_filter([
            'session_key' => $sessionKey,
            'per_page' => $perPage,
            'atividade_id' => $modoTodosMomentos ? null : $atividade->id,
        ], fn ($v) => $v !== null && $v !== '');

        $rowsPaginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => route('inscricoes.preview', $evento),
                'query' => $queryPreview,
            ]
        );

        $globalOffset = ($page - 1) * $perPage;

        $municipios = Municipio::with('estado')->orderBy('nome')->get(['id', 'nome', 'estado_id']);

        $organizacoes = config('engaja.organizacoes', []);
        $participanteTags = config('engaja.participante_tags', Participante::TAGS);

        return view('inscricoes.preview', [
            'evento' => $evento,
            'atividade' => $atividade,
            'atividadesEscopo' => $atividadesEscopo,
            'modoTodosMomentos' => $modoTodosMomentos,
            'rows' => $rowsPaginator,
            'globalOffset' => $globalOffset,
            'sessionKey' => $sessionKey,
            'municipios' => $municipios,
            'organizacoes' => $organizacoes,
            'participanteTags' => $participanteTags,
            'demograficos' => config('engaja.demograficos'),
            'usuariosExistentesCount' => $resumoImportacao['usuariosExistentesCount'],
            'usuariosNovosCount' => $resumoImportacao['usuariosNovosCount'],
            'identityErrors' => $resumoImportacao['identityErrors'],
            'origemImportacao' => $origemImportacao,
        ]);
    }

    private function inscricaoPreviewData(Request $request, Evento $evento, string $sessionKey): array
    {
        if (! Str::startsWith($sessionKey, "import_preview_evento_{$evento->id}_")) {
            return [];
        }

        $preview = session($sessionKey, []);
        if (! is_array($preview)
            || ($preview['evento_id'] ?? null) !== $evento->id
            || ($preview['user_id'] ?? null) !== $request->user()->id
            || ! is_array($preview['rows'] ?? null)) {
            return [];
        }

        return $preview;
    }

    /**
     * Salva as edições da PÁGINA ATUAL na sessão.
     */
    public function savePage(Request $request, Evento $evento, InscricaoImportValidator $validator, ParticipanteImportIdentityResolver $resolver)
    {
        $fields = ['nome', 'email', 'cpf', 'telefone', 'municipio_id', 'estado', 'tipo_organizacao', 'escola_unidade', 'tag', 'data_nascimento', 'sexo', 'data_entrada'];
        $rules = [
            'session_key' => 'required|string|max:150',
            'rows' => 'required|array',
            'rows.*.nome' => 'sometimes|nullable|string|max:255',
            'rows.*.email' => 'sometimes|nullable|string|max:255',
            'rows.*.cpf' => 'sometimes|nullable|string|max:255',
            'rows.*.telefone' => 'sometimes|nullable|string|max:255',
            'rows.*.municipio_id' => ['sometimes', 'nullable', 'integer', Rule::exists('municipios', 'id')->whereNull('deleted_at')],
            'rows.*.estado' => 'sometimes|nullable|string|max:255',
            'rows.*.tipo_organizacao' => 'sometimes|nullable|string|max:255',
            'rows.*.escola_unidade' => 'sometimes|nullable|string|max:255',
            'rows.*.tag' => 'sometimes|nullable|string|max:255',
            'rows.*.data_nascimento' => 'sometimes|nullable|string|max:255',
            'rows.*.sexo' => 'sometimes|nullable|string|max:255',
            'rows.*.data_entrada' => 'sometimes|nullable|date_format:Y-m-d',
        ];
        foreach (config('engaja.demograficos', []) as $field => $definition) {
            $fields[] = $field;
            $rules['rows.*.'.$field] = ['sometimes', 'nullable', 'string', 'max:255', Rule::in($definition['opcoes'])];
            if (! empty($definition['campo_outro'])) {
                $fields[] = $definition['campo_outro'];
                $rules['rows.*.'.$definition['campo_outro']] = 'sometimes|nullable|string|max:255';
            }
        }
        $rules['rows.*'] = 'array:'.implode(',', $fields);
        $data = $request->validate($rules);

        $sessionKey = $data['session_key'];
        $sessionPayload = $this->inscricaoPreviewData($request, $evento, $sessionKey);
        $allRows = $sessionPayload['rows'] ?? [];
        if ($allRows === []) {
            return back()->withErrors(['rows' => 'Sessão expirada. Reenvie o arquivo.']);
        }

        foreach ($data['rows'] as $globalIndex => $row) {
            if (! ctype_digit((string) $globalIndex) || ! array_key_exists($globalIndex, $allRows)) {
                throw ValidationException::withMessages(['rows' => 'A linha editada não pertence a esta importação.']);
            }
            $allRows[$globalIndex] = array_merge($allRows[$globalIndex], $row);
        }

        $sessionPayload['rows'] = $validator->validate($allRows);
        $validator->validateProfiles($sessionPayload['rows'], $resolver);
        session([$sessionKey => $sessionPayload]);

        return redirect()->route('inscricoes.preview', array_filter([
            'evento' => $evento,
            'session_key' => $sessionKey,
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => max(1, min(100, (int) $request->query('per_page', 50))),
            'atividade_id' => $sessionPayload['atividade_id'],
        ]))->with('success', 'Alterações desta página salvas.');
    }

    /**
     * Confirma TUDO: lê as linhas da sessão e grava no banco.
     */
    public function confirmar(Request $request, Evento $evento, InscricaoImportValidator $validator, ParticipanteImportIdentityResolver $resolver)
    {
        if ($request->input('atividade_id') === '' || $request->input('atividade_id') === null) {
            $request->merge(['atividade_id' => null]);
        }

        $validatedBase = $request->validate([
            'session_key' => 'required|string|max:150',
        ]);

        $sessionKey = $validatedBase['session_key'];
        $sessionPayload = $this->inscricaoPreviewData($request, $evento, $sessionKey);

        if (! is_array($sessionPayload) || empty($sessionPayload['rows'] ?? [])) {
            return back()->withErrors(['rows' => 'Sessão de importação vazia/expirada. Reenvie o arquivo.']);
        }

        $modoTodosMomentos = ! empty($sessionPayload['modo_todos_momentos']);

        if ($modoTodosMomentos) {
            $atividadesAlvo = $evento->atividades()
                ->orderBy('dia')
                ->orderBy('hora_inicio')
                ->get();

            if ($atividadesAlvo->isEmpty()) {
                return back()->withErrors(['atividade_id' => 'Este evento não possui momentos cadastrados.']);
            }

            if ($request->input('atividade_id') !== null) {
                return back()->withErrors(['atividade_id' => 'Modo da importação não corresponde ao processo em andamento.']);
            }
        } else {
            $validated = $request->validate([
                'atividade_id' => [
                    'required',
                    'integer',
                    Rule::exists('atividades', 'id')->where('evento_id', $evento->id),
                ],
            ]);

            $atividadeId = $validated['atividade_id'];
            $sessionAtividade = $sessionPayload['atividade_id'] ?? null;

            if ($sessionAtividade && (int) $sessionAtividade !== (int) $atividadeId) {
                return back()->withErrors(['atividade_id' => 'Momento informado não corresponde ao processo em andamento. Refaça a importação.']);
            }

            $atividade = $evento->atividades()
                ->whereKey($atividadeId)
                ->first();

            if (! $atividade) {
                return back()->withErrors(['atividade_id' => 'Momento informado não pertence a este evento.']);
            }

            $atividadesAlvo = collect([$atividade]);
        }

        $rows = $validator->validate($sessionPayload['rows']);
        $origemImportacao = trim((string) ($sessionPayload['origem'] ?? ''));

        DB::transaction(function () use ($rows, $evento, $atividadesAlvo, $origemImportacao, $validator, $resolver) {
            $resolver->lock();
            $validator->validateProfiles($rows, $resolver);
            $participantes = [];
            $users = [];
            $demograficosConfig = config('engaja.demograficos', []);

            $toDate = function ($raw) {
                if ($raw === null) {
                    return null;
                }
                $s = trim((string) $raw);
                if ($s === '') {
                    return null;
                }
                try {
                    if (is_numeric($s)) {
                        return Carbon::instance(ExcelDate::excelToDateTimeObject($s))->format('Y-m-d');
                    }
                    if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $s)) {
                        return Carbon::createFromFormat('d/m/Y', $s)->format('Y-m-d');
                    }

                    return Carbon::parse($s)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            };

            $tagOptions = config('engaja.participante_tags', Participante::TAGS);
            $tagLookup = array_fill_keys($tagOptions, true);

            $municipiosConhecidos = Municipio::withTrashed()
                ->with(['estado' => fn ($query) => $query->withTrashed()])
                ->get()
                ->groupBy(fn (Municipio $municipio) => $this->normalizeSpreadsheetHeader($municipio->nome));
            $municipiosResolvidos = [];

            foreach ($rows as $rowIndex => $row) {
                $participante = $resolver->resolve($row);
                $user = $participante->user;

                $dadosDemograficos = [];
                foreach ($demograficosConfig as $campo => $definicao) {
                    $valor = $row[$campo] ?? null;
                    if (! is_string($valor) || trim($valor) === '') {
                        continue;
                    }

                    $dadosDemograficos[$campo] = $valor;

                    // Ler também o campo_outro diretamente da sessão
                    $campoOutro = $definicao['campo_outro'] ?? null;
                    if ($campoOutro !== null) {
                        $valorOutro = $row[$campoOutro] ?? null;
                        $dadosDemograficos[$campoOutro] = is_string($valorOutro) && trim($valorOutro) !== ''
                            ? $valorOutro
                            : null;
                    }
                }

                $user->fill($dadosDemograficos);
                if ($user->isDirty()) {
                    $user->save();
                }

                $tipoOrgRaw = $row['tipo_organizacao'] ?? $row['organizacao'] ?? null;
                $tipoOrg = is_string($tipoOrgRaw) ? trim($tipoOrgRaw) : null;

                $orgRaw = $row['escola_unidade'] ?? $row['organizacao_nome'] ?? null;
                if ($orgRaw === null && ! isset($row['tipo_organizacao'])) {
                    $orgRaw = $row['organizacao'] ?? null;
                }
                $org = is_string($orgRaw) ? trim($orgRaw) : null;

                $tagRaw = $row['tag'] ?? null;
                $tag = is_string($tagRaw) ? trim($tagRaw) : null;
                if ($tag === '') {
                    $tag = null;
                } elseif (! isset($tagLookup[$tag])) {
                    $tag = null;
                }

                $telefoneRaw = $row['telefone'] ?? null;
                $telefoneValue = is_string($telefoneRaw)
                    ? trim($telefoneRaw)
                    : (is_scalar($telefoneRaw) ? trim((string) $telefoneRaw) : null);

                $telefoneValue = $telefoneValue !== '' ? $telefoneValue : null;

                $municipioId = $this->resolveMunicipioImportacao(
                    $row,
                    $municipiosConhecidos,
                    $municipiosResolvidos,
                    (int) ($row['linha_original'] ?? $rowIndex + 2),
                );

                $dados = [
                    'municipio_id' => $municipioId,
                    'cpf' => $row['cpf'],
                    'telefone' => $telefoneValue,
                    'escola_unidade' => ($org !== '') ? $org : null,
                    'tipo_organizacao' => ($tipoOrg !== '') ? $tipoOrg : null,
                    'tag' => $tag,
                    'data_nascimento' => $row['data_nascimento'],
                    'sexo' => $row['sexo'],
                    'data_entrada' => $toDate($row['data_entrada'] ?? null),
                ];

                // Campos vazios não apagam informações já cadastradas.
                $participante->fill(array_filter($dados, fn ($value) => $value !== null));
                if ($participante->isDirty()) {
                    $participante->save();
                }
                $resolver->remember($participante);
                $participantes[$participante->id] = $participante;
                $users[$user->id] = $user;
            }

            if ($origemImportacao !== '') {
                $now = now();
                $origens = collect($users)->map(fn ($user) => [
                    'evento_id' => $evento->id,
                    'user_id' => $user->id,
                    'origem' => $origemImportacao,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all();
                foreach (array_chunk($origens, 1000) as $chunk) {
                    DB::table('origem_usuario')->upsert(
                        $chunk,
                        ['evento_id', 'user_id'],
                        ['origem', 'updated_at']
                    );
                }
            }

            foreach ($participantes as $participante) {
                foreach ($atividadesAlvo as $atividade) {
                    $this->inscreverParticipanteNoMomento($evento, $atividade, $participante);
                }
            }
        });

        session()->forget($sessionKey);

        return redirect()
            ->route('eventos.show', $evento)
            ->with('success', 'Importação confirmada e salva com sucesso!');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<string, Collection<int, Municipio>>  $municipiosConhecidos
     * @param  array<string, int>  $municipiosResolvidos
     */
    private function resolveMunicipioImportacao(
        array $row,
        Collection $municipiosConhecidos,
        array &$municipiosResolvidos,
        int $linha,
    ): ?int {
        $municipioId = (int) ($row['municipio_id'] ?? 0);
        if ($municipioId > 0) {
            return $municipioId;
        }

        $municipioNome = trim((string) ($row['municipio'] ?? ''));
        if ($municipioNome === '') {
            return null;
        }

        $estado = trim((string) ($row['estado'] ?? $row['uf'] ?? $row['estado_sigla'] ?? ''));
        if (preg_match('/^(.+?)\s*(?:-|\/)\s*([A-Za-z]{2})$/u', $municipioNome, $matches)) {
            $municipioNome = trim($matches[1]);
            if ($estado === '') {
                $estado = mb_strtoupper($matches[2]);
            }
        }

        $nomeNormalizado = $this->normalizeSpreadsheetHeader($municipioNome);
        $estadoNormalizado = $this->normalizeSpreadsheetHeader($estado);
        $cacheKey = $nomeNormalizado.'|'.$estadoNormalizado;

        if (isset($municipiosResolvidos[$cacheKey])) {
            return $municipiosResolvidos[$cacheKey];
        }

        $candidatos = collect($municipiosConhecidos->get($nomeNormalizado, collect()));
        if ($estadoNormalizado !== '') {
            $candidatos = $candidatos->filter(function (Municipio $municipio) use ($estadoNormalizado) {
                $sigla = $this->normalizeSpreadsheetHeader((string) $municipio->estado?->sigla);
                $nome = $this->normalizeSpreadsheetHeader((string) $municipio->estado?->nome);

                return $sigla === $estadoNormalizado || $nome === $estadoNormalizado;
            })->values();
        }

        if ($candidatos->count() === 1) {
            /** @var Municipio $municipio */
            $municipio = $candidatos->first();
            if ($municipio->estado?->trashed()) {
                $municipio->estado->restore();
            }
            if ($municipio->trashed()) {
                $municipio->restore();
            }

            return $municipiosResolvidos[$cacheKey] = $municipio->id;
        }

        $mensagem = $candidatos->isEmpty()
            ? "Linha {$linha}: município \"{$municipioNome}\" não encontrado na base de localidades."
            : "Linha {$linha}: há mais de um município chamado \"{$municipioNome}\". Informe o estado ou UF para identificá-lo.";

        throw ValidationException::withMessages(['rows' => $mensagem]);
    }

    public function inscritos(Request $request, Evento $evento)
    {
        $search = $request->query('q');
        $municipioId = $request->query('municipio_id');
        $atividadeId = $request->query('atividade_id');
        $perPage = (int) $request->query('per_page', 50);

        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $atividades = $evento->atividades()
            ->orderBy('dia')
            ->orderBy('hora_inicio')
            ->get(['id', 'descricao', 'dia', 'hora_inicio']);

        $inscricoesQuery = Inscricao::query()
            ->with([
                'participante.user:id,name,email',
                'participante.municipio.estado:id,nome,sigla',
                'atividade:id,descricao,dia,hora_inicio',
            ])
            ->where('evento_id', $evento->id)
            ->whereNull('deleted_at')
            ->when($atividadeId, fn ($q) => $q->where('atividade_id', $atividadeId))
            ->when($municipioId, fn ($q) => $q->whereHas('participante', fn ($pq) => $pq->where('municipio_id', $municipioId)))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $like = "%{$search}%";
                    $w->whereHas('participante.user', function ($uq) use ($like) {
                        $uq->where('name', 'ilike', $like)
                            ->orWhere('email', 'ilike', $like);
                    })
                        ->orWhereHas('participante', function ($pq) use ($like) {
                            $pq->where('cpf', 'ilike', $like)
                                ->orWhere('telefone', 'ilike', $like);
                        })
                        ->orWhereHas('participante.municipio', function ($mq) use ($like) {
                            $mq->where('nome', 'ilike', $like);
                        });
                });
            })
            ->orderByDesc('id');

        $inscricoes = $inscricoesQuery
            ->paginate($perPage)
            ->appends($request->query());

        return view('inscricoes.index', [
            'evento' => $evento,
            'inscricoes' => $inscricoes,
            'municipios' => $municipios,
            'atividades' => $atividades,
            'search' => $search,
            'municipioId' => $municipioId,
            'atividadeId' => $atividadeId,
            'perPage' => $perPage,
        ]);
    }

    public function selecionar(Request $request, Evento $evento)
    {
        $search = trim((string) $request->query('q', ''));
        $municipioId = $request->query('municipio_id');
        $tagSelecionada = $request->query('tag');
        $atividadeId = $request->query('atividade_id');
        $perPage = (int) $request->query('per_page', 25);

        if (! in_array($perPage, [25, 50, 100, 200], true)) {
            $perPage = 25;
        }

        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $atividades = $evento->atividades()
            ->orderBy('dia')
            ->orderBy('hora_inicio')
            ->get(['id', 'descricao', 'dia', 'hora_inicio']);

        $atividadeSelecionada = null;
        if ($atividadeId) {
            $atividadeSelecionada = $atividades->firstWhere('id', (int) $atividadeId);
            if (! $atividadeSelecionada) {
                $atividadeId = null;
            }
        }

        $apenasDisponiveis = $request->has('apenas_disponiveis')
            ? $request->boolean('apenas_disponiveis')
            : (bool) $atividadeId;

        if (! $atividadeId && ! $atividades->count()) {
            $apenasDisponiveis = false;
        }

        $participanteTags = config('engaja.participante_tags', Participante::TAGS);

        $inscricoesAtivas = Inscricao::query()
            ->where('evento_id', $evento->id)
            ->whereNull('deleted_at')
            ->get(['participante_id', 'atividade_id']);

        $inscritosEvento = $inscricoesAtivas
            ->pluck('participante_id')
            ->unique()
            ->all();

        $inscritosAtividade = $atividadeId
            ? $inscricoesAtivas
                ->filter(fn ($item) => (int) $item->atividade_id === (int) $atividadeId)
                ->pluck('participante_id')
                ->unique()
                ->all()
            : [];

        $idsAtividadesEvento = $atividades->pluck('id')->map(fn ($id) => (int) $id)->all();
        $totalMomentosEvento = count($idsAtividadesEvento);

        $momentosInscritosPorParticipante = [];
        if ($totalMomentosEvento > 0) {
            $idSet = array_flip($idsAtividadesEvento);
            foreach ($inscricoesAtivas as $ins) {
                $aid = $ins->atividade_id !== null ? (int) $ins->atividade_id : null;
                if ($aid !== null && isset($idSet[$aid])) {
                    $pid = (int) $ins->participante_id;
                    $momentosInscritosPorParticipante[$pid] = ($momentosInscritosPorParticipante[$pid] ?? 0) + 1;
                }
            }
        }

        $participantesQuery = Participante::query()
            ->with([
                'user:id,name,email',
                'municipio.estado:id,nome,sigla',
            ])
            ->whereNull('participantes.deleted_at')
            ->when($municipioId, fn ($q) => $q->where('municipio_id', $municipioId))
            ->when($tagSelecionada, fn ($q) => $q->where('tag', $tagSelecionada))
            ->when($search, function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('cpf', 'ilike', $like)
                        ->orWhere('telefone', 'ilike', $like)
                        ->orWhereHas('user', function ($uq) use ($like) {
                            $uq->where('name', 'ilike', $like)
                                ->orWhere('email', 'ilike', $like);
                        })
                        ->orWhereHas('municipio', function ($mq) use ($like) {
                            $mq->where('nome', 'ilike', $like);
                        });
                });
            });

        if ($apenasDisponiveis && $totalMomentosEvento > 0) {
            if ($atividadeId) {
                $participantesQuery->whereDoesntHave('inscricoes', function ($q) use ($evento, $atividadeId) {
                    $q->where('evento_id', $evento->id)
                        ->where('atividade_id', (int) $atividadeId)
                        ->whereNull('deleted_at');
                });
            } else {
                $placeholders = implode(',', array_fill(0, $totalMomentosEvento, '?'));
                $participantesQuery->whereRaw(
                    '(SELECT COUNT(DISTINCT atividade_id) FROM inscricaos WHERE participantes.id = inscricaos.participante_id'
                    .' AND inscricaos.evento_id = ? AND inscricaos.deleted_at IS NULL'
                    .' AND inscricaos.atividade_id IN ('.$placeholders.')) < ?',
                    array_merge([$evento->id], $idsAtividadesEvento, [$totalMomentosEvento])
                );
            }
        }

        $participantes = $participantesQuery
            ->leftJoin('users as users_order', 'users_order.id', '=', 'participantes.user_id')
            ->select('participantes.*')
            ->orderByRaw('LOWER(users_order.name) ASC NULLS LAST')
            ->paginate($perPage)
            ->appends($request->query());

        return view('inscricoes.selecionar', [
            'evento' => $evento,
            'participantes' => $participantes,
            'municipios' => $municipios,
            'atividades' => $atividades,
            'participanteTags' => $participanteTags,
            'search' => $search,
            'municipioId' => $municipioId,
            'tagSelecionada' => $tagSelecionada,
            'atividadeId' => $atividadeId,
            'atividadeSelecionada' => $atividadeSelecionada,
            'apenasDisponiveis' => $apenasDisponiveis,
            'perPage' => $perPage,
            'inscritosNaAtividade' => $inscritosAtividade,
            'inscritosNoEvento' => $inscritosEvento,
            'totalMomentosEvento' => $totalMomentosEvento,
            'momentosInscritosPorParticipante' => $momentosInscritosPorParticipante,
        ]);
    }

    public function selecionarStore(Request $request, Evento $evento)
    {
        if ($request->input('atividade_id') === '' || $request->input('atividade_id') === null) {
            $request->merge(['atividade_id' => null]);
        }

        $validated = $request->validate([
            'atividade_id' => [
                'nullable',
                'integer',
                Rule::exists('atividades', 'id')->where('evento_id', $evento->id),
            ],
            'participantes' => ['required', 'array', 'min:1'],
            'participantes.*' => [
                'integer',
                Rule::exists('participantes', 'id'),
            ],
        ], [
            'participantes.required' => 'Selecione pelo menos um participante.',
            'participantes.min' => 'Selecione pelo menos um participante.',
        ]);

        $modoTodosOsMomentos = $validated['atividade_id'] === null;

        $atividadesAlvo = $modoTodosOsMomentos
            ? $evento->atividades()->orderBy('dia')->orderBy('hora_inicio')->get()
            : collect();

        if ($modoTodosOsMomentos && $atividadesAlvo->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['atividade_id' => 'Este evento não possui momentos cadastrados.']);
        }

        $participanteIds = array_values(array_unique($validated['participantes']));

        $participantes = Participante::whereIn('id', $participanteIds)->get();

        if ($participantes->isEmpty()) {
            return back()->withErrors(['participantes' => 'Nenhum participante válido foi selecionado.']);
        }

        $resultado = DB::transaction(function () use ($participantes, $evento, $validated, $modoTodosOsMomentos, $atividadesAlvo) {
            $totais = [
                'adicionados' => 0,
                'ignorados' => 0,
            ];

            if ($modoTodosOsMomentos) {
                foreach ($participantes as $participante) {
                    foreach ($atividadesAlvo as $atividade) {
                        $r = $this->inscreverParticipanteNoMomento($evento, $atividade, $participante);
                        if ($r === 'adicionado') {
                            $totais['adicionados']++;
                        } else {
                            $totais['ignorados']++;
                        }
                    }
                }
            } else {
                $atividade = $evento->atividades()
                    ->whereKey($validated['atividade_id'])
                    ->firstOrFail();

                foreach ($participantes as $participante) {
                    $r = $this->inscreverParticipanteNoMomento($evento, $atividade, $participante);
                    if ($r === 'adicionado') {
                        $totais['adicionados']++;
                    } else {
                        $totais['ignorados']++;
                    }
                }
            }

            return $totais;
        });

        if ($modoTodosOsMomentos) {
            $mensagem = "{$resultado['adicionados']} inscrição(ões) adicionada(s) ou reativada(s).";
            if ($resultado['ignorados'] > 0) {
                $mensagem .= " {$resultado['ignorados']} ignorada(s) (já inscrito no momento).";
            }
        } else {
            $mensagem = "{$resultado['adicionados']} participante(s) inscrito(s).";
            if ($resultado['ignorados'] > 0) {
                $mensagem .= " {$resultado['ignorados']} já estavam inscritos neste momento.";
            }
        }

        $queryParams = [
            'atividade_id' => $validated['atividade_id'],
            'q' => $request->input('q'),
            'municipio_id' => $request->input('municipio_id'),
            'tag' => $request->input('tag'),
            'per_page' => $request->input('per_page'),
            'apenas_disponiveis' => $request->has('apenas_disponiveis')
                ? ($request->boolean('apenas_disponiveis') ? 1 : 0)
                : 1,
        ];

        $queryParams = array_filter(
            $queryParams,
            fn ($value) => $value !== null && $value !== ''
        );

        return redirect()
            ->route('inscricoes.selecionar', array_merge(['evento' => $evento->id], $queryParams))
            ->with('success', $mensagem);
    }

    /**
     * @return 'adicionado'|'ignorado'
     */
    private function inscreverParticipanteNoMomento(Evento $evento, Atividade $atividade, Participante $participante): string
    {
        $inscricao = Inscricao::withTrashed()
            ->where('participante_id', $participante->id)
            ->where('atividade_id', $atividade->id)
            ->where('evento_id', $evento->id)
            ->first();

        if ($inscricao && $inscricao->deleted_at === null) {
            return 'ignorado';
        }

        if (! $inscricao) {
            $inscricao = Inscricao::withTrashed()
                ->where('participante_id', $participante->id)
                ->where('evento_id', $evento->id)
                ->whereNull('atividade_id')
                ->first();
        }

        if ($inscricao) {
            $inscricao->fill([
                'evento_id' => $evento->id,
                'atividade_id' => $atividade->id,
                'participante_id' => $participante->id,
                'ouvinte' => false,
            ]);
            $inscricao->deleted_at = null;
            $inscricao->save();
        } else {
            Inscricao::create([
                'evento_id' => $evento->id,
                'atividade_id' => $atividade->id,
                'participante_id' => $participante->id,
                'ouvinte' => false,
            ]);
        }

        return 'adicionado';
    }

    public function inscrever(Request $request, Evento $evento)
    {
        $user = $request->user();
        $participante = $user->participante; // já existe pelo booted()

        // existe ativo?
        $exists = \DB::table('inscricaos')
            ->where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return back()->with('info', 'Você já está inscrito neste evento.');
        }

        // já existiu (soft-deletada)? restaura
        $restored = \DB::table('inscricaos')
            ->where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null, 'updated_at' => now()]);

        if ($restored) {
            return back()->with('success', 'Inscrição reativada com sucesso!');
        }

        // cria nova
        \DB::table('inscricaos')->insert([
            'evento_id' => $evento->id,
            'participante_id' => $participante->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Inscrição realizada com sucesso!');
    }

    public function cancelar(Request $request, Evento $evento)
    {
        $user = $request->user();
        $participanteId = optional($user->participante)->id;

        if (! $participanteId) {
            return back()->with('error', 'Você não possui cadastro de participante.');
        }

        $affected = DB::table('inscricaos')
            ->where('evento_id', $evento->id)
            ->where('participante_id', $participanteId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected) {
            return back()->with('success', 'Inscrição cancelada.');
        }

        return back()->with('info', 'Você não está inscrito neste evento.');
    }

    public function create()
    { /* ... */
    }

    public function store(Request $request)
    { /* ... */
    }

    public function show(string $id)
    { /* ... */
    }

    public function edit(string $id)
    { /* ... */
    }

    public function update(Request $request, string $id)
    { /* ... */
    }

    public function destroy(string $id)
    { /* ... */
    }
}
