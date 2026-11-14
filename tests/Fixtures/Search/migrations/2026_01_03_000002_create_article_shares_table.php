<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_shares', function (Blueprint $table): void {
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['article_id', 'user_id']);
        });
    }
};
