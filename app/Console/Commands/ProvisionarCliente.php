<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Setor;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Onboarding de um cliente novo num passo só (container single-tenant).
 * Cria a empresa + admin + dados base. Idempotente: se já existir, não duplica.
 *
 * Ex.: php artisan fbre:provisionar ajato "Ajato Transportes LTDA" \
 *        --fantasia="Ajato Passagens" --dominio=ajatopassagem.com.br --senha=SenhaForte123
 */
class ProvisionarCliente extends Command
{
    protected $signature = 'fbre:provisionar
        {slug : identificador em minúsculas (ex.: ajato)}
        {nome : razão social}
        {--fantasia= : nome fantasia}
        {--dominio= : domínio próprio (ex.: ajatopassagem.com.br)}
        {--login=admin : login do administrador}
        {--senha= : senha do admin (se vazio, gera uma)}';

    protected $description = 'Cria empresa + admin + dados base de um cliente novo (onboarding single-tenant).';

    public function handle(): int
    {
        $slug = strtolower(trim((string) $this->argument('slug')));
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->error('Slug inválido. Use só letras minúsculas, números e hífen.');
            return self::FAILURE;
        }

        $empresa = Empresa::where('slug', $slug)->first();

        if ($empresa) {
            $this->warn("Empresa '{$slug}' já existe (id {$empresa->id}). Não vou duplicar.");
        } else {
            $dominio = $this->normalizarDominio($this->option('dominio'));
            if ($dominio && Empresa::where('dominio', $dominio)->exists()) {
                $this->error("O domínio '{$dominio}' já está em uso por outra empresa.");
                return self::FAILURE;
            }

            $empresa = Empresa::create([
                'nome' => (string) $this->argument('nome'),
                'nome_fantasia' => $this->option('fantasia') ?: null,
                'slug' => $slug,
                'dominio' => $dominio,
                'validade' => now()->addYear(),
                'recursos' => ['print_agent'],
            ]);
            $this->info("Empresa criada: {$empresa->nomeExibicao()} (id {$empresa->id}, slug {$slug}).");

            // dados base da empresa (mesmo do cadastro pelo painel)
            Tenant::set($empresa->id);
            foreach (['Escritório', 'Porto'] as $s) Setor::create(['nome' => $s]);
            foreach (['Caixa pequena', 'Caixa média', 'Caixa grande'] as $p) Produto::create(['nome' => $p, 'valor_padrao' => 0]);
            Tenant::clear();
        }

        $login = strtolower(trim((string) ($this->option('login') ?: 'admin')));
        $user = User::where('empresa_id', $empresa->id)->where('login', $login)->first();

        if ($user) {
            $this->warn("Usuário '{$login}' já existe nessa empresa. Não vou recriar.");
        } else {
            $senha = $this->option('senha') ?: Str::password(10, true, true, false);
            User::create([
                'empresa_id' => $empresa->id,
                'name' => 'Administrador',
                'login' => $login,
                'password' => $senha,          // cast 'hashed' no model cuida do hash
                'admin' => true,
                'perm_cadastra' => true, 'perm_baixa' => true, 'perm_caixa' => true,
            ]);
            $this->info("Admin criado: login '{$login}' / senha '{$senha}'");
            $this->line('>>> ANOTE a senha agora; não será mostrada de novo.');
        }

        $this->newLine();
        $this->line('Agora, no .env deste container:');
        $this->line("  FBRE_SINGLE=true");
        $this->line("  FBRE_EMPRESA={$slug}");
        $this->line('e faça o redeploy. Depois mude SEED_ON_BOOT para 0.');

        return self::SUCCESS;
    }

    /** Deixa só o host: tira http(s)://, caminho e www. */
    protected function normalizarDominio(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        $v = preg_replace('#^https?://#i', '', $v);
        $v = preg_replace('#/.*$#', '', $v);
        $v = preg_replace('/^www\./i', '', $v);
        $v = strtolower(trim($v));
        return $v !== '' ? $v : null;
    }
}
