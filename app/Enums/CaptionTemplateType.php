<?php

namespace App\Enums;

enum CaptionTemplateType: string
{
    /** Reusable caption text, such as a call to action or a standard description. */
    case Caption = 'caption';

    /** A saved set of hashtags, appended to the end of a caption. */
    case Hashtags = 'hashtags';
}
