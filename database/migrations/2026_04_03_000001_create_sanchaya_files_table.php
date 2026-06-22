<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanchaya_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('sanchaya_files')
                ->onDelete('cascade');

            $table->string('type', 10)->default('file')->index();
            $table->string('disk')->index();

            $table->string('file_name');
            $table->string('original_name');

            $table->text('path');

            $table->string('extension')->nullable();
            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('size')->default(0);

            $table->json('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['parent_id', 'file_name', 'disk', 'type'],
                'sanchaya_unique_file_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanchaya_files');
    }
};
