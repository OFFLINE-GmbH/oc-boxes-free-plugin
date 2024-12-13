<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add open graph columns to pages table.
 */
class AddOpenGraphColumnsToPagesTable extends Migration
{
    public function up()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->string('og_title')->after('meta_description')->nullable();
            $table->string('og_description')->after('og_title')->nullable();
            $table->string('og_type')->after('og_description')->nullable();
            $table->string('canonical_url')->after('url')->nullable();
            $table->text('meta_robots')->after('meta_description')->nullable();
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn(['og_title', 'og_description', 'og_type', 'canonical_url', 'meta_robots']);
        });
    }
}
