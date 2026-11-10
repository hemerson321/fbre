<?php

namespace App\Exceptions;

/**
 * Recusa por REGRA DE NEGÓCIO (cota de gratuidade, limites do operador, etc.).
 * Diferente de um erro de servidor: o sync devolve 422 (não fica repetindo como se fosse 500).
 */
class VendaRecusada extends \RuntimeException
{
}
