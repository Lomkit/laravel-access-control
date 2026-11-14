<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_reviewers', function (Blueprint $table): void {
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->primary(['article_id', 'user_id']);
        });

        Schema::create('article_notice', function (Blueprint $table): void {
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('notice_id');
            $table->primary(['article_id', 'notice_id']);
        });
    }
};
