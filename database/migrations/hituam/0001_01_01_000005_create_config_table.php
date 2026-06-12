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
        DB::statement('CREATE SEQUENCE IF NOT EXISTS "SQ_MSHCONFIGURATIONS_IID" START 1 INCREMENT 1');

        Schema::create('HITUAM_MSHCONFIGURATIONS', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHCONFIGURATIONS_IID"\')'));
            $table->string('VVARIABLE', 100);
            $table->text('VVALUE', 100);
            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('HITUAM_MSHCONFIGURATIONS');

        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHCONFIGURATIONS_IID"');
    }
};
