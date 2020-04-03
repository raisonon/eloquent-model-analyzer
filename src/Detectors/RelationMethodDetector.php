<?php

namespace Naoray\EloquentModelAnalyzer\Detectors;

use ReflectionMethod;
use ReflectionObject;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Naoray\EloquentModelAnalyzer\RelationMethod;
use Illuminate\Database\Eloquent\Relations\Relation;
use Naoray\EloquentModelAnalyzer\Contracts\Detector;
use Naoray\EloquentModelAnalyzer\Traits\InteractsWithRelationMethods;

class RelationMethodDetector implements Detector
{
    use InteractsWithRelationMethods;

    /**
     * @var Model
     */
    protected $model;

    /**
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Analyzes given model with reflection to gain all methods which return
     * Relation instances e.g. belongsTo, hasMany, hasOne, etc.
     *
     * @return Collection
     */
    public function analyze(): Collection
    {
        $reflectionObject = new ReflectionObject($this->model);

        return collect($reflectionObject->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) {
                return $this->isRelationMethod($method);
            })
            ->map(function ($method) use ($reflectionObject) {
                return new RelationMethod([
                    'method' => $method,
                    'model' => $this->model,
                    'reflection' => $reflectionObject,
                ]);
            });
    }

    protected function isRelationMethod(ReflectionMethod $method): bool
    {
        if (method_exists(Model::class, $method->getName())) {
            return false;
        }

        if ($method->hasReturnType()) {
            return $this->isRelationReturnType($method->getReturnType());
        }

        if ($docComment = $method->getDocComment()) {
            return (bool)$this->extractReturnTypeFromDocs($docComment);
        }

        if ($method->getNumberOfParameters() > 0) {
            return false;
        }

        $relationObject = $this->model->{$method->getName()}();

        return $relationObject instanceof Relation;
    }
}
