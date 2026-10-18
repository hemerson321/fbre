# FBRE — contexto para agentes

Leia `README.md` (documento-mestre do projeto) antes de qualquer alteração.

## Regras do código
- Laravel 13, PHP 8.3, MariaDB. Sem WordPress.
- **Multiempresa:** todo model de dados usa a trait `PertenceEmpresa` (escopo global por `empresa_id`).
  Nunca crie tabela de dados sem `empresa_id`. Nunca escreva `if ($empresa->slug === 'x')` no código.
- Personalização por empresa: campos em `empresas` (logo, cores, templates, `recursos`, `config`).
  Template de impresso: `resources/views/impressao/{comprovante|etiqueta}/{nome}.blade.php`.
- Permissões: `perm:cadastra | perm:baixa | perm:caixa | perm:admin | perm:super` (middleware).
- Layout: Tailwind (CDN) + Alpine. Deve funcionar em desktop, celular e POS 5" (botões ≥ 48px).
- Impressão: navegador (80 mm) e FBRE Print Agent (`/api/print/*`, pasta `print-agent/`).
- Textos da interface em português do Brasil.

## Comandos
- `php artisan migrate --seed` — cria tabelas e empresas iniciais (GERATEC + Oliveira)
- `php artisan serve` — dev local
- Deploy: push na `main` → EasyPanel builda o `Dockerfile`.
