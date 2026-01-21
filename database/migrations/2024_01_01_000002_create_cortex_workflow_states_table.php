<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cortex_workflow_states', function (Blueprint $table) {
            $table->id();
            $table->string('run_id')->unique();
            $table->string('workflow_id')->index();
            $table->string('current_node')->nullable();
            $table->string('status')->index();
            $table->json('data');
            $table->json('history');
            $table->string('pause_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cortex_workflow_states');
    }
};
