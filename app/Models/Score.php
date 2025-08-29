<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Score extends Model
{
    use HasFactory;
    // ⬇ 編集を許可するカラムを指定（ホワイトリスト）
    protected $fillable = ['user_id', 'song_id', 'rank', 'fc', 'memo'];
    protected $casts = [
        'fc' => 'boolean',   // ★ 追加：API応答が true/false になる
    ];

    // ⬇ リレーション（1つのスコアは1つの曲に属する）
    public function song()
    {
        return $this->belongsTo(\App\Models\Song::class);
    }

    // 必要であれば user() リレーションも
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
