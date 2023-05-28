<?php

namespace OFFLINE\Boxes\Classes\Transfer;

use Illuminate\Support\Collection;
use October\Rain\Database\Model;
use System\Models\File;

/**
 * Mapper maps data from an Eloquent model to an array.
 */
abstract class Mapper
{
    /**
     * Convert all attachments to an array.
     * @param Model $model
     * @return array
     */
    protected function attachmentsToArray(Model $model): array
    {
        $attachOne = array_keys($model->attachOne);
        $attachMany = array_keys($model->attachMany);

        $attachments = [];

        foreach ($attachOne as $relation) {
            $file = $model->$relation;

            if ($file instanceof File) {
                $attachments[$relation] = [array_filter([
                    'path' => $file->getLocalPath(),
                    'disk_name' => $file->disk_name,
                    'file_name' => $file->file_name,
                    'title' => $file->title,
                    'description' => $file->description,
                    'type' => 'attachOne',
                ])];
            }
        }

        foreach ($attachMany as $relation) {
            $files = $model->$relation;

            if ($files instanceof Collection && $files->count()) {
                $attachments[$relation] = $files->map(fn (File $file) => array_filter([
                    'path' => $file->getLocalPath(),
                    'disk_name' => $file->disk_name,
                    'file_name' => $file->file_name,
                    'title' => $file->title,
                    'description' => $file->description,
                    'type' => 'attachMany',
                ]))->toArray();
            }
        }

        return $attachments;
    }
}
