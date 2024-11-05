<?php

namespace App\Http\Controllers;

use App\Http\Resources\UgcMediaCollection;
use App\Models\App;
use App\Models\TaxonomyWhere;
use App\Models\UgcMedia;
use App\Models\User;
use App\Traits\UGCFeatureCollectionTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UgcMediaController extends Controller
{
    use UGCFeatureCollectionTrait;
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();
        if (isset($user)) {

            if (!empty($request->header('app-id'))) {
                $reqAppId = $request->header('app-id');
                $appId = 'geohub_' . $reqAppId;
                $medias = UgcMedia::where([['user_id', $user->id], ['app_id', $appId]])->orderByRaw('updated_at DESC')->get();
                return $this->getUGCFeatureCollection($medias);
            }

            $medias = UgcMedia::where('user_id', $user->id)->orderByRaw('updated_at DESC')->get();
            return $this->getUGCFeatureCollection($medias);
        } else {
            return new UgcMediaCollection(UgcMedia::currentUser()->paginate(10));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'geojson' => 'required',
            'image' => 'required'
        ]);

        if ($validator->fails())
            return response(['error' => $validator->errors()], 400);

        $geojson = json_decode($data['geojson'], true);

        $validator = Validator::make($geojson, [
            'type' => 'required',
            'properties' => 'required|array',
            'properties.app_id' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            $currentErrors = json_decode($validator->errors(), true);
            $errors = [];
            foreach ($currentErrors as $key => $error) {
                $errors['geojson.' . $key] = $error;
            }

            return response(['error' => $errors], 400);
        }

        $user = auth('api')->user();

        $media = new UgcMedia();
        $media->name = $geojson['properties']['name'] ?? 'placeholder_name';
        if (isset($geojson['properties']['description']))
            $media->description = $geojson['properties']['description'];
        $media->user_id = $user->id;
        $media->relative_url = '';

        if (isset($geojson['geometry']))
            $media->geometry = DB::raw("ST_GeomFromGeojson('" . json_encode($geojson['geometry']) . "')");

        if (isset($geojson['properties']['app_id'])) {
            $appId = 'geohub_' . $geojson['properties']['app_id'];
            if ($appId)
                $media->app_id = $appId;
        }

        unset($geojson['properties']['name']);
        unset($geojson['properties']['description']);
        unset($geojson['properties']['app_id']);
        $media->raw_data = empty($geojson['properties']) ? null : $geojson['properties'];
        $media->save();

        try {
            $id = $media->id;
            $imageName = "image_$id";
            $basePath = "ugc-media/";
            if (Storage::disk('public')->exists("$basePath$imageName"))
                Storage::disk('public')->delete("$basePath$imageName");
            Storage::disk('public')->put("$basePath$imageName", $data['image']);

            $savedPath = Storage::disk('public')->files("$basePath$imageName/")[0];
            $split = explode('/', $savedPath);
            $savedName = end($split);

            $split = explode('.', $savedName);
            $ext = end($split);
            Storage::disk('public')->delete("$basePath$imageName.$ext");
            Storage::disk('public')->move("{$basePath}image_$id/$savedName", "$basePath$imageName.$ext");
            Storage::disk('public')->deleteDirectory("$basePath$imageName");
            $media->relative_url = $basePath . $imageName . '.' . $ext;

            if ($media->name == 'placeholder_name')
                $media->name = $imageName;
            if (empty($media->description))
                $media->description = $imageName;
            $media->save();
        } catch (Exception $e) {
            Log::error($e);

            return response(['message' => 'An error occurred while creating the new image'], 500);
        }

        return response(['id' => $media->id, 'message' => 'Created successfully'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param UgcMedia $ugcMedia
     *
     * @return Response
     */
    public function show(UgcMedia $ugcMedia)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param UgcMedia $ugcMedia
     *
     * @return Response
     */
    public function edit(UgcMedia $ugcMedia)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param UgcMedia $ugcMedia
     *
     * @return Response
     */
    public function destroy($id)
    {
        try {
            $media = UgcMedia::find($id);

            if (!$media) {
                return response()->json([
                    'error' => 'Media not found',
                    'code' => 404
                ], 404);
            }

            // Delete the file if it exists
            if ($media->relative_url && Storage::disk('public')->exists($media->relative_url)) {
                Storage::disk('public')->delete($media->relative_url);
            }
            $media->delete();

            return response()->json(['success' => 'media deleted']);
        } catch (Exception $e) {
            return response()->json([
                'error' => "this media can't be deleted by api",
                'code' => 400
            ], 400);
        }
    }

    public function downloadUserGeojson($user_id)
    {
        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="MyUgcMedia.geojson"',
        ];

        $user = User::find($user_id);
        if ($user == null) {
            $geojson = ["error" => "User Not Found"];
        } else if (count($user->ugc_medias) == 0) {
            $geojson = ["error" => "User has no Media"];
        } else {
            $geojson = ['type' => 'FeatureCollection'];
            $geojson['features'] = [];
            foreach ($user->ugc_medias as $media) {
                $feature = ['type' => 'Feature'];
                $feature['properties']['id'] = $media->id;
                $feature['properties']['name'] = $media->name;
                $feature['properties']['url'] = env('APP_URL') . Storage::url($media->relative_url);
                $emptygeojson = $media->getEmptyGeojson();
                $feature['geometry'] = $emptygeojson['geometry'];
                $geojson['features'][] = $feature;
            }
        }

        return response(json_encode($geojson), 200, $headers);
    }

    protected function mime2ext($mime)
    {
        $mime_map = [
            'video/3gpp2' => '3g2',
            'video/3gp' => '3gp',
            'video/3gpp' => '3gp',
            'application/x-compressed' => '7zip',
            'audio/x-acc' => 'aac',
            'audio/ac3' => 'ac3',
            'application/postscript' => 'ai',
            'audio/x-aiff' => 'aif',
            'audio/aiff' => 'aif',
            'audio/x-au' => 'au',
            'video/x-msvideo' => 'avi',
            'video/msvideo' => 'avi',
            'video/avi' => 'avi',
            'application/x-troff-msvideo' => 'avi',
            'application/macbinary' => 'bin',
            'application/mac-binary' => 'bin',
            'application/x-binary' => 'bin',
            'application/x-macbinary' => 'bin',
            'image/bmp' => 'bmp',
            'image/x-bmp' => 'bmp',
            'image/x-bitmap' => 'bmp',
            'image/x-xbitmap' => 'bmp',
            'image/x-win-bitmap' => 'bmp',
            'image/x-windows-bmp' => 'bmp',
            'image/ms-bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'application/bmp' => 'bmp',
            'application/x-bmp' => 'bmp',
            'application/x-win-bitmap' => 'bmp',
            'application/cdr' => 'cdr',
            'application/coreldraw' => 'cdr',
            'application/x-cdr' => 'cdr',
            'application/x-coreldraw' => 'cdr',
            'image/cdr' => 'cdr',
            'image/x-cdr' => 'cdr',
            'zz-application/zz-winassoc-cdr' => 'cdr',
            'application/mac-compactpro' => 'cpt',
            'application/pkix-crl' => 'crl',
            'application/pkcs-crl' => 'crl',
            'application/x-x509-ca-cert' => 'crt',
            'application/pkix-cert' => 'crt',
            'text/css' => 'css',
            'text/x-comma-separated-values' => 'csv',
            'text/comma-separated-values' => 'csv',
            'application/vnd.msexcel' => 'csv',
            'application/x-director' => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/x-dvi' => 'dvi',
            'message/rfc822' => 'eml',
            'application/x-msdownload' => 'exe',
            'video/x-f4v' => 'f4v',
            'audio/x-flac' => 'flac',
            'video/x-flv' => 'flv',
            'image/gif' => 'gif',
            'application/gpg-keys' => 'gpg',
            'application/x-gtar' => 'gtar',
            'application/x-gzip' => 'gzip',
            'application/mac-binhex40' => 'hqx',
            'application/mac-binhex' => 'hqx',
            'application/x-binhex40' => 'hqx',
            'application/x-mac-binhex40' => 'hqx',
            'text/html' => 'html',
            'image/x-icon' => 'ico',
            'image/x-ico' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'text/calendar' => 'ics',
            'application/java-archive' => 'jar',
            'application/x-java-application' => 'jar',
            'application/x-jar' => 'jar',
            'image/jp2' => 'jp2',
            'video/mj2' => 'jp2',
            'image/jpx' => 'jp2',
            'image/jpm' => 'jp2',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpeg',
            'application/x-javascript' => 'js',
            'application/json' => 'json',
            'text/json' => 'json',
            'application/vnd.google-earth.kml+xml' => 'kml',
            'application/vnd.google-earth.kmz' => 'kmz',
            'text/x-log' => 'log',
            'audio/x-m4a' => 'm4a',
            'application/vnd.mpegurl' => 'm4u',
            'audio/midi' => 'mid',
            'application/vnd.mif' => 'mif',
            'video/quicktime' => 'mov',
            'video/x-sgi-movie' => 'movie',
            'audio/mpeg' => 'mp3',
            'audio/mpg' => 'mp3',
            'audio/mpeg3' => 'mp3',
            'audio/mp3' => 'mp3',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'application/oda' => 'oda',
            'audio/ogg' => 'ogg',
            'video/ogg' => 'ogg',
            'application/ogg' => 'ogg',
            'application/x-pkcs10' => 'p10',
            'application/pkcs10' => 'p10',
            'application/x-pkcs12' => 'p12',
            'application/x-pkcs7-signature' => 'p7a',
            'application/pkcs7-mime' => 'p7c',
            'application/x-pkcs7-mime' => 'p7c',
            'application/x-pkcs7-certreqresp' => 'p7r',
            'application/pkcs7-signature' => 'p7s',
            'application/pdf' => 'pdf',
            'application/octet-stream' => 'pdf',
            'application/x-x509-user-cert' => 'pem',
            'application/x-pem-file' => 'pem',
            'application/pgp' => 'pgp',
            'application/x-httpd-php' => 'php',
            'application/php' => 'php',
            'application/x-php' => 'php',
            'text/php' => 'php',
            'text/x-php' => 'php',
            'application/x-httpd-php-source' => 'php',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'application/powerpoint' => 'ppt',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.ms-office' => 'ppt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop' => 'psd',
            'image/vnd.adobe.photoshop' => 'psd',
            'audio/x-realaudio' => 'ra',
            'audio/x-pn-realaudio' => 'ram',
            'application/x-rar' => 'rar',
            'application/rar' => 'rar',
            'application/x-rar-compressed' => 'rar',
            'audio/x-pn-realaudio-plugin' => 'rpm',
            'application/x-pkcs7' => 'rsa',
            'text/rtf' => 'rtf',
            'text/richtext' => 'rtx',
            'video/vnd.rn-realvideo' => 'rv',
            'application/x-stuffit' => 'sit',
            'application/smil' => 'smil',
            'text/srt' => 'srt',
            'image/svg+xml' => 'svg',
            'application/x-shockwave-flash' => 'swf',
            'application/x-tar' => 'tar',
            'application/x-gzip-compressed' => 'tgz',
            'image/tiff' => 'tiff',
            'text/plain' => 'txt',
            'text/x-vcard' => 'vcf',
            'application/videolan' => 'vlc',
            'text/vtt' => 'vtt',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/wav' => 'wav',
            'application/wbxml' => 'wbxml',
            'video/webm' => 'webm',
            'audio/x-ms-wma' => 'wma',
            'application/wmlc' => 'wmlc',
            'video/x-ms-wmv' => 'wmv',
            'video/x-ms-asf' => 'wmv',
            'application/xhtml+xml' => 'xhtml',
            'application/excel' => 'xl',
            'application/msexcel' => 'xls',
            'application/x-msexcel' => 'xls',
            'application/x-ms-excel' => 'xls',
            'application/x-excel' => 'xls',
            'application/x-dos_ms_excel' => 'xls',
            'application/xls' => 'xls',
            'application/x-xls' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xlsx',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'text/xsl' => 'xsl',
            'application/xspf+xml' => 'xspf',
            'application/x-compress' => 'z',
            'application/x-zip' => 'zip',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/s-compressed' => 'zip',
            'multipart/x-zip' => 'zip',
            'text/x-scriptzsh' => 'zsh',
        ];

        return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
    }

    protected function ext2mime($ext)
    {
        $mime_map = [
            'video/3gpp2' => '3g2',
            'video/3gp' => '3gp',
            'video/3gpp' => '3gp',
            'application/x-compressed' => '7zip',
            'audio/x-acc' => 'aac',
            'audio/ac3' => 'ac3',
            'application/postscript' => 'ai',
            'audio/x-aiff' => 'aif',
            'audio/aiff' => 'aif',
            'audio/x-au' => 'au',
            'video/x-msvideo' => 'avi',
            'video/msvideo' => 'avi',
            'video/avi' => 'avi',
            'application/x-troff-msvideo' => 'avi',
            'application/macbinary' => 'bin',
            'application/mac-binary' => 'bin',
            'application/x-binary' => 'bin',
            'application/x-macbinary' => 'bin',
            'image/bmp' => 'bmp',
            'image/x-bmp' => 'bmp',
            'image/x-bitmap' => 'bmp',
            'image/x-xbitmap' => 'bmp',
            'image/x-win-bitmap' => 'bmp',
            'image/x-windows-bmp' => 'bmp',
            'image/ms-bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'application/bmp' => 'bmp',
            'application/x-bmp' => 'bmp',
            'application/x-win-bitmap' => 'bmp',
            'application/cdr' => 'cdr',
            'application/coreldraw' => 'cdr',
            'application/x-cdr' => 'cdr',
            'application/x-coreldraw' => 'cdr',
            'image/cdr' => 'cdr',
            'image/x-cdr' => 'cdr',
            'zz-application/zz-winassoc-cdr' => 'cdr',
            'application/mac-compactpro' => 'cpt',
            'application/pkix-crl' => 'crl',
            'application/pkcs-crl' => 'crl',
            'application/x-x509-ca-cert' => 'crt',
            'application/pkix-cert' => 'crt',
            'text/css' => 'css',
            'text/x-comma-separated-values' => 'csv',
            'text/comma-separated-values' => 'csv',
            'application/vnd.msexcel' => 'csv',
            'application/x-director' => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/x-dvi' => 'dvi',
            'message/rfc822' => 'eml',
            'application/x-msdownload' => 'exe',
            'video/x-f4v' => 'f4v',
            'audio/x-flac' => 'flac',
            'video/x-flv' => 'flv',
            'image/gif' => 'gif',
            'application/gpg-keys' => 'gpg',
            'application/x-gtar' => 'gtar',
            'application/x-gzip' => 'gzip',
            'application/mac-binhex40' => 'hqx',
            'application/mac-binhex' => 'hqx',
            'application/x-binhex40' => 'hqx',
            'application/x-mac-binhex40' => 'hqx',
            'text/html' => 'html',
            'image/x-icon' => 'ico',
            'image/x-ico' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'text/calendar' => 'ics',
            'application/java-archive' => 'jar',
            'application/x-java-application' => 'jar',
            'application/x-jar' => 'jar',
            'image/jp2' => 'jp2',
            'video/mj2' => 'jp2',
            'image/jpx' => 'jp2',
            'image/jpm' => 'jp2',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpeg',
            'application/x-javascript' => 'js',
            'application/json' => 'json',
            'text/json' => 'json',
            'application/vnd.google-earth.kml+xml' => 'kml',
            'application/vnd.google-earth.kmz' => 'kmz',
            'text/x-log' => 'log',
            'audio/x-m4a' => 'm4a',
            'application/vnd.mpegurl' => 'm4u',
            'audio/midi' => 'mid',
            'application/vnd.mif' => 'mif',
            'video/quicktime' => 'mov',
            'video/x-sgi-movie' => 'movie',
            'audio/mpeg' => 'mp3',
            'audio/mpg' => 'mp3',
            'audio/mpeg3' => 'mp3',
            'audio/mp3' => 'mp3',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'application/oda' => 'oda',
            'audio/ogg' => 'ogg',
            'video/ogg' => 'ogg',
            'application/ogg' => 'ogg',
            'application/x-pkcs10' => 'p10',
            'application/pkcs10' => 'p10',
            'application/x-pkcs12' => 'p12',
            'application/x-pkcs7-signature' => 'p7a',
            'application/pkcs7-mime' => 'p7c',
            'application/x-pkcs7-mime' => 'p7c',
            'application/x-pkcs7-certreqresp' => 'p7r',
            'application/pkcs7-signature' => 'p7s',
            'application/pdf' => 'pdf',
            'application/octet-stream' => 'pdf',
            'application/x-x509-user-cert' => 'pem',
            'application/x-pem-file' => 'pem',
            'application/pgp' => 'pgp',
            'application/x-httpd-php' => 'php',
            'application/php' => 'php',
            'application/x-php' => 'php',
            'text/php' => 'php',
            'text/x-php' => 'php',
            'application/x-httpd-php-source' => 'php',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'application/powerpoint' => 'ppt',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.ms-office' => 'ppt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop' => 'psd',
            'image/vnd.adobe.photoshop' => 'psd',
            'audio/x-realaudio' => 'ra',
            'audio/x-pn-realaudio' => 'ram',
            'application/x-rar' => 'rar',
            'application/rar' => 'rar',
            'application/x-rar-compressed' => 'rar',
            'audio/x-pn-realaudio-plugin' => 'rpm',
            'application/x-pkcs7' => 'rsa',
            'text/rtf' => 'rtf',
            'text/richtext' => 'rtx',
            'video/vnd.rn-realvideo' => 'rv',
            'application/x-stuffit' => 'sit',
            'application/smil' => 'smil',
            'text/srt' => 'srt',
            'image/svg+xml' => 'svg',
            'application/x-shockwave-flash' => 'swf',
            'application/x-tar' => 'tar',
            'application/x-gzip-compressed' => 'tgz',
            'image/tiff' => 'tiff',
            'text/plain' => 'txt',
            'text/x-vcard' => 'vcf',
            'application/videolan' => 'vlc',
            'text/vtt' => 'vtt',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/wav' => 'wav',
            'application/wbxml' => 'wbxml',
            'video/webm' => 'webm',
            'audio/x-ms-wma' => 'wma',
            'application/wmlc' => 'wmlc',
            'video/x-ms-wmv' => 'wmv',
            'video/x-ms-asf' => 'wmv',
            'application/xhtml+xml' => 'xhtml',
            'application/excel' => 'xl',
            'application/msexcel' => 'xls',
            'application/x-msexcel' => 'xls',
            'application/x-ms-excel' => 'xls',
            'application/x-excel' => 'xls',
            'application/x-dos_ms_excel' => 'xls',
            'application/xls' => 'xls',
            'application/x-xls' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xlsx',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'text/xsl' => 'xsl',
            'application/xspf+xml' => 'xspf',
            'application/x-compress' => 'z',
            'application/x-zip' => 'zip',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/s-compressed' => 'zip',
            'multipart/x-zip' => 'zip',
            'text/x-scriptzsh' => 'zsh',
        ];

        return array_search($ext, $mime_map);
    }

    public function download($id)
    {
        $media = UgcMedia::find($id);

        if (!$media) {
            return response()->json([
                "error" => "UGC Media $id not found"
            ], 404);
        }

        if (!Storage::disk('public')->exists($media->relative_url)) {
            return response()->json([
                "error" => "UGC Media image not found"
            ], 404);
        }
        $filename = $media->name;
        $split = explode('.', $filename);
        $extension = end($split);
        $mime = $this->ext2mime($extension);
        $headers = [
            'Content-type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response(readfile(Storage::disk('public')->path($media->relative_url)), 200, $headers);
    }

    /**
     * Update an ugc media, setting coordinates and taxonomy where if available
     * 
     * @param Request $request the request
     * @param int $id the ugc media id
     */
    public function update(Request $request, int $id)
    {
        $media = UgcMedia::find($id);

        if (!$media) {
            return response()->json([
                "error" => "UGC Media $id not found"
            ], 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'geojson' => 'required',
            'geojson.type' => 'required'
        ]);

        if ($validator->fails())
            return response(['error' => $validator->errors()], 400);

        $geojson = $data['geojson'];

        if (isset($geojson['geometry']['type']) && $geojson['geometry']['type'] === 'Point' && isset($geojson['geometry']['coordinates'])) {
            $media->geometry = DB::raw("ST_GeomFromGeojson('" . json_encode($geojson['geometry']) . "')");
            $media->save();
        }

        // if (isset($geojson['properties']['where_ids']) && is_array($geojson['properties']['where_ids'])) {
        //     $whereIds = $geojson['properties']['where_ids'];
        //     $ids = [];
        //     foreach ($whereIds as $id) {
        //         $where = TaxonomyWhere::find($id);
        //         if (!is_null($where)) {
        //             $ids[] = $id;
        //         }
        //     }
        //     $media->taxonomy_wheres()->sync($ids);
        // }


        return response()->noContent();
    }
}
