<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class MediaController extends Controller
{
    /**
     * Stream a media file with temporary URL
     */
    public function stream(Request $request, string $uuid)
    {
        // Find the media by UUID
        $media = Media::where('uuid', $uuid)->firstOrFail();
        
        // Check if user has access to this media
        $this->authorizeMediaAccess($media);
        
        // Generate a temporary URL that's cached for 10 minutes
        $cacheKey = "media_temp_url_{$media->id}";
        
        $tempUrl = Cache::remember($cacheKey, 600, function () use ($media) {
            return Storage::disk($media->disk)->temporaryUrl(
                $media->getPath(),
                now()->addMinutes(10)
            );
        });
        
        // Redirect to the temporary URL
        return redirect($tempUrl);
    }
    
    /**
     * Download a media file with proper headers
     */
    public function download(Request $request, string $uuid)
    {
        // Find the media by UUID
        $media = Media::where('uuid', $uuid)->firstOrFail();
        
        // Check if user has access to this media
        $this->authorizeMediaAccess($media);
        
        // Generate a temporary URL for download
        $cacheKey = "media_download_url_{$media->id}";
        
        $tempUrl = Cache::remember($cacheKey, 600, function () use ($media) {
            return Storage::disk($media->disk)->temporaryUrl(
                $media->getPath(),
                now()->addMinutes(10)
            );
        });
        
        // Redirect to the temporary URL with download headers
        return redirect($tempUrl)->header('Content-Disposition', 'attachment; filename="' . $media->file_name . '"');
    }
    
    /**
     * Authorize access to media file
     */
    private function authorizeMediaAccess(Media $media)
    {
        // Check if the media belongs to a customer
        if ($media->model_type === Customer::class) {
            $customer = Customer::find($media->model_id);
            
            if (!$customer) {
                abort(404, 'Customer not found');
            }
            
            // Check if the current user has access to this customer
            if (auth()->user()->team_id !== $customer->team_id) {
                abort(403, 'You do not have access to this media file');
            }
        }
        
        // Add more authorization logic for other model types if needed
    }
}
