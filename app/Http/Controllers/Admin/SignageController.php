<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SignageMidia;
use App\Models\SignageTela;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Digital Signage (mídia indoor): telas/pontos e as propagandas e avisos que rodam em cada uma. Tudo numa página só. */
class SignageController extends Controller
{
    public function index()
    {
        $telas = SignageTela::with('midias')->orderBy('nome')->get();
        return view('admin.signage', [
            'telas' => $telas,
            'layouts' => SignageMidia::LAYOUTS,
            'empresa' => (string) (Tenant::empresa()?->nome ?? ''),
        ]);
    }

    public function telaSalvar(Request $request, ?SignageTela $tela = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'local' => 'nullable|string|max:200',
            'orientacao' => 'required|in:horizontal,vertical',
        ]);
        $data['ativo'] = $request->boolean('ativo', true);
        if ($tela && $tela->exists) {
            $tela->update($data);
            return back()->with('ok', 'Tela "'.$tela->nome.'" atualizada.');
        }
        do { $token = Str::lower(Str::random(20)); } while (SignageTela::withoutGlobalScopes()->where('token', $token)->exists());
        $nova = SignageTela::create($data + ['empresa_id' => Tenant::id(), 'token' => $token]);
        return back()->with('ok', 'Tela "'.$nova->nome.'" criada. Copie o link e abra na TV Box.');
    }

    public function telaExcluir(SignageTela $tela)
    {
        foreach ($tela->midias as $m) $this->apagarArquivos($m);
        $tela->midias()->delete();
        $nome = $tela->nome;
        $tela->delete();
        return back()->with('ok', 'Tela "'.$nome.'" excluída.');
    }

    public function midiaSalvar(Request $request, SignageTela $tela, ?SignageMidia $midia = null)
    {
        $editando = $midia && $midia->exists;
        if ($editando) abort_unless((int) $midia->tela_id === (int) $tela->id, 404);

        $data = $request->validate([
            'tipo' => 'required|in:midia,texto',
            'titulo' => 'nullable|string|max:120',
            'texto' => 'nullable|string|max:2000',
            'layout' => 'nullable|integer|min:1|max:10',
            'arquivo' => 'nullable|file|max:204800|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov,m4v',
            'arquivo_url' => 'nullable|url|max:255',
            'audio' => 'nullable|file|max:30720|mimes:mp3,ogg,wav,m4a,aac',
            'duracao' => 'nullable|integer|min:0|max:3600',
            'modo' => 'required|in:sequencia,intervalo,horario',
            'intervalo_min' => 'nullable|integer|min:1|max:1440',
            'horarios' => 'nullable|string|max:500',
            'dias' => 'nullable|array',
            'dias.*' => 'integer|min:0|max:6',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date',
            'ordem' => 'nullable|integer|min:0|max:9999',
        ]);

        $m = $editando ? $midia : new SignageMidia(['empresa_id' => Tenant::id(), 'tela_id' => $tela->id]);

        if ($data['tipo'] === 'texto') {
            if (trim((string) ($data['texto'] ?? '')) === '') return back()->with('erro', 'Escreva o texto do aviso.')->withInput();
            if ($editando && $m->tipo !== 'texto') { $this->apagar($m->arquivo); $m->arquivo = null; }
            $m->tipo = 'texto';
            $m->texto = trim((string) $data['texto']);
            $m->layout = (int) ($data['layout'] ?? 1);
        } else {
            if ($request->hasFile('arquivo')) {
                $f = $request->file('arquivo');
                $ehVideo = str_starts_with((string) $f->getMimeType(), 'video/') || in_array(strtolower($f->getClientOriginalExtension()), ['mp4', 'webm', 'mov', 'm4v'], true);
                $this->apagar($m->arquivo);
                $m->arquivo = 'storage/'.$f->store('signage/'.Tenant::id(), 'public');
                $m->tipo = $ehVideo ? 'video' : 'imagem';
            } elseif (! empty($data['arquivo_url'])) {
                $this->apagar($m->arquivo);
                $m->arquivo = $data['arquivo_url'];
                $m->tipo = preg_match('/\.(mp4|webm|mov|m4v)(\?.*)?$/i', $data['arquivo_url']) ? 'video' : 'imagem';
            } elseif (! $editando || ! in_array($m->tipo, ['imagem', 'video'], true) || ! $m->arquivo) {
                return back()->with('erro', 'Envie a foto ou o vídeo (ou cole o link do arquivo).')->withInput();
            }
            $m->texto = null;
        }

        if ($request->hasFile('audio')) {
            $this->apagar($m->audio);
            $m->audio = 'storage/'.$request->file('audio')->store('signage/'.Tenant::id(), 'public');
        } elseif ($request->boolean('remover_audio')) {
            $this->apagar($m->audio);
            $m->audio = null;
        }

        $horarios = [];
        foreach (preg_split('/[\s,;]+/', (string) ($data['horarios'] ?? '')) as $h) {
            if (preg_match('/^(\d{1,2})[:h](\d{2})$/i', trim($h), $mm) && (int) $mm[1] < 24 && (int) $mm[2] < 60) {
                $horarios[] = sprintf('%02d:%02d', $mm[1], $mm[2]);
            }
        }
        $horarios = array_values(array_unique($horarios));
        sort($horarios);
        if ($data['modo'] === 'horario' && ! $horarios) return back()->with('erro', 'Informe pelo menos um horário (ex.: 08:00, 12:30).')->withInput();
        if ($data['modo'] === 'intervalo' && empty($data['intervalo_min'])) return back()->with('erro', 'Informe de quantos em quantos minutos a mídia deve aparecer.')->withInput();

        $padraoDur = $m->tipo === 'video' ? 0 : 10;
        $m->fill([
            'titulo' => trim((string) ($data['titulo'] ?? '')) ?: null,
            'som' => $request->boolean('som'),
            'duracao' => ($data['duracao'] ?? null) === null ? $padraoDur : (int) $data['duracao'],
            'modo' => $data['modo'],
            'intervalo_min' => $data['modo'] === 'intervalo' ? (int) $data['intervalo_min'] : null,
            'horarios' => $data['modo'] === 'horario' ? $horarios : null,
            'dias' => array_values(array_unique(array_map('intval', $data['dias'] ?? []))) ?: null,
            'data_inicio' => $data['data_inicio'] ?? null,
            'data_fim' => $data['data_fim'] ?? null,
            'ordem' => (int) ($data['ordem'] ?? ($editando ? $m->ordem : ((int) $tela->midias()->max('ordem') + 1))),
            'ativo' => $request->boolean('ativo', true),
        ]);
        $m->save();

        return back()->with('ok', $editando ? 'Mídia atualizada.' : 'Mídia adicionada à tela "'.$tela->nome.'".');
    }

    public function midiaExcluir(SignageMidia $midia)
    {
        $this->apagarArquivos($midia);
        $midia->delete();
        return back()->with('ok', 'Mídia excluída.');
    }

    public function midiaAtivar(SignageMidia $midia)
    {
        $midia->update(['ativo' => ! $midia->ativo]);
        return back()->with('ok', $midia->ativo ? 'Mídia ativada.' : 'Mídia pausada.');
    }

    /** Reordena a programação da tela (arrastar e soltar). Recebe os IDs na nova ordem. */
    public function midiaOrdenar(Request $request, SignageTela $tela)
    {
        $ids = (array) $request->input('ids', []);
        $ordem = 1;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            // midias() já é escopado pela tela e pela empresa (global scope) — só atualiza o que é desta tela.
            $tela->midias()->where('id', $id)->update(['ordem' => $ordem++]);
        }

        return response()->json(['ok' => true, 'total' => $ordem - 1]);
    }

    protected function apagarArquivos(SignageMidia $m): void
    {
        $this->apagar($m->arquivo);
        $this->apagar($m->audio);
    }

    protected function apagar(?string $caminho): void
    {
        if ($caminho && str_starts_with($caminho, 'storage/signage/')) {
            try { Storage::disk('public')->delete(substr($caminho, 8)); } catch (\Throwable $e) {}
        }
    }
}
