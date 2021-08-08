<?php

namespace App\Models\Translations;

use A17\Twill\Models\Model;
use App\Models\Post;

class PostTranslation extends Model
{
    protected $baseModuleModel = Post::class;
}
