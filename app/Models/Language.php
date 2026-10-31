<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    /** @use HasFactory<\Database\Factories\LanguageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Whether any card uses this language (such languages cannot be deleted).
     */
    public function isInUse(): bool
    {
        return Card::where('lang1_id', $this->id)->orWhere('lang2_id', $this->id)->exists();
    }
}
