<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados de empresa POR TRANSPORTE (exibidos no bilhete). Cada embarcacao pode ter
 * sua propria razao social, CNPJ, IE, endereco, etc. Se vazio, o bilhete usa os da empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach ([
                'nome_fantasia' => 120, 'razao_social' => 160, 'cnpj' => 24, 'ie' => 24,
                'endereco' => 200, 'cep' => 12, 'contato' => 40, 'email_bilhete' => 120,
            ] as $col => $len) {
                if (! Schema::hasColumn('embarcacoes', $col)) {
                    Schema::table('embarcacoes', fn (Blueprint $x) => $x->string($col, $len)->nullable());
                }
            }
        });

        // Semeia os dados do cliente na lancha MADAME CRYS (idempotente)
        DB::table('embarcacoes')->whereRaw('LOWER(nome) = ?', ['madame crys'])->update([
            'nome_fantasia' => 'Ajato Passagem',
            'razao_social'  => 'A S AGENCIAMENTO MARITIMO LTDA',
            'cnpj'          => '10.579.831/0001-13',
            'ie'            => '42.919.04-5',
            'endereco'      => 'RUA LIRIO AMARELO, Nº 13, CASA A, SALA 01, MANAUS - AM',
            'cep'           => '69070-463',
            'contato'       => '92 99982-8170',
            'email_bilhete' => 'ATENDIMENTO@TERMINALAJATO.COM.BR',
        ]);
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach (['nome_fantasia', 'razao_social', 'cnpj', 'ie', 'endereco', 'cep', 'contato', 'email_bilhete'] as $c) {
                if (Schema::hasColumn('embarcacoes', $c)) $t->dropColumn($c);
            }
        });
    }
};
