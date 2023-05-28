<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Models\Box;
use Schema;

/**
 * Add Box references feature.
 */
class AddBoxReferences extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('offline_boxes_boxes', 'origin_box_id')) {
            return;
        }

        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            if (!Schema::hasColumn('offline_boxes_boxes', 'origin_box_id')) {
                $table->integer('origin_box_id')->nullable()->after('holder_id');
            }

            if (!Schema::hasColumn('offline_boxes_boxes', 'version')) {
                $table->integer('references_box_id')->nullable()->after('origin_box_id');
            }
        });

        Box::get()->each(function (Box $box) {
            $box->origin_box_id = $box->id;
            $box->save();
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            $table->dropColumn('origin_box_id');
            $table->dropColumn('references_box_id');
        });
    }
}
