<?php

namespace Tochka\GeoPHP\Geometry;

/**
 * GeometryCollection: A heterogenous collection of geometries
 * @api
 * @extends Collection<GeometryInterface>
 */
class GeometryCollection extends Collection
{
    // We need to override asArray. Because geometryCollections are heterogeneous
    // we need to specify which type of geometries they contain. We need to do this
    // because, for example, there would be no way to tell the difference between a
    // MultiPoint or a LineString, since they share the same structure (collection
    // of points). So we need to call out the type explicitly.
    public function asArray(): array
    {
        $array = [];
        foreach ($this->getComponents() as $component) {
            $array[] = [
                'type' => $component->geometryType(),
                'components' => $component->asArray(),
            ];
        }
        return $array;
    }

    // Not valid for this geomettry
    public function boundary(): ?GeometryInterface
    {
        return null;
    }

    public function isSimple(): bool
    {
        return false;
    }

    public function geometryType(): string
    {
        return 'GeometryCollection';
    }
}
