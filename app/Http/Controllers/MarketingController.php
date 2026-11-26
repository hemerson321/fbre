<?php

namespace App\Http\Controllers;

use App\Models\MarketingAudio;
use App\Models\MarketingDisparo;
use App\Models\MarketingProgramacao;
use App\Models\MarketingTerminal;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Áudio indoor (áudio terminal): terminais, áudios (fala/arquivo), programação por horário/semana e "tocar agora". */
class MarketingController extends Controller
{
    public function index()
    {
        $terminais = MarketingTerminal::orderBy('nome')->get();
        $audios = MarketingAudio::orderBy('nome')->get();
        $programacoes = MarketingProgramacao::with(['audio', 'terminal'])->orderBy('hora')->get();
        $disparos = MarketingDisparo::with(['audio', 'terminal'])->orderByDesc('id')->limit(15)->get();

        $emp = Tenant::empresa();
        $tts = [
            'provider' => (string) ($emp?->config('tts_provider', 'openai')),
            'openai_voz' => (string) ($emp?->config('tts_openai_voz', 'nova')),
            'estilo' => (string) ($emp?->config('tts_estilo', '')),
            'google_tem_chave' => filled($emp?->config('tts_google_key')),
            'google_voz' => (string) ($emp?->config('tts_google_voz', 'pt-BR-Chirp3-HD-Aoede')),
            'google_ritmo' => (float) ($emp?->config('tts_google_ritmo', 1.0)),
        ];

        return view('marketing.index', compact('terminais', 'audios', 'programacoes', 'disparos', 'tts'));
    }

    /** Salva as integrações de voz (provedor OpenAI/Google, voz, estilo, chave do Google). */
    public function ttsSalvar(Request $request)
    {
        $data = $request->validate([
            'tts_provider' => 'required|in:openai,google',
            'tts_openai_voz' => 'nullable|string|max:40',
            'tts_estilo' => 'nullable|string|max:600',
            'tts_google_key' => 'nullable|string|max:200',
            'tts_google_voz' => 'nullable|string|max:60',
            'tts_google_ritmo' => 'nullable|numeric|min:0.5|max:1.5',
        ]);
        $e = Tenant::empresa();
        $cfg = $e->config ?? [];
        $cfg['tts_provider'] = $data['tts_provider'];
        $cfg['tts_openai_voz'] = $data['tts_openai_voz'] ?: 'nova';
        $cfg['tts_estilo'] = trim((string) ($data['tts_estilo'] ?? ''));
        $cfg['tts_google_voz'] = $data['tts_google_voz'] ?: 'pt-BR-Chirp3-HD-Aoede';
        $cfg['tts_google_ritmo'] = (float) ($data['tts_google_ritmo'] ?? 1.0);
        if (! empty($data['tts_google_key'])) $cfg['tts_google_key'] = trim((string) $data['tts_google_key']); // em branco mantém a atual
        $e->forceFill(['config' => $cfg])->save();

        return back()->with('ok', 'Integrações de voz salvas.');
    }

    /** Testa a voz configurada nas Integrações: gera uma frase curta e devolve o áudio (ou o erro exato). */
    public function ttsTestar(Request $request)
    {
        $emp = Tenant::empresa();
        $svc = app(\App\Services\SinteseVoz::class);
        $prov = (string) ($emp?->config('tts_provider', 'openai'));
        $texto = 'Atenção, senhores passageiros. Teste de voz do terminal.';
        if ($prov === 'google') {
            $b64 = $svc->sintetizarGoogle($texto, (string) $emp?->config('tts_google_voz', 'pt-BR-Chirp3-HD-Aoede'), (string) $emp?->config('tts_google_key'), (float) $emp?->config('tts_google_ritmo', 1.0));
        } else {
            $b64 = $svc->sintetizar($texto, (string) ($emp?->config('tts_openai_voz') ?: 'nova'), null, (string) ($emp?->config('tts_estilo') ?: ''));
        }
        if ($b64 !== '') {
            return response()->json(['ok' => true, 'provedor' => $prov, 'audio' => 'data:audio/mp3;base64,'.$b64]);
        }

        return response()->json(['ok' => false, 'provedor' => $prov, 'msg' => $svc->ultimoErro ?: 'Não foi possível gerar a voz.']);
    }

    // -------- Terminais --------
    public function terminalSalvar(Request $request, ?MarketingTerminal $terminal = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'local' => 'nullable|string|max:200',
        ]);
        $data['ativo'] = $request->boolean('ativo', true);
        if ($terminal && $terminal->exists) {
            $terminal->update($data);
            return back()->with('ok', 'Terminal "'.$terminal->nome.'" atualizado.');
        }
        do { $token = Str::lower(Str::random(20)); } while (MarketingTerminal::withoutGlobalScopes()->where('token', $token)->exists());
        $novo = MarketingTerminal::create($data + ['empresa_id' => Tenant::id(), 'token' => $token]);

        return back()->with('ok', 'Terminal "'.$novo->nome.'" criado. Abra o link num tablet/PC com caixa de som.');
    }

    public function terminalExcluir(MarketingTerminal $terminal)
    {
        $nome = $terminal->nome;
        MarketingProgramacao::where('terminal_id', $terminal->id)->delete();
        $terminal->delete();

        return back()->with('ok', 'Terminal "'.$nome.'" excluído.');
    }

    // -------- Áudios --------
    public function audioSalvar(Request $request, ?MarketingAudio $audio = null)
    {
        $editando = $audio && $audio->exists;
        $data = $request->validate([
            'nome' => 'required|string|max:140',
            'tipo' => 'required|in:tts,arquivo',
            'texto' => 'nullable|string|max:2000',
            'voz' => 'nullable|string|max:60',
            'voz_google' => 'nullable|string|max:60',
            'estilo' => 'nullable|string|max:600',
            'provedor' => 'nullable|in:openai,google',
            'arquivo' => 'nullable|file|max:30720|mimes:mp3,ogg,wav,m4a,aac',
            'arquivo_url' => 'nullable|url|max:255',
        ]);

        $a = $editando ? $audio : new MarketingAudio(['empresa_id' => Tenant::id()]);
        $a->nome = trim((string) $data['nome']);
        $a->tipo = $data['tipo'];
        $a->ativo = $request->boolean('ativo', true);
        $a->provedor = $data['provedor'] ?? 'google';
        $a->voz = $a->provedor === 'google' ? (trim((string) ($data['voz_google'] ?? '')) ?: null) : (trim((string) ($data['voz'] ?? '')) ?: null);
        $a->estilo = trim((string) ($data['estilo'] ?? '')) ?: null;

        if ($data['tipo'] === 'tts') {
            if (trim((string) ($data['texto'] ?? '')) === '') return back()->with('erro', 'Escreva o texto que vai ser falado.')->withInput();
            $a->texto = trim((string) $data['texto']);
            $antigo = $a->arquivo;
            $a->arquivo = null;
            $a->save();
            // Gera o MP3 com voz de verdade (OpenAI TTS), reaproveitando o provedor OpenAI ativo da empresa.
            $gerado = false; $motivoVoz = '';
            try {
                $emp = Tenant::empresa();
                $svc = app(\App\Services\SinteseVoz::class);
                $estilo = (string) ($a->estilo ?: ($emp?->config('tts_estilo') ?: 'Narre em português do Brasil com voz feminina natural, calma, clara e acolhedora, como uma recepcionista de terminal de embarque anunciando aos passageiros. Boa dicção, ritmo tranquilo e sotaque brasileiro neutro. Soe humana, nunca robótica.'));
                $prov = $a->provedor ?: (string) ($emp?->config('tts_provider', 'openai'));
                if ($prov === 'google') {
                    $b64 = $svc->sintetizarGoogle($a->texto, (string) ($a->voz ?: $emp?->config('tts_google_voz', 'pt-BR-Neural2-A')), (string) $emp?->config('tts_google_key'), (float) $emp?->config('tts_google_ritmo', 1.0));
                } else {
                    $b64 = $svc->sintetizar($a->texto, (string) ($a->voz ?: ($emp?->config('tts_openai_voz') ?: 'nova')), null, $estilo);
                }
                if ($b64 !== '') {
                    $path = 'marketing/'.Tenant::id().'/tts_'.$a->id.'_'.time().'.mp3';
                    Storage::disk('public')->put($path, base64_decode($b64));
                    $a->arquivo = 'storage/'.$path;
                    $a->save();
                    $gerado = true;
                } else {
                    $motivoVoz = $svc->ultimoErro;
                }
            } catch (\Throwable $e) {
                $motivoVoz = $e->getMessage();
            }
            $this->apagar($antigo);

            return back()->with('ok', ($editando ? 'Áudio atualizado. ' : 'Áudio "'.$a->nome.'" criado. ').($gerado ? '🔊 Voz gerada.' : '⚠️ Não gerei a voz: '.($motivoVoz ?: 'verifique as Integrações').' — por ora fala pela voz do navegador.'));
        } else {
            $a->texto = null;
            if ($request->hasFile('arquivo')) {
                $this->apagar($a->arquivo);
                $a->arquivo = 'storage/'.$request->file('arquivo')->store('marketing/'.Tenant::id(), 'public');
            } elseif (! empty($data['arquivo_url'])) {
                $this->apagar($a->arquivo);
                $a->arquivo = $data['arquivo_url'];
            } elseif (! $editando || ! $a->arquivo) {
                return back()->with('erro', 'Envie o arquivo de áudio (ou cole o link).')->withInput();
            }
        }
        $a->save();

        return back()->with('ok', $editando ? 'Áudio atualizado.' : 'Áudio "'.$a->nome.'" criado.');
    }

    public function audioExcluir(MarketingAudio $audio)
    {
        $nome = $audio->nome;
        $this->apagar($audio->arquivo);
        MarketingProgramacao::where('audio_id', $audio->id)->delete();
        $audio->delete();

        return back()->with('ok', 'Áudio "'.$nome.'" excluído.');
    }

    public function tocarAgora(Request $request, MarketingAudio $audio)
    {
        $terminalId = $request->integer('terminal_id') ?: null;
        if ($terminalId && ! MarketingTerminal::whereKey($terminalId)->exists()) $terminalId = null;

        MarketingDisparo::create([
            'empresa_id' => Tenant::id(),
            'audio_id' => $audio->id,
            'terminal_id' => $terminalId,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('ok', 'Tocando "'.$audio->nome.'" '.($terminalId ? 'no terminal selecionado' : 'em todos os terminais').'. Pode levar alguns segundos.');
    }

    // -------- Programação --------
    public function programacaoSalvar(Request $request, ?MarketingProgramacao $programacao = null)
    {
        $data = $request->validate([
            'audio_id' => 'required|integer',
            'terminal_id' => 'nullable|integer',
            'hora' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hora_fim' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'repetir_min' => 'nullable|integer|min:1|max:1440',
            'vezes' => 'nullable|integer|min:1|max:50',
            'intervalo_seg' => 'nullable|integer|min:0|max:600',
            'dias' => 'nullable|array',
            'dias.*' => 'integer|min:0|max:6',
        ]);
        abort_unless(MarketingAudio::whereKey($data['audio_id'])->exists(), 404);
        $terminalId = $data['terminal_id'] ?? null;
        if ($terminalId && ! MarketingTerminal::whereKey($terminalId)->exists()) $terminalId = null;

        // Janela (hora_fim + repetir a cada X min): só vale se os dois estiverem definidos e o fim for depois do início.
        $horaFim = $data['hora_fim'] ?? null;
        $repetir = ($horaFim && ! empty($data['repetir_min'])) ? max(1, (int) $data['repetir_min']) : null;
        if (! $repetir || ($horaFim && $horaFim <= $data['hora'])) { $horaFim = null; $repetir = null; }

        $payload = [
            'empresa_id' => Tenant::id(),
            'audio_id' => (int) $data['audio_id'],
            'terminal_id' => $terminalId,
            'hora' => $data['hora'],
            'hora_fim' => $horaFim,
            'repetir_min' => $repetir,
            'vezes' => max(1, min(50, (int) ($data['vezes'] ?? 1))),
            'intervalo_seg' => max(0, min(600, (int) ($data['intervalo_seg'] ?? 5))),
            'dias' => array_values(array_unique(array_map('intval', $data['dias'] ?? []))) ?: null,
            'ativo' => $request->boolean('ativo', true),
        ];
        if ($programacao && $programacao->exists) {
            $programacao->update($payload);
            return back()->with('ok', 'Programação atualizada.');
        }
        MarketingProgramacao::create($payload);

        return back()->with('ok', 'Programação criada.');
    }

    public function programacaoExcluir(MarketingProgramacao $programacao)
    {
        $programacao->delete();

        return back()->with('ok', 'Programação removida.');
    }

    protected function apagar(?string $caminho): void
    {
        if ($caminho && str_starts_with($caminho, 'storage/marketing/')) {
            try { Storage::disk('public')->delete(substr($caminho, 8)); } catch (\Throwable $e) {}
        }
    }
}
