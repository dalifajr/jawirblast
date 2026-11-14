<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_group_pivot', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('group_id')->constrained('contact_groups')->cascadeOnDelete()->cascadeOnUpdate();

            $table->primary(['contact_id', 'group_id']);
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_group_pivot');
    }
};
