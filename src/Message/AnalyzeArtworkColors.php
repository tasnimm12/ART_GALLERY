<?php

namespace App\Message;

final class AnalyzeArtworkColors
{
    public function __construct(public readonly int $artworkId)
    {
    }
}
