<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponderAutorizacaoImagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('autorizacoes-imagem.registrar')) {
            return $this->user()?->isEngajaUser() && $this->user()->can('autorizacao-imagem.registrar');
        }
        if ($this->routeIs('profile.autorizacoes-imagem.salvar')) {
            return (bool) $this->user()?->isEngajaUser();
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'contexto' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'autorizado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'contexto.required' => 'Reabra o formulário antes de responder.',
            'contexto.regex' => 'Solicitação inválida. Reabra o formulário.',
            'autorizado.required' => 'Escolha se autoriza ou não o uso de imagem.',
            'autorizado.boolean' => 'Selecione uma das opções de autorização.',
        ];
    }
}
