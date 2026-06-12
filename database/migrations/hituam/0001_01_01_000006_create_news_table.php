<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS "SQ_MSHNEWS_IID" START 1 INCREMENT 1');

        Schema::create('HITUAM_MSHNEWS', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHNEWS_IID"\')'));
            $table->string('VTITLE', 100); // title
            $table->string('VSUBJECT', 100); // subject
            $table->string('AVIEWERS')->nullable(); // viewers as published to vendor/customer/all
            $table->integer('ITOTALVIEW')->nullable(); // total view
            $table->text('VCONTENT')->nullable(); // content
            $table->string('VIMAGE_PATH')->nullable();
            $table->string('VFILE_PATH')->nullable();
            $table->boolean('BSTATUS')->default(false); // status draft/publish
            $table->timestamp('DPUBLISHED_AT')->nullable();
            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });
    }

    // info_id
    // title
    // category
    // viewers
    // summary
    // published_at
    // expired_at

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('HITUAM_MSHNEWS');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHNEWS_IID"');
    }
};
