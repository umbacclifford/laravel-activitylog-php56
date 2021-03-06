<?php

namespace Spatie\Activitylog\Traits;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Exceptions\CouldNotLogChanges;

trait DetectsChanges
{
    protected $oldAttributes = [];

    protected static function bootDetectsChanges()
    {
        if (static::eventsToBeRecorded()->contains('updated')) {
            static::updating(function (Model $model) {

                //temporary hold the original attributes on the model
                //as we'll need these in the updating event
                $oldValues = $model->replicate()->setRawAttributes($model->getOriginal());

                $model->oldAttributes = static::logChanges($oldValues);
            });
        }
    }


    /**
     * @return array
     */
    public function attributesToBeLogged()
    {
        if (! isset(static::$logAttributes)) {
            return [];
        }

        return static::$logAttributes;
    }


    /**
     * @param string $processingEvent
     * @return array
     */
    public function attributeValuesToBeLogged($processingEvent)
    {
        if (! count($this->attributesToBeLogged())) {
            return [];
        }

        $properties['attributes'] = static::logChanges($this->exists ? $this->fresh() : $this);

        if (static::eventsToBeRecorded()->contains('updated') && $processingEvent == 'updated') {
            $nullProperties = array_fill_keys(array_keys($properties['attributes']), null);

            $properties['old'] = array_merge($nullProperties, $this->oldAttributes);
        }

        return $properties;
    }


    /**
     * @param Model $model
     * @return array
     */
    public static function logChanges(Model $model)
    {
        return collect($model->attributesToBeLogged())->mapWithKeys(
            function ($attribute) use ($model) {
                if (str_contains($attribute, '.')) {
                    return self::getRelatedModelAttributeValue($model, $attribute);
                }

                return collect($model)->only($attribute);
            }
        )->toArray();
    }


    /**
     * @param Model  $model
     * @param string $attribute
     * @return array
     * @throws CouldNotLogChanges
     */
    protected static function getRelatedModelAttributeValue(Model $model, $attribute)
    {
        if (substr_count($attribute, '.') > 1) {
            throw CouldNotLogChanges::invalidAttribute($attribute);
        }

        list($relatedModelName, $relatedAttribute) = explode('.', $attribute);

        $relatedModel = isset($model->$relatedModelName)
            ? $model->$relatedModelName
            : $model->$relatedModelName();

        return ["{$relatedModelName}.{$relatedAttribute}" => $relatedModel->$relatedAttribute];
    }
}
