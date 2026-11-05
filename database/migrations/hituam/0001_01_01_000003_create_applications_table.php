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
        DB::statement('CREATE SEQUENCE "SQ_MSHAPPLICATION_IID" START 1 INCREMENT 1');

        Schema::create('HITUAM_MSHAPPLICATION', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHAPPLICATION_IID"\')'));
            $table->string('VPROJECTDESC', 255)->nullable();
            $table->string('VDEPT', 255)->nullable();
            $table->string('VPIC', 255)->nullable();
            $table->string('VPORTALACCESS', 255)->nullable();
            $table->string('VPUBLISH', 255)->nullable();
            $table->string('VPORTALNAME', 255)->nullable();
            $table->string('VOPERATIONAL', 255)->nullable();
            $table->string('VSTRDZATION', 255)->nullable();
            $table->string('VPREFIXPROJECT', 255)->nullable();
            $table->string('VDATABASE', 255)->nullable();
            $table->integer('NORDERPROJECT')->nullable();
            $table->string('VICON');
            $table->boolean('BIS_EMBED')->default(true);
            $table->string('VHOST')->nullable();

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
        Schema::dropIfExists('HITUAM_MSHAPPLICATION');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHAPPLICATION_IID"');
    }
};
