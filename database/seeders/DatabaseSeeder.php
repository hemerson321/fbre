<?php

namespace Database\Seeders;

use App\Models\Embarcacao;
use App\Models\Empresa;
use App\Models\Localidade;
use App\Models\Produto;
use App\Models\Setor;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('fbre.single')) {
            $this->empresaUnica();
            return;
        }

        // ------------------------------------------------------------
        // GERATEC — empresa de testes + super-admin
        // ------------------------------------------------------------
        $geratec = Empresa::firstOrCreate(['slug' => 'geratec'], [
            'nome' => 'GERATEC Soluções',
            'nome_fantasia' => 'GERATEC',
            'cnpj' => '44.079.100/0001-20',
            'telefone' => '',
            'cidade' => 'Manaus',
            'uf' => 'AM',
            'endereco' => 'Rua Cariri, 120 - Alvorada',
            'logo' => 'img/empresas/geratec.png',
            'cor_primaria' => '#0b1a33',
            'cor_secundaria' => '#00a8e8',
            'recursos' => ['print_agent'],
            'ativo' => true,
        ]);

        $this->usuario('hemerson321@gmail.com', [
            'login' => 'hemerson', 'empresa_id' => $geratec->id, 'name' => 'Hemerson',
            'super_admin' => true, 'admin' => true, 'perm_cadastra' => true, 'perm_baixa' => true, 'perm_caixa' => true, 'setor' => 'Escritório',
        ]);

        $this->dadosBase($geratec);

        // ------------------------------------------------------------
        // E OLIVEIRA FERNANDES — primeiro cliente
        // ------------------------------------------------------------
        $oliveira = Empresa::firstOrCreate(['slug' => 'oliveira'], [
            'nome' => 'E Oliveira Fernandes - Transportes Fluviais - ME',
            'nome_fantasia' => 'Ferry Boat Rainha Esther',
            'cnpj' => '05.967.900/0001-26',
            'uf' => 'AM',
            'logo' => 'img/empresas/oliveira.jpg',
            'cor_primaria' => '#013155',
            'cor_secundaria' => '#e0a630',
            'recursos' => ['print_agent', 'viagens'],
            'validade' => now()->addYear(),
            'ativo' => true,
        ]);

        $this->usuario('admin@oliveira.fbre', [
            'login' => 'oliveira', 'empresa_id' => $oliveira->id, 'name' => 'Administrador Oliveira',
            'admin' => true, 'perm_cadastra' => true, 'perm_baixa' => true, 'perm_caixa' => true, 'setor' => 'Escritório',
        ]);

        $this->dadosBase($oliveira);
    }

    /** Instalação dedicada a uma única empresa (config fbre.empresa). */
    protected function empresaUnica(): void
    {
        $slug = config('fbre.empresa');

        $empresa = Empresa::firstOrCreate(['slug' => $slug], match ($slug) {
            'oliveira' => [
                'nome' => 'E Oliveira Fernandes - Transportes Fluviais - ME',
                'nome_fantasia' => 'Ferry Boat Rainha Esther',
                'cnpj' => '05.967.900/0001-26',
                'uf' => 'AM',
                'logo' => 'img/empresas/oliveira.jpg',
                'cor_primaria' => '#013155',
                'cor_secundaria' => '#e0a630',
                'recursos' => ['print_agent', 'viagens'],
                'validade' => now()->addYear(),
                'ativo' => true,
            ],
            default => ['nome' => ucfirst($slug), 'nome_fantasia' => ucfirst($slug), 'recursos' => ['print_agent'], 'ativo' => true],
        });

        // garante os recursos ligados mesmo se a empresa já existia (firstOrCreate não atualiza)
        $empresa->recursos = array_values(array_unique(array_merge($empresa->recursos ?? [], ['print_agent', 'viagens'])));
        $empresa->ativo = true;
        $empresa->save();

        // outras empresas que porventura existam ficam desativadas nesta instalação
        Empresa::where('id', '!=', $empresa->id)->update(['ativo' => false]);

        // GERATEC (super-admin) — vinculado à própria empresa do cliente
        $this->usuario('hemerson321@gmail.com', [
            'login' => 'hemerson', 'empresa_id' => $empresa->id, 'name' => 'Hemerson (GERATEC)',
            'super_admin' => true, 'admin' => true, 'perm_cadastra' => true, 'perm_baixa' => true, 'perm_caixa' => true, 'setor' => 'Suporte',
        ]);

        // administrador do cliente (login: admin)
        $this->usuario("admin@{$slug}.fbre", [
            'login' => 'admin', 'empresa_id' => $empresa->id, 'name' => 'Administrador',
            'admin' => true, 'perm_cadastra' => true, 'perm_baixa' => true, 'perm_caixa' => true, 'setor' => 'Escritório',
            'password' => env('SEED_ADMIN_PASSWORD') ?: 'Malu2013@123',
        ]);

        $this->dadosBase($empresa);
    }

    /** Cria o usuário se não existir; se existir, só garante login/permissões (não mexe na senha). */
    protected function usuario(string $email, array $dados): void
    {
        $u = User::where('email', $email)->orWhere('login', $dados['login'])->first();
        if ($u) {
            unset($dados['password']); // não mexe na senha de quem já existe
            $u->fill($dados)->save();
        } else {
            $dados['password'] = $dados['password'] ?? env('SEED_ADMIN_PASSWORD', 'mudar123');
            User::create($dados + ['email' => $email]);
        }
    }

    protected function dadosBase(Empresa $empresa): void
    {
        Tenant::set($empresa->id);

        foreach (['Escritório', 'Porto', 'Embarcação'] as $s) {
            Setor::firstOrCreate(['empresa_id' => $empresa->id, 'nome' => $s]);
        }

        foreach (['Manaus', 'Parintins', 'Itacoatiara', 'Santarém', 'Juruti', 'Óbidos', 'Oriximiná'] as $l) {
            Localidade::firstOrCreate(['empresa_id' => $empresa->id, 'nome' => $l]);
        }

        if (Embarcacao::count() === 0) {
            Embarcacao::create(['nome' => $empresa->slug === 'oliveira' ? 'Ferry Boat Rainha Esther' : 'Embarcação 1']);
        }

        foreach ([
            ['Caixa pequena', 20], ['Caixa média', 35], ['Caixa grande', 50],
            ['Saco / fardo', 25], ['Envelope / documento', 10], ['Eletrodoméstico', 80],
        ] as [$nome, $valor]) {
            Produto::firstOrCreate(['empresa_id' => $empresa->id, 'nome' => $nome], ['valor_padrao' => $valor]);
        }

        // rotas (módulo viagens) — Ferry Boat Rainha Esther
        if (in_array('viagens', $empresa->recursos ?? [], true) && $empresa->slug === 'oliveira') {
            // desativa a rota de exemplo antiga, se existir
            \App\Models\Rota::where('empresa_id', $empresa->id)->where('nome', 'Linha Tefé')->update(['ativo' => false]);

            // Subida (Manaus → Uarini) — dias: 0=Dom 1=Seg 2=Ter 3=Qua 4=Qui 5=Sex 6=Sáb
            $this->criarRota($empresa, 'Subida — Manaus → Uarini', [
                ['Manaus', 2, '11:00'],   // Terça 11h (partida)
                ['Codajás', 3, '06:30'],  // Quarta 6h-7h
                ['Coari', 3, '16:00'],    // Quarta 16h-17h
                ['Tefé', 4, '07:00'],     // Quinta 7h
                ['Alvarães', 4, '19:00'], // Quinta 19h
                ['Uarini', 5, '07:00'],   // Sexta 7h (chegada)
            ]);
            // Descida (Uarini → Manaus)
            $this->criarRota($empresa, 'Descida — Uarini → Manaus', [
                ['Uarini', 6, '07:00'],   // Sábado 7h (partida)
                ['Alvarães', 6, '11:00'], // Sábado 11h
                ['Tefé', 6, '17:30'],     // Sábado 15h passagem / 17h30 saída
                ['Coari', 0, '08:30'],    // Domingo 6h chegada / 8h30 saída
                ['Codajás', 0, '14:00'],  // Domingo 14h
                ['Manaus', 1, '08:00'],   // Segunda de manhã (chegada)
            ]);
        }

        Tenant::clear();
    }

    /** Cria uma rota com suas paradas (idempotente pelo nome). */
    protected function criarRota(Empresa $empresa, string $nome, array $paradas): void
    {
        $rota = \App\Models\Rota::firstOrCreate(['empresa_id' => $empresa->id, 'nome' => $nome], ['ativo' => true]);
        if ($rota->paradas()->exists()) return;
        foreach ($paradas as $i => [$loc, $dia, $hora]) {
            $rota->paradas()->create(['ordem' => $i + 1, 'localidade' => $loc, 'dia_semana' => $dia, 'hora' => $hora]);
        }
    }
}
