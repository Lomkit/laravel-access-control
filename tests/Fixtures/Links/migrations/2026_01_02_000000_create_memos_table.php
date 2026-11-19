<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table): void {
            $table->id();
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('memo_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('memo_id')->constrained();
            $table->morphs('subject');
            $table->timestamps();
        });
    }
};
