<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forum_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->string('body');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });
    }
};
