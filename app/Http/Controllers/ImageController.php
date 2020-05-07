<?php

namespace App\Http\Controllers;

use App\Image;
use App\Jobs\SaveImage;
use App\Jobs\DeleteImages;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    use ImageTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string json
     */
    public function store(Request $request)
    {
        $input = $request->all();
        if (empty($input['images'])) {
            return json_encode(['error' => 'No images in request']);
        }

        $data = [
            'project' => $input['project'],
            'images' => $input['images'],
            'hash' => $input['hash'],
            'sizes' => ((isset($input['sizes'])) ? $input['sizes'] : []),
            'webp' => (int) ((isset($input['webp'])) ? $input['webp'] : 0),
            'quality' => (int) ((isset($input['quality'])) ? $input['quality'] : 70),
        ];

        // Проверка хеша
        if (! $this->checkHash($data)) {
            return json_encode(['error' => 'Hash error']);
        }

        // Путь к папке, в которой будем хранить фото
        $path = 'p' . $input['project'] . '/' . $this->mkPath();

        // Массив для ответа (вывод ссылок на фото)
        $out = [];

        // Массив для вставки инфы в БД
        // Нужно для возможности удаления фото
        $insert = [];

        foreach ($input['images'] as $image) {
            if ((! isset($image['uid'])) || (! isset($image['url']))) {
                continue;
            }

            // Имя нового файла из запроса
            $name = (isset($image['name'])) ? $image['name'] : '';
            $data['name'] = $this->mkFilename($name);
            // Расширение исходного фото
            $data['src_ext'] = substr($image['url'], strrpos($image['url'], '.') + 1);
            $data['folder'] = $path . '/' . $image['uid'];

            // Добавляем путь к новому фото в ответ
            // Составляем массив из частей нового URL
            $img_url_parts = [
                config('app.url'),
                'storage/images',
                $data['folder'],
                'orig',
                $data['name'] . '.' . $data['src_ext']
            ];

            $out[$image['uid']] = implode('/', $img_url_parts);

            $insert = array_merge(
                $insert,
                [
                    [
                        'project_id' => $data['project'],
                        'image_id' => $image['uid'],
                        'url' => implode('/', $img_url_parts),
                    ]
                ]
            );

            $data['image'] = $image;
            SaveImage::dispatch($data);
        }

        if (! empty($insert)) {
            Image::insert($insert);
        }

        $out = json_encode($out);
        // Log::debug('input: ' . $out);
        return $out;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Image  $image
     * @return \Illuminate\Http\Response
     */
    public function show(Image $image)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Image  $image
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $input = $request->all();

        // Проверка наличия ID изображений в запросе
        if ((! isset($input['images'])) || (empty($input['images']))) {
            return json_encode(['error' => 'No images in request']);
        }

        // Проверка хеша
        if (! $this->checkDelHash($input)) {
            return json_encode(['error' => 'Hash error']);
        }

        DeleteImages::dispatch($input);

        return json_encode(['status' => 'Deleting']);
    }
}
