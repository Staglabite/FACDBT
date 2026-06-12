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
        DB::statement('CREATE SEQUENCE "SQ_MSHUSERS_IID" START 1 INCREMENT 1');
        DB::statement('CREATE SEQUENCE "SQ_MSHROLES_NID" START 1 INCREMENT 1');
        DB::statement('CREATE SEQUENCE "SQ_MSHUSERROLES_NID" START 1 INCREMENT 1');

        Schema::create('HITUAM_MSHUSER', function (Blueprint $table) {
            $table->bigInteger('IID')->primary()->default(DB::raw('nextval(\'"SQ_MSHUSERS_IID"\')'));
            $table->string('VEMPNO')->nullable();
            $table->string('VPASSWORD');
            $table->string('VUSERNAME', 100)->unique();
            $table->string('VPHONE', 20)->nullable();
            $table->string('VEMAIL')->nullable();
            $table->string('VPHOTO', 120)->nullable();
            $table->timestamp('DLASTLOGIN')->nullable();
            $table->string('VSESSIONTOKEN', 120)->nullable();

            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('HITUAM_MSHROLES', function (Blueprint $table) {
            $table->bigInteger('NID')->primary()->default(DB::raw('nextval(\'"SQ_MSHROLES_NID"\')'));
            $table->string('VROLENAME', 120)->unique();
            $table->string('VROLEDESC', 200)->nullable();
            $table->boolean('BSTATUS')->default(false);

            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('HITUAM_MSHUSERROLES', function (Blueprint $table) {
            $table->bigInteger('NID')->primary()->default(DB::raw('nextval(\'"SQ_MSHUSERROLES_NID"\')'));
            $table->string('VUSERNAME')->nullable();
            $table->string('VROLE')->nullable();
            $table->foreign('VUSERNAME')
                ->references('VUSERNAME')
                ->on('HITUAM_MSHUSER')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('VROLE')
                ->references('VROLENAME')
                ->on('HITUAM_MSHROLES')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['VUSERNAME', 'VROLE'], 'HITUAM_MSHUSERROLES_VUSERNAME_VROLENAME_UNIQUE');

            $table->string('VCREA', 100)->nullable();
            $table->timestamp('DCREA')->nullable();
            $table->string('VMODI', 100)->nullable();
            $table->timestamp('DMODI')->nullable();
            $table->string('VDELETE', 100)->nullable();
            $table->timestamp('DDELETE')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id');
            $table->string('application')->default(config('app.code'));
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->primary(['id', 'application']);

            $table->index(['user_id', 'application']);
            $table->index(['last_activity', 'application']);
        });

        Schema::create('remember_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('HITUAM_MSHUSER', 'IID')
                ->onDelete('cascade');
            $table->string('application')->default(config('app.code'));
            $table->string('token', 100)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Unique constraint: one remember token per user per application
            $table->unique(['user_id', 'application']);
            $table->index(['token', 'application']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('HITUAM_MSHUSERROLES');
        Schema::dropIfExists('HITUAM_MSHROLES');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('remember_tokens');
        Schema::dropIfExists('HITUAM_MSHUSERS');

        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHUSERS_IID"');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHROLES_NID"');
        DB::statement('DROP SEQUENCE IF EXISTS "SQ_MSHUSERROLES_NID"');
    }
};
