<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Tela de atendimento. A conversa cai na tela pelo NÚMERO de WhatsApp em que chegou.
 * Número que não está em nenhuma tela criada fica na tela "Padrão" (id 0, sempre existe).
 */
class AtendTela extends Model
{
    use PertenceEmpresa;

    protected $table = 'atend_telas';
    protected $guarded = [];
    protected $casts = [
        'conexao_ids' => 'array', 'agente_ids' => 'array', 'user_ids' => 'array',
        'usa_ia' => 'boolean', 'operadores_todos' => 'boolean', 'ativo' => 'boolean',
    ];

    /** A tabela já existe? (antes de rodar a migration tudo se comporta como hoje: só a tela Padrão) */
    public static function pronta(): bool
    {
        static $ok = null;
        return $ok ??= Schema::hasTable('atend_telas');
    }

    /** Telas ativas da empresa (uma consulta por requisição). */
    public static function daEmpresa(?int $empresaId): Collection
    {
        static $cache = [];
        $empresaId = (int) $empresaId;
        if (! $empresaId || ! static::pronta()) return collect();
        return $cache[$empresaId] ??= static::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('ativo', true)->orderBy('nome')->get();
    }

    public function conexoes(): array { return array_values(array_map('intval', (array) ($this->conexao_ids ?: []))); }
    public function agentes(): array { return array_values(array_map('intval', (array) ($this->agente_ids ?: []))); }
    public function usuarios(): array { return array_values(array_map('intval', (array) ($this->user_ids ?: []))); }

    /** Tela (criada) em que a conversa cai. null = tela Padrão. */
    public static function daConversa(Conversa $c): ?self
    {
        $cx = (int) $c->whatsapp_conexao_id;
        if (! $cx) return null;
        foreach (static::daEmpresa($c->empresa_id) as $t) if (in_array($cx, $t->conexoes(), true)) return $t;
        return null;
    }

    /** Agente de IA que atende a conversa, conforme a tela dela. null = ninguém (só operadores). */
    public static function agentePara(Conversa $c): ?Agente
    {
        $tela = static::daConversa($c);
        if (! $tela) return Agente::where('modulo', $c->modulo)->where('ativo', true)->first(); // Padrão: como sempre foi
        if (! $tela->usa_ia) return null;
        $ids = $tela->agentes();
        return $ids
            ? Agente::whereIn('id', $ids)->where('ativo', true)->orderBy('id')->first()
            : Agente::where('modulo', $c->modulo)->where('ativo', true)->first();
    }

    /** Este usuário pode entrar nesta tela? */
    public function permite($u): bool
    {
        if (! $u) return false;
        if ($u->admin || $u->super_admin || $this->operadores_todos) return true;
        return in_array((int) $u->id, $this->usuarios(), true);
    }

    /** Telas que o usuário enxerga: [id => nome]. A Padrão (0) vem primeiro. */
    public static function doUsuario($u): array
    {
        $out = [0 => 'Padrão'];
        foreach (static::daEmpresa(\App\Support\Tenant::id() ?: $u?->empresa_id) as $t) if ($t->permite($u)) $out[(int) $t->id] = (string) $t->nome;
        return $out;
    }

    /** Tela em que o usuário está agora (guardada na sessão). */
    public static function atualId($u): int
    {
        $id = (int) session('atend_tela', 0);
        return array_key_exists($id, static::doUsuario($u)) ? $id : 0;
    }

    public static function escolher($u, int $id): void
    {
        if (array_key_exists($id, static::doUsuario($u))) session(['atend_tela' => $id]);
    }

    /** Restringe uma consulta de conversas às da tela informada. */
    public static function filtrar($q, int $telaId, ?int $empresaId)
    {
        $telas = static::daEmpresa($empresaId);
        if ($telas->isEmpty()) return $q; // nenhuma tela criada: tudo na Padrão
        if ($telaId > 0) {
            $t = $telas->firstWhere('id', $telaId);
            return $q->whereIn('whatsapp_conexao_id', ($t ? $t->conexoes() : []) ?: [0]);
        }
        $usadas = $telas->flatMap(fn ($t) => $t->conexoes())->unique()->values()->all();
        if (! $usadas) return $q;
        return $q->where(fn ($w) => $w->whereNull('whatsapp_conexao_id')->orWhereNotIn('whatsapp_conexao_id', $usadas));
    }
}
