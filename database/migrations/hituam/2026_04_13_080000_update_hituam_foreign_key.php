<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('HITUAM_MSHSERVICES', function (Blueprint $table) {
            $table->dropForeign(['VMENUID']);

            $table->foreign('VMENUID')
                ->references('VAPPID')
                ->on('HITUAM_MSHMENUS')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('HITUAM_MSHSERVICES', function (Blueprint $table) {
            $table->dropForeign(['VMENUID']);

            $table->foreign('VMENUID')
                ->references('VAPPID')
                ->on('HITUAM_MSHMENUS');
        });
    }
};
