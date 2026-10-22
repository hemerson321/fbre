<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'checkin_auto' => 'boolean',
            'password' => 'hashed',
            'super_admin' => 'boolean',
            'admin' => 'boolean',
            'perm_cadastra' => 'boolean',
            'perm_baixa' => 'boolean',
            'perm_caixa' => 'boolean',
            'perm_filtro' => 'boolean',
            'perm_relatorio' => 'boolean',
            'perm_imprimir' => 'boolean',
            'ativo' => 'boolean',
            'loc_permitidas' => 'array',
            'setores_permitidos' => 'array',
            'transportes_permitidos' => 'array',
            'perm_retroativo' => 'boolean',
            'perm_parcial' => 'boolean',
            'caixa_todos' => 'boolean',
            'relatorio_todos' => 'boolean',
            'perm_zerar' => 'boolean',
            'perm_passagem' => 'boolean',
            'perm_frete' => 'boolean',
            'perm_camarote' => 'boolean',
            'perm_lanchonete' => 'boolean',
            'perm_wifi' => 'boolean',
            'perm_avulso' => 'boolean',
            'perm_recibo' => 'boolean',
            'perm_financeiro' => 'boolean',
            'mod_perms' => 'array',
        ];
    }

    public const PAPEIS = [
        'operador_barco' => 'Operador do Barco',
        'agencia_externa' => 'Agência Externa',
        'gerente' => 'Gerente',
    ];

    protected static function booted(): void
    {
        // todo operador precisa de um prefixo único (código do número da encomenda)
        static::created(function (self $u) {
            if (empty($u->prefixo)) {
                $u->prefixo = 'OP' . $u->id;
                $u->saveQuietly();
            }
        });
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }

    // ---- escopo do operador no cadastro (vazio = tudo liberado) ----
    public function cidadesPermitidas(): array    { return array_values($this->loc_permitidas ?? []); }
    public function setoresPermitidos(): array    { return array_values($this->setores_permitidos ?? []); }
    public function transportesPermitidos(): array { return array_map('intval', $this->transportes_permitidos ?? []); }
    public function podeRetroativo(): bool        { return $this->admin || $this->gerente() || $this->perm_retroativo; }
    public function podeParcial(): bool           { return $this->admin || $this->gerente() || (bool) $this->perm_parcial; }
    public function gerente(): bool               { return $this->papel === 'gerente'; }
    public function caixaTodos(): bool            { return $this->admin || $this->gerente() || $this->caixa_todos || $this->financeiro(); }
    public function relatorioTodos(): bool        { return $this->admin || $this->gerente() || $this->relatorio_todos || $this->financeiro(); }
    public function papelNome(): string           { return self::PAPEIS[$this->papel] ?? '—'; }

    public function podeCadastrar(): bool { return $this->permite('encomenda', 'cadastra'); }
    public function podeBaixar(): bool    { return $this->permite('encomenda', 'baixa'); }
    public function financeiro(): bool    { return (bool) $this->perm_financeiro; }
    public function podeVerCaixa(): bool  { return $this->admin || $this->perm_caixa || $this->financeiro(); }
    public function podeFiltrar(): bool   { return $this->admin || $this->perm_filtro; }
    public function podeRelatorio(): bool { return $this->admin || $this->perm_relatorio || $this->financeiro(); }
    public function podeImprimir(): bool  { return $this->permite('encomenda', 'imprimir'); }
    public function isAdmin(): bool       { return $this->admin; }
    public function isSuper(): bool       { return $this->super_admin; }
    public function podeZerar(): bool     { return $this->super_admin || (bool) $this->perm_zerar; }

    /** Módulos de cadastro do sistema. */
    public const MODULOS = [
        'encomenda'  => 'Encomendas',
        'passagem'   => 'Passagem',
        'frete'      => 'Frete',
        'camarote'   => 'Camarote',
        'lanchonete' => 'Lanchonete',
        'wifi'       => 'WiFi',
        'avulso'     => 'Avulso (entrada/saída)',
        'recibo'     => 'Recibo',
    ];

    /** Ações (possibilidades) de CADA módulo — aparecem na tela de permissões. */
    public const ACOES_MODULO = [
        'encomenda'  => ['cadastra' => 'Cadastrar', 'ver' => 'Ver lista / filtro', 'baixa' => 'Dar baixa', 'imprimir' => 'Imprimir', 'precadastro' => 'Pré-cadastros'],
        'passagem'   => ['cadastra' => 'Vender passagem', 'ver' => 'Ver lista / embarque', 'baixa' => 'Dar baixa / embarque', 'imprimir' => 'Imprimir'],
        'frete'      => ['cadastra' => 'Cadastrar frete', 'ver' => 'Ver lista', 'baixa' => 'Dar baixa / entrega', 'imprimir' => 'Imprimir'],
        'camarote'   => ['cadastra' => 'Reservar', 'ver' => 'Ver lista', 'baixa' => 'Dar baixa', 'imprimir' => 'Imprimir'],
        'lanchonete' => ['vender' => 'Vender', 'ver' => 'Ver vendas', 'imprimir' => 'Imprimir', 'estoque' => 'Controle de estoque'],
        'wifi'       => ['vender' => 'Vender WiFi', 'ver' => 'Ver vendas'],
        'avulso'     => ['lancar' => 'Lançar entrada/saída no caixa'],
        'recibo'     => ['emitir' => 'Emitir recibo', 'ver' => 'Ver recibos', 'imprimir' => 'Imprimir'],
    ];

    /** Ação principal (a de "criar/vender") de cada módulo. */
    public static function acaoPrincipal(string $mod): string
    {
        return match ($mod) {
            'avulso' => 'lancar',
            'lanchonete', 'wifi' => 'vender',
            'recibo' => 'emitir',
            default => 'cadastra',
        };
    }

    /**
     * Permissão por módulo e ação. Admin/gerente podem tudo.
     * Sem config em mod_perms, cai no fallback das colunas antigas (compatível).
     */
    public function permite(string $mod, string $acao = 'cadastra'): bool
    {
        if ($this->admin || $this->isSuper() || $this->gerente()) return true;
        $mp = $this->mod_perms[$mod] ?? null;
        if (is_array($mp) && array_key_exists($acao, $mp)) return (bool) $mp[$acao];
        // fallback compatível
        if ($mod === 'encomenda') {
            return match ($acao) {
                'cadastra', 'precadastro' => (bool) $this->perm_cadastra,
                'baixa'    => (bool) $this->perm_baixa,
                'imprimir' => (bool) $this->perm_imprimir,
                'ver'      => (bool) $this->perm_filtro,
                default    => false,
            };
        }
        // outros módulos: só a ação principal cai na coluna legada perm_<mod>
        return $acao === self::acaoPrincipal($mod) ? (bool) ($this->{'perm_' . $mod} ?? false) : false;
    }

    /** Pode fazer a ação principal do módulo (criar/vender). */
    public function podeModulo(string $mod): bool
    {
        return $this->permite($mod, self::acaoPrincipal($mod));
    }

    /** Tem QUALQUER acesso ao módulo. Decide se o módulo aparece pra ele. */
    public function temModulo(string $mod): bool
    {
        foreach (array_keys(self::ACOES_MODULO[$mod] ?? []) as $acao) {
            if ($this->permite($mod, $acao)) return true;
        }
        return false;
    }

    /** Lista de módulos que este usuário pode acessar. */
    public function modulosLiberados(): array
    {
        return array_values(array_filter(array_keys(self::MODULOS), fn ($m) => $this->podeModulo($m)));
    }

    /** Próxima sequência do operador, de forma atômica (fallback quando o aparelho não manda o número). */
    public function proximoSeq(): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $u = static::lockForUpdate()->find($this->id);
            $n = (int) ($u->proximo_seq ?: 1);
            $u->update(['proximo_seq' => $n + 1]);
            return $n;
        });
    }
}
