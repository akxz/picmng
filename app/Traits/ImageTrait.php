<?php

namespace App\Traits;

use DB;
use Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Imng;

trait ImageTrait
{
    /**
     * Задает пути и качество для конвертации изображения в WebP
     * @param  string $folder      Путь до папки с размерами
     * @param  string $size_folder Папка с обозначением размера
     * @param  string $name        Имя файла без расширения
     * @param  string $ext         Расширение файла
     * @param  int $quality     Качество (0-100)
     * @return void
     */
    private function mkWebp($folder, $size_folder, $name, $ext, $quality)
    {
        // Путь до исходного файла
        $source = $folder . $size_folder . $name . '.' . $ext;
        // Путь до webp файла
        $webp = $folder . $size_folder . $name . '.webp';

        $webpfile = $this->convertImageToWebP(
            storage_path() . '/app/' . $source,
            storage_path() . '/app/' . $webp,
            $quality
        );
    }

    /**
     * Конвертирует изображение в WebP формат
     * @param  string  $source      Путь к изображению-источнику
     * @param  string   $destination Путь к новому файлу
     * @param  integer $quality     Качество нового изображения (0-100)
     * @return bool
     */
    private function convertImageToWebP($source, $destination, $quality = 80)
    {
        $extension = pathinfo($source, PATHINFO_EXTENSION);
        if ($extension == 'jpeg' || $extension == 'jpg') {
            $image = imagecreatefromjpeg($source);
        } elseif ($extension == 'gif') {
            $image = imagecreatefromgif($source);
        } elseif ($extension == 'png') {
            $image = imagecreatefrompng($source);
        }

        return imagewebp($image, $destination, $quality);
    }

    /**
     * Изменение размера изображения (длины и ширины)
     * @param  array $data   Массив данных
     * @param  string $folder Путь к папке с размерами
     * @return void
     */
    private function mkSizes($data, $folder)
    {
        // Set the driver
        Imng::configure(array('driver' => 'gd'));
        // Путь до исходного файла
        $orig = $folder . '/orig/' . $data['name'] . '.' . $data['src_ext'];

        foreach ($data['sizes'] as $size) {
            $width = $size['width'];
            $height = $size['height'];
            // Новая папка с размером
            $size_folder = '/' . $width . 'x' . $height . '/';
            // Путь к новому файлу
            $destination = str_replace(
                '/orig/',
                $size_folder,
                $orig
            );

            // Выводим новый файл в поток
            $img = Imng::make(storage_path('app/' . $orig))
                ->resize($width, $height)
                ->stream('jpg', 100);

            // Сохраняем файл
            Storage::put($destination, $img);

            // Добавляем Webp версию файла, если надо
            if ($data['webp'] == 1) {
                $this->mkWebp(
                    $folder,
                    $size_folder,
                    $data['name'],
                    $data['src_ext'],
                    $data['quality']
                );
            }
        }
    }

    /**
     * Обработка или генерация имени файла
     * @param  string  $name   Имя файла
     * @param  integer $length Длина строки для генерации имени файла
     * @return string          Результат
     */
    private function mkFilename($name, $length = 10)
    {
        $name = trim($name);
        $name = str_replace([' ', '.'], '', $name);

        if ($name == '') {
            $name = Str::random($length);
        }

        return $name;
    }

    /**
     * Генерация пути из timestamp
     * @return string Созданный путь
     */
    private function mkPath()
    {
        // Переведем время в 10-значную строку
        $current = (string) time();
        $segments = [];

        // Разобъем строку времени на 2-значные сегменты
        // (пример: 15/87/98/94/22)
        for ($i = 0; $i < 10; $i++) {
            $segments[] = substr($current, $i, 2);
            $i++;
        }

        return implode('/', $segments);
    }

    /**
     * Проверка хеша при запросе на добавление фото
     * @param  array $data [description]
     * @return bool       [description]
     */
    private function checkHash($data)
    {
        // Нам нужен секретный ключ проекта
        $secret = DB::table('projects')
            ->where('id', $data['project'])
            ->first()->secret;

        if (is_null($secret)) {
            return false;
        }

        $str = $data['project'];
        $str .= count($data['images']);
        $str .= count($data['sizes']);
        $str .= $data['webp'] . $data['quality'] . $secret;

        return (bool) ($data['hash'] == sha1($str));
    }

    /**
     * Проверка хеша при удалении
     * @param  array $data [description]
     * @return bool       [description]
     */
    private function checkDelHash($data)
    {
        // Нам нужен секретный ключ проекта
        $secret = DB::table('projects')
            ->where('id', $data['project'])
            ->first()->secret;

        if (is_null($secret)) {
            return false;
        }

        $str = $data['project'];
        $str .= implode('.', $data['images']) . $secret;

        return (bool) ($data['hash'] == sha1($str));
    }
}
