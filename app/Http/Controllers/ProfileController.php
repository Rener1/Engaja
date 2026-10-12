<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Atividade;
use App\Models\Certificado;
use App\Models\Inscricao;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['participante.municipio.estado']);

        // Se preferir carregar na view, pode remover isso:
        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $organizacoes = config('engaja.organizacoes', []);
        $participanteTags = config('engaja.participante_tags', Participante::TAGS);

        return view('profile.edit', [
            'user' => $user,
            'municipios' => $municipios,
            'organizacoes' => $organizacoes,
            'participanteTags' => $participanteTags,
        ]);
    }

    public function storeProfilePhotoPrompt(Request $request): RedirectResponse
    {
        $request->validateWithBag('photoPrompt', [
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ], [
            'profile_photo.required' => 'Selecione uma imagem para enviar.',
            'profile_photo.image' => 'O arquivo deve ser uma imagem.',
            'profile_photo.mimes' => 'Use JPG, PNG, GIF ou WEBP.',
            'profile_photo.max' => 'A imagem deve ter no máximo 5 MB.',
        ]);

        $user = $request->user();
        $user->profile_photo_path = $this->storeProfilePhoto($request, $user);
        $user->save();

        return Redirect::back()->with('status', 'profile-photo-updated');
    }

    public function skipProfilePhotoPrompt(Request $request): RedirectResponse
    {
        return Redirect::back();
    }

    public function completeDemographics(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identidade_genero' => ['required', 'string'],
            'identidade_genero_outro' => ['nullable', 'string', 'max:255', 'required_if:identidade_genero,Outro'],
            'raca_cor' => ['required', 'string'],
            'comunidade_tradicional' => ['required', 'string'],
            'comunidade_tradicional_outro' => ['nullable', 'string', 'max:255', 'required_if:comunidade_tradicional,Outro'],
            'faixa_etaria' => ['required', 'string'],
            'pcd' => ['required', 'string'],
            'orientacao_sexual' => ['required', 'string'],
            'orientacao_sexual_outra' => ['nullable', 'string', 'max:255', 'required_if:orientacao_sexual,Outra'],
        ], [
            'identidade_genero.required' => 'Identidade de gênero é obrigatória.',
            'raca_cor.required' => 'Raça/Cor é obrigatória.',
            'comunidade_tradicional.required' => 'Pertencimento a comunidade é obrigatório.',
            'faixa_etaria.required' => 'Faixa etária é obrigatória.',
            'pcd.required' => 'Campo PcD é obrigatório.',
            'orientacao_sexual.required' => 'Orientação sexual é obrigatória.',
            'identidade_genero_outro.required_if' => 'Especifique sua identidade de gênero.',
            'comunidade_tradicional_outro.required_if' => 'Especifique a comunidade tradicional.',
            'orientacao_sexual_outra.required_if' => 'Especifique sua orientação sexual.',
        ]);

        $request->user()->update($data);

        return Redirect::back()->with('status', 'demograficos-salvos');
    }

    public function certificados(Request $request): View
    {
        $user = $request->user();
        $participanteId = $user->participante?->id;
        $certificados = Certificado::with(['modelo'])
            ->where('participante_id', $participanteId)
            ->orderByDesc('created_at')
            ->get();

        return view('profile.certificados', compact('certificados'));
    }

    public function presencas(Request $request)
    {
        $user = auth()->user();
        $participante = Participante::where('user_id', $user->id)->first();
        if (! $participante) {
            return view('profile.presencas', ['atividades' => collect(), 'eventos' => collect()]);
        }

        $eventoId = $request->input('evento_id');
        $dataDe = $request->input('data_de');
        $dataAte = $request->input('data_ate');
        $busca = $request->input('busca');

        $inscricoes = Inscricao::where('participante_id', $participante->id)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('atividade_id');

        $atividadeIds = $inscricoes->keys();

        $eventos = Inscricao::where('participante_id', $participante->id)
            ->whereNull('deleted_at')
            ->with('evento')
            ->get()
            ->pluck('evento')
            ->unique('id')
            ->filter()
            ->values();

        $atividadesQuery = Atividade::query()
            ->whereIn('id', $atividadeIds)
            ->with([
                'evento',
                'presencas' => fn ($q) => $q->whereIn('inscricao_id', $inscricoes->pluck('id')),
            ]);

        $atividadesQuery->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId));
        $atividadesQuery->when($dataDe, fn ($q) => $q->where('dia', '>=', $dataDe));
        $atividadesQuery->when($dataAte, fn ($q) => $q->where('dia', '<=', $dataAte));
        $atividadesQuery->when($busca, function ($q) use ($busca) {
            $q->where('descricao', 'ilike', "%$busca%")
                ->orWhereHas('evento', fn ($qe) => $qe->where('nome', 'ilike', "%$busca%"));
        });

        $atividades = $atividadesQuery
            ->orderBy('dia')
            ->orderBy('hora_inicio')
            ->get();

        $dados = $atividades->map(function ($atividade) use ($inscricoes) {
            $inscricao = $inscricoes->get($atividade->id);
            $presente = $atividade->presencas
                ->where('inscricao_id', optional($inscricao)->id)
                ->isNotEmpty();
            $status = 'Ausente';

            if ($presente) {
                $status = ($inscricao?->ouvinte ?? false) ? 'Ouvinte' : 'Presente';
            }

            return [
                'data' => $atividade->dia,
                'hora' => $atividade->hora_inicio,
                'momento' => $atividade->descricao,
                'evento' => $atividade->evento->nome ?? '',
                'status' => $status,
            ];
        });

        return view('profile.presencas', [
            'atividades' => $dados,
            'eventos' => $eventos,
            'filtros' => [
                'evento_id' => $eventoId,
                'data_de' => $dataDe,
                'data_ate' => $dataAte,
                'busca' => $busca,
            ],
        ]);
    }

    /**
     * Update the user's profile information + participante.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validated();

        $oldEmail = $user->email;
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($oldEmail !== $data['email']) {
            $user->email_verified_at = null;
        }

        if (! empty($data['remove_profile_photo'])) {
            $this->deleteProfilePhoto($user);
            $user->profile_photo_path = null;
        } elseif ($request->hasFile('profile_photo')) {
            $user->profile_photo_path = $this->storeProfilePhoto($request, $user);
        }

        $user->save();

        $participanteData = [
            'cpf' => $data['cpf'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'municipio_id' => $data['municipio_id'] ?? null,
            'escola_unidade' => $data['escola_unidade'] ?? null,
            'tipo_organizacao' => $data['tipo_organizacao'] ?? null,
            'tag' => $data['tag'] ?? null,
            'sexo' => $data['sexo'] ?? 'Não preenchido',
            'data_nascimento' => $data['data_nascimento'] ?? null,
            'autorizacao_imagem' => $data['autorizacao_imagem'] ?? false,
            // 'data_entrada'   => $data['data_entrada']   ?? null, // já 'Y-m-d' de <input type="date">
        ];

        $user->participante()->updateOrCreate(
            ['user_id' => $user->id],
            $participanteData
        );

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    private function storeProfilePhoto(Request $request, User $user): string
    {
        $file = $request->file('profile_photo');
        $directory = "users/{$user->id}/perfil";
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = "perfil.{$extension}";

        $this->deleteProfilePhoto($user);

        return $file->storeAs($directory, $filename, 'public');
    }

    private function deleteProfilePhoto(User $user): void
    {
        if (! $user->profile_photo_path) {
            return;
        }

        Storage::disk('public')->delete($user->profile_photo_path);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
