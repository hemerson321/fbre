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

        return view('marketing.index', compact('terminais', 'audios', 'programacoes', 'disparos'));
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
            'arquivo' => 'nullable|file|max:30720|mimes:mp3,ogg,wav,m4a,aac',
            'arquivo_url' => 'nullable|url|max:255',
        ]);

        $a = $editando ? $audio : new MarketingAudio(['empresa_id' => Tenant::id()]);
        $a->nome = trim((string) $data['nome']);
        $a->tipo = $data['tipo'];
        $a->ativo = $request->boolean('ativo', true);

        if ($data['tipo'] === 'tts') {
            if (trim((string) ($data['texto'] ?? '')) === '') return back()->with('erro', 'Escreva o texto que vai ser falado.')->withInput();
            if ($editando) $this->apagar($a->arquivo);
            $a->texto = trim((string) $data['texto']);
            $a->voz = trim((string) ($data['voz'] ?? '')) ?: null;
            $a->arquivo = null;
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
            'dias' => 'nullable|array',
            'dias.*' => 'integer|min:0|max:6',
        ]);
        abort_unless(MarketingAudio::whereKey($data['audio_id'])->exists(), 404);
        $terminalId = $data['terminal_id'] ?? null;
        if ($terminalId && ! MarketingTerminal::whereKey($terminalId)->exists()) $terminalId = null;

        $payload = [
            'empresa_id' => Tenant::id(),
            'audio_id' => (int) $data['audio_id'],
            'terminal_id' => $terminalId,
            'hora' => $data['hora'],
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
