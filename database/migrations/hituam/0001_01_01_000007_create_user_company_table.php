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
        DB::statement('CREATE SEQUENCE IF NOT EXISTS "SQ_MSHSUPPLIERS_IID" START 1 INCREMENT 1');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS "SQ_MSHSUPPLIER_COMMUNICATION_METHODS_IID" START 1 INCREMENT 1');

        Schema::create('HITUAM_MSHSUPPLIERS', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHSUPPLIERS_IID"\')'));
            $table->string('VSUPPLIER_CODE', 100);
            $table->string('VNAME', 100)->nullable();
            $table->string('VADDRESS')->nullable();
            $table->string('VCOUNTRY')->nullable();
            $table->string('VSUPPLIER_TYPE', 100);
            $table->string('VNPWP')->nullable();
            $table->string('VNIK')->nullable();
            $table->boolean('BPKP')->default(false);
            $table->string('VPAYMENT_TERM')->nullable();
            $table->string('VGROUP')->nullable();
            $table->string('VSTAT_GROUP')->nullable();
            $table->string('VTAX_CODE')->nullable();
            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('HITUAM_MSHSUPPLIER_COMMUNICATION_METHODS', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHSUPPLIER_COMMUNICATION_METHODS_IID"\')'));
            $table->bigInteger('ICOMM_ID')->nullable();
            $table->string('VSUPPLIER_CODE', 100);
            $table->string('VSUPPLIER_NAME', 100);
            $table->string('VUSERNAME', 100)->nullable();
            $table->string('VNAME', 100)->nullable();
            $table->string('VMETHOD_ID', 100)->nullable();
            $table->string('VDESCRIPTION', 100)->nullable();
            $table->text('VADDRESS_ID')->nullable();
            $table->string('VPARTY_TYPE_DB_VAL', 100)->nullable();
            $table->string('BMETHOD_DEFAULT', 100)->default(false);
            $table->string('VVALUE', 100)->nullable();
            $table->bigInteger('IUSER_ID')->nullable();
            $table->foreignId('ISUPPLIER_ID')
                ->constrained('HITUAM_MSHSUPPLIERS', 'IID')
                ->onDelete('cascade');
            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();

            // TAMBAH composite unique
            // $table->unique(
            //     ['ICOMM_ID', 'ISUPPLIER_ID'],
            //     'uq_comm_supplier'
            // );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('FACTWM_MSHSUPPLIER_COMMUNICATION_METHODS', function (Blueprint $table) {
        //     $table->dropUnique('uq_comm_supplier');
        // });

        Schema::dropIfExists('HITUAM_MSHSUPPLIER_COMMUNICATION_METHODS');
        Schema::dropIfExists('HITUAM_MSHSUPPLIERS');

        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHSUPPLIERS_IID"');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHSUPPLIER_COMMUNICATION_METHODS_IID"');
    }
};
