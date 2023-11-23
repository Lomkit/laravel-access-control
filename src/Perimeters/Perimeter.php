<?php

namespace Lomkit\Access\Perimeters;

class Perimeter
{
    /**
     * Set the priority of the perimeter.
     *
     * @param  integer $priority
     * @return PendingPerimeterRegistration
     */
    public function priority(int $priority): PendingPerimeterRegistration
    {
        return (new PendingPerimeterRegistration($this))->priority($priority);
    }

    /**
     * Set the name of the perimeter.
     *
     * @param  string $name
     * @return PendingPerimeterRegistration
     */
    public function name(string $name): PendingPerimeterRegistration
    {
        return (new PendingPerimeterRegistration($this))->name($name);
    }

    /**
     * Register the perimeter.
     *
     * @return PerimeterCollection
     */
    public function register()
    {
        // @TODO: le soucis ici c'est que le perimeter ne contient pas les infos mais c'est le pending perimeter plutôt --> quasiment prêt mais il faut faire en sorte que l'on puisse récupérer tous les périmètres --> createRoute comme dans le router ????
        return app(Perimeters::class)
            ->register($this);
    }
}