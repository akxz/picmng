<?php

namespace App\Jobs;

use Storage;
use App\Image;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $images = Image::select('image_id', 'url')
            ->where('project_id', $data['project'])
            ->whereIn('image_id', $data['images'])
            ->groupBy(['image_id', 'url'])
            ->get();

        if (empty($images)) {
            return;
        }

        foreach ($images as $image) {
            // Выбираем из URL путь к папке
            $slice = Str::between(
                $image['url'],
                ('/p' . $data['project'] . '/'),
                ('/' . $image['image_id'] . '/')
            );

            // Составляем путь для удаления
            $directory = 'images/p' . $data['project'] . '/' . $slice . '/' . $image['image_id'];

            // Удаляем папку рекурсивно
            Storage::disk('public')->deleteDirectory($directory);

            // Обрезаем путь на 1 сегмент (получаем надиректорию)
            $directory = Str::beforeLast($directory, '/');

            // Удаляем пустые наддиректории
            while (empty(Storage::allFiles($directory))) {
                if (strpos($directory, '/') === false) {
                    break;
                }

                Storage::disk('public')->deleteDirectory($directory);
                // Снова обрезаем путь на 1 сегмент
                $directory = Str::beforeLast($directory, '/');
                // Log::debug($directory);
            }
        }

        Image::where('project_id', $data['project'])
            ->whereIn('image_id', $data['images'])
            ->delete();
    }
}
