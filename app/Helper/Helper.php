<?php

namespace App\Helper;

use Illuminate\Support\Facades\Storage;

class Helper
{
    public static function upload($request, $key, $path)
    {
        if ($request->hasfile($key)) {
            $file = $request->file($key);
            $_file = "C&F_Client_VAT_REG_" . md5($file->getClientOriginalName() . time()) . "." . $file->getClientOriginalExtension();
            Storage::disk('public')->put($path . $_file, file_get_contents($file));
        }

        return $_file ?? '';
    }


    public static function uploadInPublic($request, $key, $path)
    {
        $_file = "";
        if ($request->hasfile($key)) {
            $file = $request->file($key);
            $_file = "C&F_Company_" . md5($file->getClientOriginalName() . time()) . "." . $file->getClientOriginalExtension();
            Storage::disk('public')->put($path . $_file, file_get_contents($file));
        }
        if(empty($_file) && gettype($request->input($key)) == "string")
        {
            $parts = parse_url($request->input($key));
            $_file = basename($parts["path"]);
        }

        return $_file ?? '';
    }

    public static function uploadImport($request, $key, $path)
    {
        if ($request->hasfile($key)) {
            $file = $request->file($key);
            $_file = "C&F_Company_" . md5($file->getClientOriginalName() . time()) . "." . $file->getClientOriginalExtension();
            Storage::disk('public')->put($path . $_file, file_get_contents($file));
        }

        return $_file ?? '';
    }


}
