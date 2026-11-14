<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->string('title');
            $table->boolean('public')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }
};
