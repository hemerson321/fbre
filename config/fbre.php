<?php

return [
    // Modo empresa única: a instalação atende só uma empresa (slug abaixo).
    // Esconde a área de "Empresas (clientes)" e a tela de login já abre com a identidade dela.
    'single' => (bool) env('FBRE_SINGLE', true),
    'empresa' => env('FBRE_EMPRESA', 'oliveira'),
];
