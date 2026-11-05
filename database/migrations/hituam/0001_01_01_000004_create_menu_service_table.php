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
        DB::statement('CREATE SEQUENCE "SQ_MSHMENUS_IID" START 1 INCREMENT 1');
        DB::statement('CREATE SEQUENCE "SQ_MSHSERVICES_IID" START 1 INCREMENT 1');
        DB::statement('CREATE SEQUENCE "SQ_MSHROLESERVICES_NID" START 1 INCREMENT 1');
        DB::statement('CREATE SEQUENCE "SQ_MSHROLEACCESS_NID" START 1 INCREMENT 1');

        Schema::create('HITUAM_MSHMENUS', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHMENUS_IID"\')'));
            $table->string('VAPPID', 100)->unique()->nullable();
            $table->string('VFLAG')->nullable();
            $table->string('VICON', 120)->nullable();
            $table->string('VAPPDESC', 100);
            $table->string('VURL')->nullable();
            $table->string('VDESC')->nullable();
            $table->string('VTYPEAPP', 120)->nullable();
            $table->integer('NSORTAPP')->default(0);
            $table->string('VENVAPP')->nullable();
            $table->string('VPARENT')->nullable();
            $table->foreignId('NSORTPROJECT')
                ->constrained('HITUAM_MSHAPPLICATION', 'IID')
                ->onDelete('cascade');

            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('HITUAM_MSHSERVICES', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHSERVICES_IID"\')'));
            $table->string('VNAME', 100)->unique();
            $table->string('VDESC');
            $table->string('VURL', 100);
            $table->string('VMETHOD', 32);
            $table->date('DBEGINEFF');
            $table->date('DENDEFF');
            $table->bigInteger('IAPPLICATIONID');
            $table->foreign('IAPPLICATIONID')
                ->references('IID')
                ->on('HITUAM_MSHAPPLICATION');
            $table->string('VMENUID');
            $table->foreign('VMENUID')
                ->references('VAPPID')
                ->on('HITUAM_MSHMENUS');
            $table->unique(['VMENUID', 'VNAME'], 'HITUAM_MSHSERVICES_VMENUID_VNAME_unique');
            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('HITUAM_MSHROLESERVICES', function (Blueprint $table) {
            $table->bigInteger('NID')->primary()->default(DB::raw('nextval(\'"SQ_MSHROLESERVICES_NID"\')'));
            $table->string('VROLE')->nullable();
            $table->string('VSERVICE')->nullable();
            $table->foreign('VROLE')
                ->references('VROLENAME')
                ->on('HITUAM_MSHROLES')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('VSERVICE')
                ->references('VNAME')
                ->on('HITUAM_MSHSERVICES')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['VSERVICE', 'VROLE'], 'HITUAM_MSHROLEACCESS_ISERVICE_IROLE_unique');

            $table->string('VCREA', 100)->nullable();
            $table->date('DBEGINEFF')->nullable();
            $table->date('DENDEFF')->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('HITUAM_MSHROLEACCESS', function (Blueprint $table) {
            $table->bigInteger('NID')->primary()->default(DB::raw('nextval(\'"SQ_MSHROLEACCESS_NID"\')'));
            $table->string('VROLE')->nullable();
            $table->string('VMENUID')->nullable();
            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
            $table->foreign('VROLE')
                ->references('VROLENAME')
                ->on('HITUAM_MSHROLES')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('VMENUID')
                ->references('VAPPID')
                ->on('HITUAM_MSHMENUS')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['VMENUID', 'VROLE'], 'HITUAM_MSHROLEACCESS_IMENU_IROLE_unique');

            $table->boolean('BSTATUS')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('HITUAM_MSHROLEACCESS');
        Schema::dropIfExists('HITUAM_MSHROLESERVICES');
        Schema::dropIfExists('HITUAM_MSHSERVICES');
        Schema::dropIfExists('HITUAM_MSHMENUS');

        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHMENUS_IID"');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHSERVICES_IID"');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHROLESERVICES_IID"');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHROLEACCESS_NID"');
    }
};
