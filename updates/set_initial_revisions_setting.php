<?php

namespace OFFLINE\Boxes\Updates;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Models\BoxesSetting;

/**
 * Migrates the revisions settings from the config file to the settings model.
 */
class SetInitialRevisionsSetting extends Migration
{
    public function up()
    {
        $pluginInstallationEntry = DB::table('system_plugin_history')
            ->where('code', 'OFFLINE.Boxes')
            ->where('type', 'comment')
            ->where('version', '1.0.1')
            ->first();

        // If the plugin was installed before, keep the old default value (true) for the revisions setting.
        // On newer installations the default value is false.
        $enableRevisions = $pluginInstallationEntry && Carbon::parse($pluginInstallationEntry->created_at)->lt('2024-04-13 12:00:00');

        // If the revisions system was explicitly disabled before, keep it disabled.
        if (config('offline.boxes::features.revisions') === false) {
            $enableRevisions = false;
        }

        // Disable for non-pro versions by default.
        if (!Features::instance()->isProVersion) {
            $enableRevisions = false;
        }

        BoxesSetting::set('revisions_enabled', $enableRevisions);

        if (!$enableRevisions) {
            BoxesSetting::set('revisions_cleanup_enabled', false);
        }
    }

    public function down()
    {
        // Do nothing.
    }
}
