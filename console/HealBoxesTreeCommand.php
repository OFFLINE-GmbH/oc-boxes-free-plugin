<?php

namespace OFFLINE\Boxes\Console;

use DB;
use Illuminate\Console\Command;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\Page;

/**
 * HealBoxesTreeCommand Command
 *
 * @link https://docs.octobercms.com/3.x/extend/console-commands.html
 */
class HealBoxesTreeCommand extends Command
{
    /**
     * @var string signature for the console command.
     */
    protected $signature = 'boxes:heal-tree';

    /**
     * @var string description is the console command description
     */
    protected $description = 'Fixes the nested pages structure in case the tree is broken.';

    /**
     * handle executes the console command.
     */
    public function handle()
    {
        if (!$this->confirm('Please backup your database before running this command. Proceed?')) {
            return;
        }

        $statusList = [PublishedState::DRAFT, PublishedState::PUBLISHED];

        foreach (Site::listSites() as $site) {
            $this->info("Healing tree for site {$site->locale}...");

            DB::transaction(function () use ($site, $statusList) {
                foreach ($statusList as $status) {
                    $pages = Page::withoutGlobalScopes()
                        ->where('site_id', $site->id)
                        ->where('published_state', $status)
                        ->where('theme', ThemeResolver::instance()->getThemeCode())
                        ->with('children')
                        ->get()
                        ->toNested(false);

                    $counter = 1;
                    $depth = 0;

                    if ($pages->count() === 0) {
                        $this->info(" -> Site has no {$status} pages. Skipping...");

                        continue;
                    }

                    $fn = function ($pages) use (&$fn, &$counter, &$depth) {
                        foreach ($pages as $page) {
                            DB::table('offline_boxes_pages')
                                ->where('id', $page->id)
                                ->update([
                                    'nest_depth' => $depth,
                                    'nest_left' => $counter++,
                                ]);

                            if ($page->children) {
                                $depth++;
                                $fn($page->children);
                                $depth--;
                            }

                            DB::table('offline_boxes_pages')
                                ->where('id', $page->id)
                                ->update([
                                    'nest_right' => $counter++,
                                ]);

                            $boxDepths = 0;
                            $boxCounter = 0;

                            $boxFn = function ($boxes) use (&$boxFn, &$boxCounter, &$boxDepths) {
                                foreach ($boxes as $box) {
                                    DB::table('offline_boxes_boxes')
                                        ->where('id', $box->id)
                                        ->update([
                                            'nest_depth' => $boxDepths,
                                            'nest_left' => $boxCounter++,
                                        ]);

                                    if ($box->children) {
                                        $boxDepths++;
                                        $boxFn($box->children);
                                        $boxDepths--;
                                    }

                                    DB::table('offline_boxes_boxes')
                                        ->where('id', $box->id)
                                        ->update([
                                            'nest_right' => $boxCounter++,
                                        ]);
                                }
                            };

                            if ($page->boxes) {
                                $boxFn($page->boxes->toNested(false));
                            }
                        }
                    };

                    $fn($pages);
                }

                $this->info(' -> Done.');
                $this->line('');
            });
        }
    }
}
