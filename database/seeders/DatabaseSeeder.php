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

        User::firstOrCreate(['email' => 'hemerson321@gmail.com'], [
            'empresa_id' => $geratec->id,
            'name' => 'Hemerson',
            'password' => env('SEED_ADMIN_PASSWORD', 'mudar123'),
            'super_admin' => true,
            'admin' => true,
            'perm_cadastra' => true,
            'perm_baixa' => true,
            'perm_caixa' => true,
            'setor' => 'Escritório',
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
            'recursos' => ['print_agent'],
            'validade' => now()->addYear(),
            'ativo' => true,
        ]);

        User::firstOrCreate(['email' => 'admin@oliveira.fbre'], [
            'empresa_id' => $oliveira->id,
            'name' => 'Administrador Oliveira',
            'password' => env('SEED_ADMIN_PASSWORD', 'mudar123'),
            'admin' => true,
            'perm_cadastra' => true,
            'perm_baixa' => true,
            'perm_caixa' => true,
            'setor' => 'Escritório',
        ]);

        $this->dadosBase($oliveira);
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

        Tenant::clear();
    }
}
