<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Category extends Model
{
    // 1. Perbarui fillable sesuai kolom baru
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * Otomatis membuat slug dari name saat data disimpan.
     */
    protected static function boot()
    {
        parent::boot();
        static::saving(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }

    /**
     * Relasi ke Kategori Utama (Parent)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relasi ke Sub-Kategori (Children)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Relasi lama kamu ke Complaints (pastikan relasi ini tetap aman)
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * Scope untuk mempermudah memanggil kategori yang aktif saja
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}