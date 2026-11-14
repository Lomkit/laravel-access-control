<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->string('title');
            $table->boolean('published')->default(true);
            $table->unsignedBigInteger('workspace_id');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }
};
