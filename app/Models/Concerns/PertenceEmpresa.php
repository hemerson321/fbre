<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escopo global multiempresa.
 * Toda consulta e toda criação ficam presas à empresa do usuário logado.
 * Super-admin pode usar Tenant::set($empresaId) para "entrar" numa empresa.
 */
trait PertenceEmpresa
{
    public static function bootPertenceEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if ($id = Tenant::id()) {
                $builder->where($builder->getModel()->getTable().'.empresa_id', $id);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->empresa_id) && ($id = Tenant::id())) {
                $model->empresa_id = $id;
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** Consulta sem o escopo (uso exclusivo do super-admin). */
    public static function todasEmpresas(): Builder
    {
        return static::withoutGlobalScope('empresa');
    }
}
