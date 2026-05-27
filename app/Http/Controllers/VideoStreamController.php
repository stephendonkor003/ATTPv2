<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    private const CHUNK = 1048576; // 1 MB per read

    private const MIME = [
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogg'  => 'video/ogg',
        'mov'  => 'video/quicktime',
    ];

    public function stream(Request $request, string $filename): StreamedResponse
    {
        // Prevent directory traversal
        $filename = basename($filename);
        $path     = public_path('gallary/' . $filename);

        abort_unless(file_exists($path), 404);

        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? null;
        abort_unless($mime, 415);

        $size = filesize($path);
        abort_unless($size !== false && $size > 0, 404);

        $start = 0;
        $end = $size - 1;
        $status = 200;

        if ($range = $request->header('Range')) {
            abort_unless(preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches), 416);

            $rangeStart = $matches[1];
            $rangeEnd = $matches[2];

            if ($rangeStart === '' && $rangeEnd === '') {
                abort(416);
            }

            if ($rangeStart === '') {
                $suffixLength = (int) $rangeEnd;
                abort_unless($suffixLength > 0, 416);
                $start = max(0, $size - $suffixLength);
            } else {
                $start = (int) $rangeStart;
                $end = $rangeEnd !== '' ? (int) $rangeEnd : $end;
            }

            abort_unless($start < $size && $start <= $end, 416);

            $end = min($end, $size - 1);
            $status = 206;
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type'           => $mime,
            'Accept-Ranges'          => 'bytes',
            'Content-Length'         => (string) $length,
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(
            function () use ($path, $start, $length) {
                $fh = fopen($path, 'rb');
                if ($fh === false) {
                    return;
                }

                $remaining = $length;
                fseek($fh, $start);

                while ($remaining > 0 && !feof($fh)) {
                    $read = (int) min(self::CHUNK, $remaining);
                    $data = fread($fh, $read);

                    if ($data === false || $data === '') {
                        break;
                    }

                    $remaining -= strlen($data);
                    echo $data;

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }

                fclose($fh);
            },
            $status,
            $headers
        );
    }
}
