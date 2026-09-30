<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanchaya_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sanchaya_file_id')
                ->constrained('sanchaya_files')
                ->onDelete('cascade');

            $table->morphs('attachable');
            $table->string('group')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(
                ['sanchaya_file_id', 'attachable_type', 'attachable_id', 'group'],
                'sanchaya_attachment_file_attachable_group_unique'
            );
            $table->index(
                ['attachable_type', 'attachable_id', 'group'],
                'sanchaya_attachment_attachable_group_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanchaya_attachments');
    }
};
