<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    // ⬇ 編集を許可するカラムを指定（ホワイトリスト）
    protected $fillable = ['user_id', 'song_id', 'rank', 'fc', 'memo'];

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
