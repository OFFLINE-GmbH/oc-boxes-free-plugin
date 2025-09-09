<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * The version column was released as varchar by mistake.
 * This migration changes the type to int.
 */
return new class() extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('offline_boxes_pages', 'version')) {
            return;
        }

        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->integer('version')->nullable()->change();
        });
    }

    public function down()
    {
        // Do nothing.
    }
};
