<?php

namespace AhmedElsied\FilamentMediaBrowser\Traits;

use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Spatie media plus pool usages: getFirstMedia() / getMedia() prefer media_attachments.
 */
trait InteractsWithPoolMedia
{
    use HasMediaAttachments, InteractsWithMedia {
        HasMediaAttachments::getFirstMedia insteadof InteractsWithMedia;
        HasMediaAttachments::getMedia insteadof InteractsWithMedia;
        HasMediaAttachments::clearMediaCollection insteadof InteractsWithMedia;
        InteractsWithMedia::getFirstMedia as protected getFirstMediaFromSpatieCollection;
        InteractsWithMedia::getMedia as protected getMediaFromSpatieCollection;
        InteractsWithMedia::clearMediaCollection as protected clearSpatieMediaCollection;
    }
}
