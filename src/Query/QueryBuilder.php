<?php

namespace Amanank\HalClient\Query;

use Illuminate\Support\Collection;

class QueryBuilder {

    public function __construct() {
    }

    // use php catch all to handle all method calls and throw not implemented exception
    public function __call($name, $arguments) {
        // print stack trace
        $calledMethod = (new Collection(debug_backtrace()))
            ->filter(fn($trace) => isset($trace['function']) && $trace['function'] === '__call')
            ->map(fn($trace) => $trace['args'][0])
            ->last() ?? $name;

        throw new \Exception("Method [$calledMethod] not supported");
    }
}
