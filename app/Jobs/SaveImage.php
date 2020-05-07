<?php

namespace App\Jobs;

use Storage;
use App\Traits\ImageTrait;
use Illuminate\Bus\Queueable;
// use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SaveImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ImageTrait;

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
        $contents = file_get_contents($data['image']['url']);

        // Папка, в которой будут храниться версии файла
        $folder = 'public/images/' . $data['folder'];
        // Путь до оригинального файла
        $orig = $folder . '/orig/' . $data['name'] . '.' . $data['src_ext'];

        // Сохраняем оригинал
        Storage::put($orig, $contents);

        // Добавляем Webp версию файла, если надо
        if ($data['webp'] == 1) {
            $this->mkWebp(
                $folder,
                '/orig/',
                $data['name'],
                $data['src_ext'],
                $data['quality']
            );
        }

        // Создаем из оригинала нужные нам размеры и форматы
        if (! empty($data['sizes'])) {
            $this->mkSizes($data, $folder);
        }
    }
}
