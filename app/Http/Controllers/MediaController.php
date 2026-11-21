<?php

namespace App\Http\Controllers;

class MediaController extends Controller
{
    public function show(string $name)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9-]+\.jpg$/D', $name), 404);
        $path = storage_path('app/private/uploads/'.$name);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
