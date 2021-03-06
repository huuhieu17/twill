<?php

namespace A17\Twill\Models\Behaviors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasSlug
{
    private $nb_variation_slug = 3;

    protected static function bootHasSlug()
    {
        static::created(function ($model) {
            $model->setSlugs();
        });

        static::updated(function ($model) {
            $model->setSlugs();
        });

        static::restored(function ($model) {
            $model->setSlugs($restoring = true);
        });
    }

    public function slugs()
    {
        return $this->hasMany($this->getSlugModelClass());
    }

    public function getSlugClass()
    {
        return new $this->getSlugModelClass();
    }

    public function getSlugModelClass()
    {
        $slug = $this->getNamespace() . "\Slugs\\" . $this->getSlugClassName();

        if (@class_exists($slug)) {
            return $slug;
        }

        return $this->getCapsuleSlugClass(class_basename($this));
    }

    protected function getSlugClassName()
    {
        return class_basename($this) . "Slug";
    }

    public function scopeForSlug($query, $slug)
    {
        return $query->whereHas('slugs', function ($query) use ($slug) {
            $query->whereSlug($slug);
            $query->whereActive(true);
            $query->whereLocale(app()->getLocale());
        })->with(['slugs']);
    }

    public function scopeForInactiveSlug($query, $slug)
    {
        return $query->whereHas('slugs', function ($query) use ($slug) {
            $query->whereSlug($slug);
            $query->whereLocale(app()->getLocale());
        })->with(['slugs']);
    }

    public function scopeForFallbackLocaleSlug($query, $slug)
    {
        return $query->whereHas('slugs', function ($query) use ($slug) {
            $query->whereSlug($slug);
            $query->whereActive(true);
            $query->whereLocale(config('translatable.fallback_locale'));
        })->with(['slugs']);
    }

    public function setSlugs($restoring = false)
    {
        foreach ($this->getSlugParams() as $slugParams) {
            $this->updateOrNewSlug($slugParams, $restoring);
        }
    }

    public function updateOrNewSlug($slugParams, $restoring = false)
    {
        if (in_array($slugParams['locale'], config('twill.slug_utf8_languages', []))) {
            $slugParams['slug'] = $this->getUtf8Slug($slugParams['slug']);
        } else {
            $slugParams['slug'] = Str::slug($slugParams['slug']);
        }

        //active old slug if already existing or create a new one
        if ((($oldSlug = $this->getExistingSlug($slugParams)) != null)
            && ($restoring ? $slugParams['slug'] === $this->suffixSlugIfExisting($slugParams) : true)) {
            if (!$oldSlug->active && ($slugParams['active'] ?? false)) {
                DB::table($this->getSlugsTable())->where('id', $oldSlug->id)->update(['active' => 1]);
                $this->disableLocaleSlugs($oldSlug->locale, $oldSlug->id);
            }
        } else {

            $this->addOneSlug($slugParams);
        }
    }

    public function getExistingSlug($slugParams)
    {
        $query = DB::table($this->getSlugsTable())->where($this->getForeignKey(), $this->id);
        unset($slugParams['active']);

        foreach ($slugParams as $key => $value) {
            //check variations of the slug
            if ($key == 'slug') {
                $query->where(function ($query) use ($value) {
                    $query->orWhere('slug', $value);
                    $query->orWhere('slug', $value . '-' . $this->getSuffixSlug());
                    for ($i = 2; $i <= $this->nb_variation_slug; $i++) {
                        $query->orWhere('slug', $value . '-' . $i);
                    }
                });
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    protected function addOneSlug($slugParams)
    {
        $datas = [];
        foreach ($slugParams as $key => $value) {
            $datas[$key] = $value;
        }

        $datas['slug'] = $this->suffixSlugIfExisting($slugParams);

        $datas[$this->getForeignKey()] = $this->id;

        $id = DB::table($this->getSlugsTable())->insertGetId($datas);

        $this->disableLocaleSlugs($slugParams['locale'], $id);
    }

    public function disableLocaleSlugs($locale, $except_slug_id = 0)
    {
        DB::table($this->getSlugsTable())
            ->where($this->getForeignKey(), $this->id)
            ->where('id', '<>', $except_slug_id)
            ->where('locale', $locale)
            ->update(['active' => 0])
        ;
    }

    private function suffixSlugIfExisting($slugParams)
    {
        $slugBackup = $slugParams['slug'];
        $table = $this->getSlugsTable();

        unset($slugParams['active']);

        for ($i = 2; $i <= $this->nb_variation_slug + 1; $i++) {
            $qCheck = DB::table($table);
            $qCheck->whereNull($this->getDeletedAtColumn());
            foreach ($slugParams as $key => $value) {
                $qCheck->where($key, '=', $value);
            }

            if ($qCheck->first() == null) {
                break;
            }

            if (!empty($slugParams['slug'])) {
                $slugParams['slug'] = $slugBackup . (($i > $this->nb_variation_slug) ? "-" . $this->getSuffixSlug() : "-{$i}");
            }
        }

        return $slugParams['slug'];
    }

    public function getActiveSlug($locale = null)
    {
        return $this->slugs->first(function ($slug) use ($locale) {
            return ($slug->locale === ($locale ?? app()->getLocale())) && $slug->active;
        }) ?? null;
    }

    public function getFallbackActiveSlug()
    {
        return $this->slugs->first(function ($slug) {
            return $slug->locale === config('translatable.fallback_locale') && $slug->active;
        }) ?? null;
    }

    public function getSlug($locale = null)
    {
        if (($slug = $this->getActiveSlug($locale)) != null) {
            return $slug->slug;
        }

        if (config('translatable.use_property_fallback', false) && (($slug = $this->getFallbackActiveSlug()) != null)) {
            return $slug->slug;
        }

        return "";
    }

    public function getSlugAttribute()
    {
        return $this->getSlug();
    }

    public function getSlugParams($locale = null)
    {
        if (count(getLocales()) === 1 || !isset($this->translations)) {
            $slugParams = $this->getSingleSlugParams($locale);
            if ($slugParams != null && !empty($slugParams)) {
                return $slugParams;
            }
        }

        $slugParams = [];
        foreach ($this->translations as $translation) {
            if ($translation->locale == $locale || $locale == null) {
                $attributes = $this->slugAttributes;

                $slugAttribute = array_shift($attributes);

                $slugDependenciesAttributes = [];
                foreach ($attributes as $attribute) {
                    if (!isset($this->$attribute)) {
                        throw new \Exception("You must define the field {$attribute} in your model");
                    }

                    $slugDependenciesAttributes[$attribute] = $this->$attribute;
                }

                if (!isset($translation->$slugAttribute) && !isset($this->$slugAttribute)) {
                    throw new \Exception("You must define the field {$slugAttribute} in your model");
                }

                $slugParam = [
                    'active' => $translation->active,
                    'slug' => $translation->$slugAttribute ?? $this->$slugAttribute,
                    'locale' => $translation->locale,
                ] + $slugDependenciesAttributes;

                if ($locale != null) {
                    return $slugParam;
                }

                $slugParams[] = $slugParam;
            }
        }

        return $locale == null ? $slugParams : null;
    }

    public function getSingleSlugParams($locale = null)
    {
        $slugParams = [];
        foreach (getLocales() as $appLocale) {
            if ($appLocale == $locale || $locale == null) {
                $attributes = $this->slugAttributes;
                $slugAttribute = array_shift($attributes);
                $slugDependenciesAttributes = [];
                foreach ($attributes as $attribute) {
                    if (!isset($this->$attribute)) {
                        throw new \Exception("You must define the field {$attribute} in your model");
                    }

                    $slugDependenciesAttributes[$attribute] = $this->$attribute;
                }

                if (!isset($this->$slugAttribute)) {
                    throw new \Exception("You must define the field {$slugAttribute} in your model");
                }

                $slugParam = [
                    'active' => 1,
                    'slug' => $this->$slugAttribute,
                    'locale' => $appLocale,
                ] + $slugDependenciesAttributes;

                if ($locale != null) {
                    return $slugParam;
                }

                $slugParams[] = $slugParam;
            }
        }

        return $locale == null ? $slugParams : null;
    }

    public function getSlugsTable()
    {
        return $this->slugs()->getRelated()->getTable();
    }

    public function getForeignKey()
    {
        return Str::snake(class_basename(get_class($this))) . "_id";
    }

    protected function getSuffixSlug()
    {
        return $this->id;
    }

    public function getUtf8Slug($str, $options = [])
    {
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        $str = mb_convert_encoding((string) $str, 'UTF-8', mb_list_encodings());

        $defaults = array(
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => array(),
            'transliterate' => true,
        );

        // Merge options
        $options = array_merge($defaults, $options);

        $char_map = array(
            // Latin
            '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'AE', '??' => 'C',
            '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'I', '??' => 'I', '??' => 'I', '??' => 'I',
            '??' => 'D', '??' => 'N', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O',
            '??' => 'O', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'Y', '??' => 'TH',
            '??' => 'ss',
            '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'ae', '??' => 'c',
            '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'i', '??' => 'i', '??' => 'i', '??' => 'i',
            '??' => 'd', '??' => 'n', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o',
            '??' => 'o', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'y', '??' => 'th',
            '??' => 'y',

            // Latin symbols
            '??' => '(c)',

            // Greek
            '??' => 'A', '??' => 'B', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Z', '??' => 'H', '??' => '8',
            '??' => 'I', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => '3', '??' => 'O', '??' => 'P',
            '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'Y', '??' => 'F', '??' => 'X', '??' => 'PS', '??' => 'W',
            '??' => 'A', '??' => 'E', '??' => 'I', '??' => 'O', '??' => 'Y', '??' => 'H', '??' => 'W', '??' => 'I',
            '??' => 'Y',
            '??' => 'a', '??' => 'b', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'z', '??' => 'h', '??' => '8',
            '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => '3', '??' => 'o', '??' => 'p',
            '??' => 'r', '??' => 's', '??' => 't', '??' => 'y', '??' => 'f', '??' => 'x', '??' => 'ps', '??' => 'w',
            '??' => 'a', '??' => 'e', '??' => 'i', '??' => 'o', '??' => 'y', '??' => 'h', '??' => 'w', '??' => 's',
            '??' => 'i', '??' => 'y', '??' => 'y', '??' => 'i',

            // Turkish
            '??' => 'S', '??' => 'I', '??' => 'C', '??' => 'U', '??' => 'O', '??' => 'G',
            '??' => 's', '??' => 'i', '??' => 'c', '??' => 'u', '??' => 'o', '??' => 'g',

            // Russian
            '??' => 'A', '??' => 'B', '??' => 'V', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Yo', '??' => 'Zh',
            '??' => 'Z', '??' => 'I', '??' => 'J', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => 'O',
            '??' => 'P', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U', '??' => 'F', '??' => 'H', '??' => 'C',
            '??' => 'Ch', '??' => 'Sh', '??' => 'Sh', '??' => '', '??' => 'Y', '??' => '', '??' => 'E', '??' => 'Yu',
            '??' => 'Ya',
            '??' => 'a', '??' => 'b', '??' => 'v', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'yo', '??' => 'zh',
            '??' => 'z', '??' => 'i', '??' => 'j', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => 'o',
            '??' => 'p', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u', '??' => 'f', '??' => 'h', '??' => 'c',
            '??' => 'ch', '??' => 'sh', '??' => 'sh', '??' => '', '??' => 'y', '??' => '', '??' => 'e', '??' => 'yu',
            '??' => 'ya',

            // Ukrainian
            '??' => 'Ye', '??' => 'I', '??' => 'Yi', '??' => 'G',
            '??' => 'ye', '??' => 'i', '??' => 'yi', '??' => 'g',

            // Kazakh
            '??' => 'A', '??' => 'G', '??' => 'Q', '??' => 'N', '??' => 'O', '??' => 'U',
            '??' => 'a', '??' => 'g', '??' => 'q', '??' => 'n', '??' => 'o', '??' => 'u',

            // Czech
            '??' => 'C', '??' => 'D', '??' => 'E', '??' => 'N', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U',
            '??' => 'Z',
            '??' => 'c', '??' => 'd', '??' => 'e', '??' => 'n', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u',
            '??' => 'z',

            // Polish
            '??' => 'A', '??' => 'C', '??' => 'e', '??' => 'L', '??' => 'N', '??' => 'o', '??' => 'S', '??' => 'Z',
            '??' => 'Z',
            '??' => 'a', '??' => 'c', '??' => 'e', '??' => 'l', '??' => 'n', '??' => 'o', '??' => 's', '??' => 'z',
            '??' => 'z',

            // Latvian
            '??' => 'A', '??' => 'C', '??' => 'E', '??' => 'G', '??' => 'i', '??' => 'k', '??' => 'L', '??' => 'N',
            '??' => 'S', '??' => 'u', '??' => 'Z',
            '??' => 'a', '??' => 'c', '??' => 'e', '??' => 'g', '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'n',
            '??' => 's', '??' => 'u', '??' => 'z',

            // Romanian
            '??' => 'A', '??' => 'A', '??' => 'I', '??' => 'S', '??' => 'T',
            '??' => 'a', '??' => 'a', '??' => 'i', '??' => 's', '??' => 't',
        );

        // Make custom replacements
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

        // Transliterate characters to ASCII
        if ($options['transliterate']) {
            $str = str_replace(array_keys($char_map), $char_map, $str);
        }

        // Replace non-alphanumeric characters with our delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate slug to max. characters
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

        // Remove delimiter from ends
        $str = trim($str, $options['delimiter']);

        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }

    public function urlSlugShorter($string)
    {
        return strtolower(trim(preg_replace('~[^0-9a-z]+~i', '-', html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8')), '-'));
    }

    public function getNamespace()
    {
        $pos = mb_strrpos(self::class, '\\');

        if ($pos === false) {
            return self::class;
        }

        return Str::substr(self::class, 0, $pos);
    }
}
