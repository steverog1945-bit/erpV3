<?php

namespace App\Support\Concerns;

use App\Services\AuditLogger;

/**
 * Tambahkan `use Auditable;` ke model Eloquent yang perubahannya perlu
 * tercatat otomatis - hook ke event created/updated/deleted bawaan
 * Eloquent. Field password/token TIDAK pernah ikut tersimpan di
 * old_values/new_values (lihat $hiddenFromAudit) supaya audit_logs tidak
 * jadi kebocoran kredensial.
 *
 * CATATAN: cuma menangkap perubahan lewat Model::create()/update()/
 * delete() Eloquent. Query DB::table() TIDAK memicu event Eloquent sama
 * sekali - untuk jalur itu panggil App\Services\AuditLogger::log() manual
 * (lihat catatan lengkap di AuditLogger).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLogger::log(
                'created',
                static::class,
                $model->getKey(),
                null,
                $model->auditableAttributes($model->getAttributes())
            );
        });

        static::updated(function ($model) {
            $dirty = $model->auditableAttributes($model->getChanges());
            if (empty($dirty)) {
                return;
            }
            $original = $model->auditableAttributes(array_intersect_key($model->getOriginal(), $dirty));
            AuditLogger::log('updated', static::class, $model->getKey(), $original, $dirty);
        });

        static::deleted(function ($model) {
            AuditLogger::log(
                'deleted',
                static::class,
                $model->getKey(),
                $model->auditableAttributes($model->getAttributes()),
                null
            );
        });
    }

    private function auditableAttributes(array $attributes): array
    {
        $disembunyikan = property_exists($this, 'hiddenFromAudit')
            ? $this->hiddenFromAudit
            : ['password', 'remember_token'];

        return array_diff_key($attributes, array_flip($disembunyikan));
    }
}
