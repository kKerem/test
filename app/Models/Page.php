<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'slug',
        'title',
        'content_json',
        'published',
        'version',
        'created_by',
    ];

    protected $casts = [
        'content_json' => 'array',
        'published' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


