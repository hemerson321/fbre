# FBRE — Módulo de Encomendas (SaaS)

**Domínio:** fbre.com.br
**Dono:** GERATEC Soluções — Hemerson Prestes Assunção — CNPJ 44.079.100/0001-20
**Status:** em desenvolvimento (início 18/10/2026)

---

## 1. O que é

Sistema em nuvem para transportadoras fluviais controlarem **encomendas** e **caixa**.
Produto da GERATEC, vendido por licença de uso (SaaS). Multiempresa: um sistema, vários clientes.

**Primeiros clientes**
| Empresa | CNPJ | Situação |
|---|---|---|
| GERATEC (teste interno) | 44.079.100/0001-20 | empresa de testes |
| E Oliveira Fernandes – Transportes Fluviais – ME | 05.967.900/0001-26 | contrato fechado em 17/10/2026 |

---

## 2. Comercial

**Tabela de preço padrão**
- Implantação: R$ 6.000 (2x) — *fechado com E Oliveira por R$ 5.000 (2x R$ 2.500)*
- Mensalidade: R$ 250/mês — hospedagem em nuvem, backup diário, suporte por AnyDesk e WhatsApp
- Contrato de 12 meses (hospedagem contratada anualmente), renovação automática
- Prazo de entrega: 30 a 45 dias após a 1ª parcela
- Não incluso: impressora térmica, bobinas, etiquetas
- Novas funções, relatórios extras e telas diferentes: orçados à parte

**Regras**
- O software é da GERATEC. O cliente tem licença de uso; ao encerrar, recebe só os dados (planilha), nunca cópia do sistema.
- Arquivos: `Orcamento_Modulo_Encomendas_GERATEC.pdf`, `Contrato_Modulo_Encomendas_GERATEC.pdf`

---

## 3. Funcionalidades (módulo Encomendas + Caixa)

**Cadastro de encomenda**
- Origem, destino, embarcação (transporte)
- Remetente e destinatário: nome, telefone/WhatsApp, CPF/CNPJ
- Produtos (manual ou do catálogo), quantidade, valor, observação
- Pagamento: À vista / A receber / Faturado — meios: Dinheiro, PIX, Débito, Crédito, desconto
- Mostra Total / Pago / Devido
- Setor e operador que cadastrou
- Ao salvar: imprime comprovante (térmica 80 mm) e etiqueta por volume (1/3, 2/3…)

**Filtro / baixa**
- Filtros: período, status de pagamento, origem, destino, entregue, embarcação, busca por nome
- Cards de totais: recebido, a receber, descontos, qtd encomendas, volumes, por meio de pagamento
- Cores: verde = pago + entregue · amarelo = pago, não entregue · vermelho = não pago, não entregue
- Ações: registrar pagamento (modal), dar baixa na entrega (imprime comprovante)
- Impressos: Protocolo (lista DOC/DATA/ASS para assinatura) e Listagem

**Caixa**
- Entradas por operador, por meio de pagamento, quem deu baixa e quando
- Totais do dia, semana, mês ou período personalizado

**Usuários e permissões (admin da empresa)**
- Só cadastra · pode dar baixa (pagamento e entrega) · pode ver o caixa
- Cada operador tira o próprio relatório (dia, semana, mês, personalizado)

**Super-admin (GERATEC)**
- Criar/ativar/desativar empresa, validade do contrato, bloqueio por inadimplência
- Ver usuários por empresa

---

## 4. Decisões técnicas

| Item | Decisão | Motivo |
|---|---|---|
| Plataforma | **Laravel (PHP 8.3) + MariaDB**, não WordPress | 1.000+ cadastros/dia; postmeta do WP não aguenta; multiempresa limpo |
| Hospedagem | **Docker no EasyPanel** (Contabo) | padrão GERATEC; fácil replicar |
| Multiempresa | Um banco, `empresa_id` em todas as tabelas, escopo global | vender = cadastrar empresa nova |
| Frontend | Blade + Alpine.js + DataTables server-side | leve, funciona no celular, stack conhecida |
| Impressão | Página 80 mm pelo navegador; QZ Tray opcional | mesmo padrão do ANAMAZON |
| Cópia por cliente | Possível (mesmo repo, novo app no EasyPanel, `.env` próprio) | só quando o cliente exigir servidor separado |

**Personalização por empresa (sem mexer no código)**
- Logo, cor primária/secundária, nome no topo, favicon
- Dados de impressão: nome, CNPJ, fones, PIX no cabeçalho
- Campos ligados/desligados, meios de pagamento aceitos, texto de rodapé
- Template de impresso por empresa: `impressao/comprovante/{padrao|cliente}.blade.php`
- Recursos por flag (`empresa.recursos`): módulos extras ativados só pra quem pagou
- Numeração de encomenda independente por empresa

**Regra de ouro:** melhoria geral → todos ganham. Pedido específico → configuração/flag/template, cobrado. **Tela diferente → projeto separado** (instância própria). Nunca `if cliente == 'x'` no código.

---

## 5. Banco de dados (esboço)

```
empresas            id, nome, cnpj, dominio, logo, cor1, cor2, pix, fones, validade, ativo, recursos(json)
empresa_configs     empresa_id, chave, valor
users               empresa_id, nome, email, senha, setor, perm_cadastra, perm_baixa, perm_caixa, super_admin
embarcacoes         empresa_id, nome
trechos             empresa_id, origem, destino
produtos            empresa_id, nome, valor_padrao
encomendas          empresa_id, numero, embarcacao_id, origem, destino, remetente_*, destinatario_*,
                    total, desconto, pago, devido, forma(avista|areceber|faturado), status_pag, entregue,
                    setor, user_id, created_at
encomenda_itens     encomenda_id, produto, qtd, valor, obs
volumes             encomenda_id, seq, total
pagamentos          empresa_id, encomenda_id, meio(dinheiro|pix|debito|credito), valor, user_id, created_at
baixas              empresa_id, encomenda_id, tipo(pagamento|entrega), user_id, created_at
caixa_movimentos    empresa_id, user_id, meio, valor, encomenda_id, created_at   (índice empresa_id+created_at)
```

---

## 6. Infra

- **Repositório:** GitHub `geratec/fbre` (a criar)
- **EasyPanel:** app `fbre` → serviços: app (php-fpm+nginx), mariadb, redis
- **Domínio:** fbre.com.br (SSL automático)
- **Backup:** dump diário do MariaDB → storage externo
- **Ambientes:** `.env` por instância (banco, domínio, chaves)

---

## 7. Próximos passos

- [ ] Criar repositório e esqueleto Laravel em Docker
- [ ] Migrations + models + seed (GERATEC e E Oliveira como empresas)
- [ ] Login, permissões, painel super-admin
- [ ] Tela de cadastro de encomenda + comprovante + etiqueta
- [ ] Filtro / baixa / protocolo
- [ ] Caixa e relatórios por operador
- [ ] Deploy no EasyPanel em fbre.com.br
- [ ] Treinamento E Oliveira Fernandes

---

## 8. Histórico

- 17/10/2026 — Orçamento enviado; cliente E Oliveira fechou por R$ 5.000 + R$ 250/mês (12 meses). Contrato gerado.
- 18/10/2026 — Definida stack Laravel + MariaDB + Docker/EasyPanel. Domínio fbre.com.br.
